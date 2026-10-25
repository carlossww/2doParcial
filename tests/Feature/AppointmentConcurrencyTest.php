<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\SeedsVidaSalud;
use Tests\Support\TestDatabaseSafety;
use Tests\TestCase;

#[Group('concurrency')]
class AppointmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations, SeedsVidaSalud;

    /**
     * Las conexiones hijas necesitan fixtures confirmados. El snapshot protege
     * rollback; la limpieza usa migraciones nuevas sobre el esquema test aislado.
     */
    public function runDatabaseMigrations(): void
    {
        RefreshDatabaseState::$migrated = false;
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            TestDatabaseSafety::resetSchema($this->app);
            $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        });
    }

    /** @return array<string, array{bool}> */
    public static function conflictingReservations(): array
    {
        return ['mismo médico, pacientes diferentes' => [false], 'mismo paciente, médicos diferentes' => [true]];
    }

    #[DataProvider('conflictingReservations')]
    public function test_only_one_conflicting_reservation_commits_across_two_real_processes(bool $samePatient): void
    {
        $this->seedVidaSalud();
        $first = $this->appointmentPayload();
        $second = $first;
        if ($samePatient) {
            $second['medico_id'] = $this->otherDoctor();
            DB::table('medico_especialidad')->insert(['medico_id' => $second['medico_id'], 'especialidad_id' => $first['especialidad_id']]);
            DB::table('horario')->insert(['medico_id' => $second['medico_id'], 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00']);
        } else {
            $second['paciente_id'] = $this->otherPatient();
        }
        $directory = sys_get_temp_dir().'/vida_salud_race_'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $applicationName = basename($directory);
        $processes = [];

        try {
            foreach (['holder' => $first, 'contender' => $second] as $name => $payload) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/appointment-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'DB_URL' => '', 'DB_CONNECTION' => 'pgsql',
                    'DB_DATABASE' => DB::connection()->getDatabaseName(),
                ]);
                $process->setInput(json_encode([
                    'database' => DB::connection()->getConfig(), 'app_key' => config('app.key'),
                    'actor' => $this->account('recepcion')->getKey(), 'payload' => $payload,
                    'holder' => $name === 'holder', 'directory' => $directory,
                    'application_name' => $applicationName.'_'.$name, 'name' => $name,
                ], JSON_THROW_ON_ERROR));
                $process->setTimeout(25);
                $process->start();
                $processes[$name] = $process;
            }
            $this->awaitCondition(static fn (): bool => is_file($directory.'/holder.ready') && is_file($directory.'/contender.ready'), $processes);
            touch($directory.'/start');
            $this->awaitCondition(static fn (): bool => is_file($directory.'/holder.responded'), $processes);
            $this->awaitCondition(static fn (): bool => DB::table('pg_stat_activity')
                ->where('application_name', $applicationName.'_contender')->where('wait_event_type', 'Lock')->exists(), $processes);
            touch($directory.'/commit');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }

            $this->assertSame(302, json_decode($processes['holder']->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status']);
            $this->assertSame(422, json_decode($processes['contender']->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status']);
            $this->assertDatabaseCount('cita', 1);
            $this->assertDatabaseHas('cita', ['paciente_id' => $first['paciente_id'], 'medico_id' => $first['medico_id'], 'estado' => 'Programada']);
            $this->assertDatabaseCount('evento_cita', 1);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /** @param array<string, Process> $processes */
    private function awaitCondition(callable $condition, array $processes): void
    {
        $deadline = microtime(true) + 10;
        while (! $condition()) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Un proceso terminó antes de completar la barrera: '.$process->getErrorOutput().' '.$process->getOutput());
                }
            }
            if (microtime(true) >= $deadline) {
                $this->fail('No se observó la barrera o el bloqueo real de PostgreSQL dentro de 10 segundos.');
            }
            usleep(10000);
        }
    }
}

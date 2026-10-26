<?php

namespace Tests\Feature;

use App\Modules\Shared\Clinic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class AppointmentDurationTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    public static function durations(): array
    {
        return ['15 minutos' => [15], '30 minutos' => [30], '45 minutos' => [45], '60 minutos' => [60]];
    }

    #[DataProvider('durations')]
    public function test_availability_and_legacy_booking_use_the_schedule_duration(int $duration): void
    {
        $payload = $this->schedule($duration);
        $slots = DB::select('SELECT * FROM vida_salud.horarios_disponibles(?, ?::date)', [
            $payload['medico_id'], Carbon::parse($payload['inicio'])->toDateString(),
        ]);

        $this->assertCount(intdiv(180, $duration), $slots);
        $this->assertSame($duration, (int) Carbon::parse($slots[0]->inicio)->diffInMinutes(Carbon::parse($slots[0]->fin)));

        $id = $this->book($payload);
        $appointment = DB::table('cita')->find($id);
        $this->assertSame($duration, $appointment->duracion_minutos);
        $this->assertTrue(Carbon::parse($appointment->fin)->equalTo(Carbon::parse($payload['inicio'])->addMinutes($duration)));
        $this->assertSame('Programada', $appointment->estado);
        $this->assertDatabaseHas('evento_cita', ['cita_id' => $id, 'fin_nuevo' => $appointment->fin]);

        $remaining = DB::select('SELECT * FROM vida_salud.horarios_disponibles(?, ?::date)', [
            $payload['medico_id'], Carbon::parse($payload['inicio'])->toDateString(),
        ]);
        $this->assertCount(count($slots) - 1, $remaining);
    }

    #[DataProvider('durations')]
    public function test_sql_insert_does_not_trust_a_supplied_ending_or_duration(int $duration): void
    {
        $payload = $this->schedule($duration);
        $id = Clinic::transaction($this->account('recepcion'), fn () => DB::table('cita')->insertGetId([
            ...$payload,
            'fin' => Carbon::parse($payload['inicio'])->addMinutes(5),
            'duracion_minutos' => 20,
            'creada_por' => $this->account('recepcion')->id,
            'modificada_por' => $this->account('recepcion')->id,
        ]));

        $appointment = DB::table('cita')->find($id);
        $this->assertSame($duration, $appointment->duracion_minutos);
        $this->assertTrue(Carbon::parse($appointment->fin)->equalTo(Carbon::parse($payload['inicio'])->addMinutes($duration)));
    }

    public function test_admin_can_configure_duration_in_the_schedule_form(): void
    {
        $payload = $this->schedule(30);
        $schedule = DB::table('horario')->where('medico_id', $payload['medico_id'])->first();

        $this->actingAs($this->account('admin'))
            ->put(route('catalog.update', ['module' => 'horarios', 'id' => $schedule->id]), [
                'medico_id' => $payload['medico_id'], 'dia_semana' => 1,
                'hora_inicio' => '09:00', 'hora_fin' => '12:00', 'duracion_minutos' => 45, 'activo' => '1',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('horario', ['id' => $schedule->id, 'duracion_minutos' => 45]);
        $this->get(route('catalog.edit', ['module' => 'horarios', 'id' => $schedule->id]))
            ->assertOk()->assertSee('duracion_minutos', false)->assertSee('45 minutos');
    }

    public function test_schedule_form_rejects_an_unsupported_duration_and_incomplete_block(): void
    {
        $payload = $this->schedule(30);
        $schedule = DB::table('horario')->where('medico_id', $payload['medico_id'])->first();
        $data = ['medico_id' => $payload['medico_id'], 'dia_semana' => 1,
            'hora_inicio' => '09:00', 'hora_fin' => '12:00', 'activo' => '1'];

        $this->actingAs($this->account('admin'))
            ->put(route('catalog.update', ['module' => 'horarios', 'id' => $schedule->id]), [...$data, 'duracion_minutos' => 20])
            ->assertSessionHasErrors('duracion_minutos');
        $this->put(route('catalog.update', ['module' => 'horarios', 'id' => $schedule->id]), [
            ...$data, 'hora_fin' => '10:00', 'duracion_minutos' => 45,
        ])->assertSessionHasErrors('hora_fin');
        $this->assertDatabaseHas('horario', ['id' => $schedule->id, 'duracion_minutos' => 30, 'hora_fin' => '12:00']);
    }

    public function test_postgres_rejects_unsupported_duration_and_incomplete_schedule(): void
    {
        $payload = $this->schedule(30);
        $this->assertRejected(fn () => DB::table('horario')->where('medico_id', $payload['medico_id'])->update(['duracion_minutos' => 20]), '23514');
        $this->assertRejected(fn () => DB::table('horario')->where('medico_id', $payload['medico_id'])->update([
            'duracion_minutos' => 45, 'hora_fin' => '10:00',
        ]), '23514');
    }

    public function test_misaligned_and_outside_blocks_are_rejected_without_events(): void
    {
        $payload = $this->schedule(45);
        foreach (['09:10', '11:45'] as $time) {
            $start = Carbon::parse($payload['inicio'])->setTimeFromTimeString($time);
            $this->assertRejected(fn () => $this->book([...$payload, 'inicio' => $start->toIso8601String()]), 'P0001');
        }
        $this->assertDatabaseCount('cita', 0);
        $this->assertDatabaseCount('evento_cita', 0);
    }

    public function test_contiguous_bookings_are_allowed_and_overlapping_doctor_booking_is_rejected(): void
    {
        $payload = $this->schedule(15);
        $first = $this->book($payload);
        $next = [...$payload, 'inicio' => Carbon::parse($payload['inicio'])->addMinutes(15)->toIso8601String()];
        $second = $this->book($next);

        $this->assertRejected(fn () => $this->book([...$payload, 'paciente_id' => $this->otherPatient()]), '23P01');
        $this->assertDatabaseCount('cita', 2);
        $this->assertDatabaseCount('evento_cita', 2);
        $this->assertSame(DB::table('cita')->find($first)->fin, DB::table('cita')->find($second)->inicio);
    }

    public function test_patient_cannot_have_overlapping_appointments_with_different_doctors(): void
    {
        $payload = $this->schedule(15);
        $this->book($payload);
        $otherDoctor = $this->otherDoctor();
        DB::table('medico_especialidad')->insert(['medico_id' => $otherDoctor, 'especialidad_id' => $payload['especialidad_id']]);
        DB::table('horario')->insert([
            'medico_id' => $otherDoctor, 'dia_semana' => 1, 'hora_inicio' => '09:00',
            'hora_fin' => '12:00', 'duracion_minutos' => 60, 'activo' => true,
        ]);

        $this->assertRejected(fn () => $this->book([...$payload, 'medico_id' => $otherDoctor]), '23P01');
        $this->assertDatabaseCount('cita', 1);
        $this->assertDatabaseCount('evento_cita', 1);
    }

    public function test_duration_change_with_pending_appointments_is_rejected_without_partial_changes(): void
    {
        $payload = $this->schedule(15);
        $id = $this->book($payload);
        $schedule = DB::table('horario')->where('medico_id', $payload['medico_id'])->first();

        $this->actingAs($this->account('admin'))
            ->put(route('catalog.update', ['module' => 'horarios', 'id' => $schedule->id]), [
                'medico_id' => $payload['medico_id'], 'dia_semana' => 1,
                'hora_inicio' => '09:00', 'hora_fin' => '12:00', 'duracion_minutos' => 30, 'activo' => '1',
            ])->assertSessionHasErrors('operacion');

        $this->assertDatabaseHas('horario', ['id' => $schedule->id, 'duracion_minutos' => 15]);
        $this->assertDatabaseHas('cita', ['id' => $id, 'duracion_minutos' => 15, 'estado' => 'Programada']);
        $this->assertDatabaseCount('evento_cita', 1);
    }

    public function test_rescheduling_uses_destination_duration_and_records_both_endings(): void
    {
        $payload = $this->schedule(30);
        DB::table('horario')->insert([
            'medico_id' => $payload['medico_id'], 'dia_semana' => 1, 'hora_inicio' => '13:00',
            'hora_fin' => '14:00', 'duracion_minutos' => 15, 'activo' => true,
        ]);
        $id = $this->book($payload);
        $old = DB::table('cita')->find($id);
        Clinic::transaction($this->account('recepcion'), fn () => DB::table('cita')->where('id', $id)->update([
            'estado' => 'Confirmada', 'modificada_por' => $this->account('recepcion')->id,
        ]));
        $destination = Carbon::parse($payload['inicio'])->setTime(13, 0);

        Clinic::transaction($this->account('recepcion'), fn () => DB::select('SELECT vida_salud.reprogramar_cita(?, ?, ?::timestamptz)', [
            $this->account('recepcion')->id, $id, $destination->toIso8601String(),
        ]));

        $appointment = DB::table('cita')->find($id);
        $event = DB::table('evento_cita')->where('cita_id', $id)->orderByDesc('id')->first();
        $this->assertSame('Programada', $appointment->estado);
        $this->assertSame(15, $appointment->duracion_minutos);
        $this->assertTrue(Carbon::parse($appointment->fin)->equalTo($destination->copy()->addMinutes(15)));
        $this->assertSame($old->inicio, $event->inicio_anterior);
        $this->assertSame($old->fin, $event->fin_anterior);
        $this->assertSame($appointment->fin, $event->fin_nuevo);
        $this->assertDatabaseCount('evento_cita', 3);
    }

    public function test_conflicting_reschedule_rolls_back_interval_duration_and_events(): void
    {
        $payload = $this->schedule(45);
        $id = $this->book($payload);
        $later = Carbon::parse($payload['inicio'])->addMinutes(45);
        $this->book([...$payload, 'inicio' => $later->toIso8601String()]);
        $before = DB::table('cita')->find($id);

        $this->assertRejected(fn () => DB::select('SELECT vida_salud.reprogramar_cita(?, ?, ?::timestamptz)', [
            $this->account('recepcion')->id, $id, $later->toIso8601String(),
        ]), '23P01');

        $this->assertEquals($before, DB::table('cita')->find($id));
        $this->assertDatabaseCount('evento_cita', 2);
    }

    public function test_cancelled_booking_keeps_its_duration_when_the_schedule_changes(): void
    {
        $payload = $this->schedule(15);
        $id = $this->book($payload);
        Clinic::transaction($this->account('recepcion'), fn () => DB::select('SELECT vida_salud.cancelar_cita(?, ?, ?)', [
            $this->account('recepcion')->id, $id, 'Cambio de disponibilidad',
        ]));
        DB::table('horario')->where('medico_id', $payload['medico_id'])->update(['duracion_minutos' => 60]);

        $this->assertDatabaseHas('cita', ['id' => $id, 'duracion_minutos' => 15, 'estado' => 'Cancelada']);
        $this->assertSame(60, DB::table('horario')->where('medico_id', $payload['medico_id'])->value('duracion_minutos'));
        $replacement = $this->book($payload);
        $this->assertDatabaseHas('cita', ['id' => $replacement, 'duracion_minutos' => 60]);
    }

    public function test_upgrade_preserves_legacy_appointments_and_immutable_events(): void
    {
        $this->seedVidaSalud();
        $migration = require database_path('migrations/2026_10_25_000002_configure_appointment_duration.php');
        $migration->down();
        $payload = $this->appointmentPayload();
        $id = $this->book($payload);
        $appointment = (array) DB::table('cita')->find($id);
        $events = DB::table('evento_cita')->where('cita_id', $id)->get()->map(fn ($row) => (array) $row)->all();
        $auditCount = DB::table('bitacora')->count();

        $migration->up();

        $upgraded = (array) DB::table('cita')->find($id);
        $this->assertSame(30, $upgraded['duracion_minutos']);
        unset($upgraded['duracion_minutos']);
        $this->assertEquals($appointment, $upgraded);
        $upgradedEvents = DB::table('evento_cita')->where('cita_id', $id)->get()->map(function ($row) {
            $this->assertNull($row->fin_anterior);
            $this->assertNull($row->fin_nuevo);
            $event = (array) $row;
            unset($event['fin_anterior'], $event['fin_nuevo']);

            return $event;
        })->all();
        $this->assertEquals($events, $upgradedEvents);
        $this->assertSame($auditCount, DB::table('bitacora')->count());
        $this->assertSame(0, DB::table('horario')->where('duracion_minutos', '<>', 30)->count());
    }

    private function schedule(int $duration): array
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        DB::table('horario')->where('medico_id', $payload['medico_id'])->update(['duracion_minutos' => $duration]);

        return $payload;
    }

    private function book(array $payload): int
    {
        return Clinic::transaction($this->account('recepcion'), fn () => (int) DB::selectOne(
            'SELECT vida_salud.agendar_cita(?, ?, ?, ?, ?::timestamptz, ?) AS id',
            [$this->account('recepcion')->id, $payload['paciente_id'], $payload['medico_id'],
                $payload['especialidad_id'], $payload['inicio'], $payload['motivo_consulta'] ?? null]
        )->id);
    }

    private function assertRejected(callable $operation, string $sqlState): void
    {
        try {
            DB::transaction($operation);
            $this->fail('La operación incompatible debió rechazarse.');
        } catch (QueryException $error) {
            $this->assertSame($sqlState, $error->errorInfo[0]);
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class DoctorDirectoryTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedVidaSalud();
    }

    private function doctor(): object
    {
        return DB::table('medico')->where('usuario_id', $this->account('medico')->id)->first();
    }

    private function specialtyId(): int
    {
        return (int) DB::table('especialidad')->where('nombre', 'Medicina General')->value('id');
    }

    private function payload(array $overrides = []): array
    {
        $account = $this->account('medico');
        $doctor = $this->doctor();

        return [
            'usuario_id' => $account->id, 'nombres' => $account->nombres, 'apellidos' => $account->apellidos,
            'correo' => $account->correo, 'telefono' => $doctor->telefono,
            'registro_profesional' => $doctor->registro_profesional,
            'especialidad_ids' => [$this->specialtyId()], 'activo' => '1',
            ...$overrides,
        ];
    }

    private function updateUrl(): string
    {
        return route('catalog.update', ['module' => 'medicos', 'id' => $this->doctor()->id]);
    }

    private function disableSpecialty(): void
    {
        $this->put(route('catalog.update', ['module' => 'especialidades', 'id' => $this->specialtyId()]), [
            'nombre' => 'Medicina General', 'activo' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public static function directoryEdits(): array
    {
        return [
            'administración edita' => ['admin', '1'], 'administración desactiva' => ['admin', '0'],
            'recepción edita' => ['recepcion', '1'], 'recepción desactiva' => ['recepcion', '0'],
        ];
    }

    #[DataProvider('directoryEdits')]
    public function test_edit_and_deactivation_preserve_the_last_inactive_specialty_and_history(string $login, string $active): void
    {
        $appointmentId = $this->existingAppointment($this->appointmentPayload());
        DB::table('cita')->where('id', $appointmentId)->update(['estado' => 'Cancelada', 'motivo_cancelacion' => 'Baja del directorio']);
        $history = DB::table('evento_cita')->orderBy('id')->get()->toJson();
        $account = $this->account('medico');
        $this->actingAs($this->account($login));
        $this->disableSpecialty();
        $links = DB::table('medico_especialidad')->orderBy('especialidad_id')->get()->toJson();
        $linkAudits = DB::table('bitacora')->where('entidad', 'medico_especialidad')->count();

        $html = $this->get(route('catalog.edit', ['module' => 'medicos', 'id' => $this->doctor()->id]))
            ->assertOk()->assertSee('Medicina General (inactiva)')->getContent();
        $this->assertSame(1, preg_match('/<option\b[^>]*value="'.$this->specialtyId().'"[^>]*\bselected\b[^>]*>Medicina General \(inactiva\)<\/option>/', $html, $option));
        $this->assertStringNotContainsString('disabled', $option[0]);

        $this->put($this->updateUrl(), $this->payload(['nombres' => 'Contacto actualizado', 'activo' => $active]))
            ->assertRedirect(route('catalog.index', 'medicos'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medico', ['id' => $this->doctor()->id, 'activo' => $active === '1']);
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'nombres' => 'Contacto actualizado', 'rol_id' => $account->rol_id, 'password_hash' => $account->password_hash, 'activo' => true]);
        $this->assertDatabaseHas('especialidad', ['id' => $this->specialtyId(), 'activo' => false]);
        $this->assertSame($links, DB::table('medico_especialidad')->orderBy('especialidad_id')->get()->toJson());
        $this->assertSame($linkAudits, DB::table('bitacora')->where('entidad', 'medico_especialidad')->count());
        $this->assertSame($history, DB::table('evento_cita')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('cita', ['id' => $appointmentId, 'estado' => 'Cancelada']);
    }

    public function test_inactive_account_and_last_inactive_specialty_do_not_block_profile_deactivation(): void
    {
        $this->actingAs($this->account('recepcion'));
        $this->disableSpecialty();
        DB::table('usuario')->where('id', $this->account('medico')->id)->update(['activo' => false]);
        $this->put($this->updateUrl(), $this->payload(['activo' => '0']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medico', ['id' => $this->doctor()->id, 'activo' => false]);
        $this->assertDatabaseHas('usuario', ['id' => $this->account('medico')->id, 'activo' => false]);
    }

    public function test_reactivation_requires_an_active_specialty_and_preserves_inactive_links(): void
    {
        $this->actingAs($this->account('recepcion'));
        $this->disableSpecialty();
        DB::table('medico')->where('id', $this->doctor()->id)->update(['activo' => false]);
        $originalName = $this->account('medico')->nombres;

        $this->putJson($this->updateUrl(), $this->payload(['nombres' => 'No debe guardarse']))
            ->assertUnprocessable()->assertJsonValidationErrors('especialidad_ids');
        $this->assertDatabaseHas('medico', ['id' => $this->doctor()->id, 'activo' => false]);
        $this->assertDatabaseHas('usuario', ['id' => $this->account('medico')->id, 'nombres' => $originalName]);

        $newId = DB::table('especialidad')->insertGetId(['nombre' => 'Cardiología']);
        $this->put($this->updateUrl(), $this->payload(['especialidad_ids' => [$this->specialtyId(), $newId]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medico', ['id' => $this->doctor()->id, 'activo' => true]);
        $this->assertDatabaseHas('medico_especialidad', ['medico_id' => $this->doctor()->id, 'especialidad_id' => $this->specialtyId(), 'activo' => true]);
        $this->assertDatabaseHas('medico_especialidad', ['medico_id' => $this->doctor()->id, 'especialidad_id' => $newId, 'activo' => true]);
        $this->assertDatabaseHas('especialidad', ['id' => $this->specialtyId(), 'activo' => false]);
    }

    public static function profileStates(): array
    {
        return [['1'], ['0']];
    }

    #[DataProvider('profileStates')]
    public function test_creation_cannot_assign_an_inactive_specialty_even_to_an_inactive_profile(string $active): void
    {
        $this->actingAs($this->account('recepcion'));
        $this->disableSpecialty();
        $account = $this->account('medico');
        $userId = DB::table('usuario')->insertGetId([
            'rol_id' => $account->rol_id, 'nombre_usuario' => 'medica.nueva', 'nombres' => 'Nueva',
            'apellidos' => 'Doctora', 'password_hash' => $account->password_hash,
        ]);
        $this->get(route('catalog.create', 'medicos'))->assertOk()->assertDontSee('Medicina General (inactiva)');
        $this->postJson(route('catalog.store', 'medicos'), $this->payload([
            'usuario_id' => $userId, 'registro_profesional' => 'MED-NEW', 'correo' => null,
            'nombres' => 'No debe guardarse', 'activo' => $active,
        ]))->assertUnprocessable()->assertJsonValidationErrors('especialidad_ids');
        $this->assertDatabaseMissing('medico', ['usuario_id' => $userId]);
        $this->assertDatabaseHas('usuario', ['id' => $userId, 'nombres' => 'Nueva']);
    }

    public static function invalidInactiveAssignments(): array
    {
        return [
            'ajena al editar' => [false, '1'], 'ajena al desactivar' => [false, '0'],
            'retirada al editar' => [true, '1'], 'retirada al desactivar' => [true, '0'],
        ];
    }

    #[DataProvider('invalidInactiveAssignments')]
    public function test_inactive_specialty_cannot_be_added_or_reassigned(bool $removed, string $active): void
    {
        $invalidId = DB::table('especialidad')->insertGetId(['nombre' => 'Especialidad retirada', 'activo' => false]);
        $owner = $removed ? $this->doctor()->id : $this->otherDoctor();
        DB::table('medico_especialidad')->insert(['medico_id' => $owner, 'especialidad_id' => $invalidId, 'activo' => ! $removed]);
        $links = DB::table('medico_especialidad')->orderBy('medico_id')->orderBy('especialidad_id')->get()->toJson();
        $name = $this->account('medico')->nombres;
        $this->actingAs($this->account('recepcion'))
            ->get(route('catalog.edit', ['module' => 'medicos', 'id' => $this->doctor()->id]))
            ->assertOk()->assertViewHas('options', fn ($options) => ! array_key_exists($invalidId, $options['especialidad_ids']));
        $this->putJson($this->updateUrl(), $this->payload([
            'especialidad_ids' => [$this->specialtyId(), $invalidId], 'nombres' => 'No debe guardarse', 'activo' => $active,
        ]))->assertUnprocessable()->assertJsonValidationErrors('especialidad_ids');
        $this->assertDatabaseHas('usuario', ['id' => $this->account('medico')->id, 'nombres' => $name]);
        $this->assertDatabaseHas('medico', ['id' => $this->doctor()->id, 'activo' => true]);
        $this->assertSame($links, DB::table('medico_especialidad')->orderBy('medico_id')->orderBy('especialidad_id')->get()->toJson());
    }

    public function test_specialty_sync_only_audits_changes_and_keeps_removed_history(): void
    {
        $retainedId = DB::table('especialidad')->insertGetId(['nombre' => 'Histórica vinculada', 'activo' => false]);
        $returningId = DB::table('especialidad')->insertGetId(['nombre' => 'Especialidad recuperada']);
        $newId = DB::table('especialidad')->insertGetId(['nombre' => 'Nueva especialidad']);
        foreach ([$retainedId => true, $returningId => false] as $id => $active) {
            DB::table('medico_especialidad')->insert(['medico_id' => $this->doctor()->id, 'especialidad_id' => $id, 'activo' => $active]);
        }
        $before = DB::table('bitacora')->where('entidad', 'medico_especialidad')->max('id');
        $this->actingAs($this->account('recepcion'))->put($this->updateUrl(), $this->payload([
            'especialidad_ids' => [$retainedId, $returningId, $newId],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medico_especialidad', ['medico_id' => $this->doctor()->id, 'especialidad_id' => $this->specialtyId(), 'activo' => false]);
        foreach ([$retainedId, $returningId, $newId] as $id) {
            $this->assertDatabaseHas('medico_especialidad', ['medico_id' => $this->doctor()->id, 'especialidad_id' => $id, 'activo' => true]);
        }
        $events = DB::table('bitacora')->where('entidad', 'medico_especialidad')->where('id', '>', $before)->get();
        $this->assertCount(3, $events);
        $this->assertSame(2, $events->where('accion', 'UPDATE')->count());
        $this->assertSame(1, $events->where('accion', 'INSERT')->count());
        $this->assertSame([$this->account('recepcion')->id], $events->pluck('usuario_id')->unique()->values()->all());
    }

    public function test_future_appointments_still_block_removing_a_specialty_and_roll_back_identity(): void
    {
        $appointmentId = $this->existingAppointment($this->appointmentPayload());
        $inactiveId = DB::table('especialidad')->insertGetId(['nombre' => 'Histórica vinculada', 'activo' => false]);
        DB::table('medico_especialidad')->insert(['medico_id' => $this->doctor()->id, 'especialidad_id' => $inactiveId]);
        $name = $this->account('medico')->nombres;
        $this->actingAs($this->account('recepcion'))->putJson($this->updateUrl(), $this->payload([
            'especialidad_ids' => [$inactiveId], 'nombres' => 'No debe guardarse',
        ]))->assertUnprocessable()->assertJsonValidationErrors('operacion');
        $this->assertDatabaseHas('medico_especialidad', ['medico_id' => $this->doctor()->id, 'especialidad_id' => $this->specialtyId(), 'activo' => true]);
        $this->assertDatabaseHas('usuario', ['id' => $this->account('medico')->id, 'nombres' => $name]);
        $this->assertDatabaseHas('cita', ['id' => $appointmentId, 'estado' => 'Programada']);
    }

    public function test_deactivation_still_requires_a_nonempty_selection(): void
    {
        $this->actingAs($this->account('recepcion'))->putJson($this->updateUrl(), $this->payload([
            'especialidad_ids' => [], 'activo' => '0',
        ]))->assertUnprocessable()->assertJsonValidationErrors('especialidad_ids');
        $this->assertDatabaseHas('medico', ['id' => $this->doctor()->id, 'activo' => true]);
    }

    public static function searches(): array
    {
        return [
            'nombre completo' => ['Dr Uno'], 'espacios' => ["  Dr \t Uno  "], 'mayúsculas' => ['dR uNO'],
            'nombres' => ['Dr'], 'apellidos' => ['Uno'], 'especialidad' => ['Medicina General'],
            'especialidad parcial' => ['mEDICINA'], 'registro' => ['MED-TEST-01'],
            'correo' => ['medico@vida-salud.test'], 'teléfono' => ['70000002'], 'cero' => ['0'],
        ];
    }

    #[DataProvider('searches')]
    public function test_search_finds_the_expected_doctor(string $term): void
    {
        DB::table('usuario')->where('id', $this->account('medico')->id)->update(['nombres' => ' Dr  ', 'apellidos' => '  Uno ']);
        DB::table('especialidad')->where('id', $this->specialtyId())->update(['nombre' => 'Medicina   General']);
        $expected = $this->doctor()->id;
        $this->actingAs($this->account('recepcion'))->get(route('catalog.index', ['module' => 'medicos', 'q' => $term]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$expected]);
    }

    public function test_search_includes_linked_inactive_specialties_but_excludes_removed_links(): void
    {
        $this->actingAs($this->account('recepcion'));
        $this->disableSpecialty();
        $url = route('catalog.index', ['module' => 'medicos', 'q' => 'Medicina General']);
        $expected = $this->doctor()->id;
        $this->get($url)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$expected]);
        DB::table('medico_especialidad')->where('medico_id', $expected)->update(['activo' => false]);
        $this->get($url)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
    }

    public function test_search_paginates_unique_doctors_and_keeps_the_filter(): void
    {
        $account = $this->account('medico');
        $secondSpecialty = DB::table('especialidad')->insertGetId(['nombre' => 'Medicina Interna']);
        $doctors = [$this->doctor()->id];
        for ($index = 0; $index < 16; $index++) {
            $userId = DB::table('usuario')->insertGetId([
                'rol_id' => $account->rol_id, 'nombre_usuario' => 'directorio.'.$index,
                'nombres' => 'Profesional', 'apellidos' => (string) $index, 'password_hash' => $account->password_hash,
            ]);
            $doctors[] = DB::table('medico')->insertGetId(['usuario_id' => $userId, 'registro_profesional' => 'DIRECTORIO-'.$index]);
        }
        foreach ($doctors as $doctorId) {
            foreach ([$this->specialtyId(), $secondSpecialty] as $specialtyId) {
                DB::table('medico_especialidad')->insertOrIgnore(['medico_id' => $doctorId, 'especialidad_id' => $specialtyId]);
            }
        }
        $this->actingAs($this->account('admin'))->get(route('catalog.index', ['module' => 'medicos', 'q' => 'Medicina', 'page' => 2]))
            ->assertOk()->assertViewHas('rows', function ($rows): bool {
                parse_str(parse_url($rows->previousPageUrl(), PHP_URL_QUERY), $query);

                return $rows->total() === 17 && $rows->perPage() === 15 && $rows->currentPage() === 2
                    && $rows->pluck('id')->all() === [$this->doctor()->id + 1, $this->doctor()->id]
                    && ($query['q'] ?? null) === 'Medicina';
            });
    }

    public function test_empty_search_lists_doctors_and_sql_text_does_not_bypass_the_filter(): void
    {
        $this->actingAs($this->account('recepcion'));
        $this->get(route('catalog.index', ['module' => 'medicos', 'q' => '   ']))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        foreach (['Sin coincidencias', "' OR 1=1 --"] as $term) {
            $this->get(route('catalog.index', ['module' => 'medicos', 'q' => $term]))
                ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        }
    }

    public function test_doctor_without_directory_permission_cannot_write_or_search(): void
    {
        $this->actingAs($this->account('medico'))->get(route('catalog.index', ['module' => 'medicos', 'q' => 'Dr Uno']))->assertForbidden();
        $this->putJson($this->updateUrl(), $this->payload(['activo' => '0']))->assertForbidden();
    }
}

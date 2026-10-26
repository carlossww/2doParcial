<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class CatalogCorrectionsTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    private function doctorPayload(?int $userId = null): array
    {
        return [
            'usuario_id' => $userId ?? $this->account('medico')->id,
            'nombres' => 'Camila', 'apellidos' => 'Salcedo', 'correo' => 'camila@vida-salud.test',
            'registro_profesional' => 'MED-TEST-01', 'telefono' => '71112222',
            'especialidad_ids' => [DB::table('especialidad')->value('id')], 'activo' => '1',
        ];
    }

    private function customRole(): int
    {
        $id = DB::table('rol')->insertGetId(['nombre' => 'Auxiliar personalizado']);
        DB::table('rol_permiso')->insert(['rol_id' => $id, 'permiso_id' => DB::table('permiso')->value('id')]);

        return $id;
    }

    public function test_role_confirmation_does_not_delete_and_deletion_is_audited(): void
    {
        $this->seedVidaSalud();
        $id = $this->customRole();
        $actor = $this->account('admin');
        $this->actingAs($actor)->get(route('catalog.roles.confirm-destroy', $id))
            ->assertOk()->assertSee('Auxiliar personalizado')->assertSee('Cancelar');
        $this->assertDatabaseHas('rol', ['id' => $id]);
        $this->delete(route('catalog.roles.destroy', $id))->assertRedirect(route('catalog.index', 'roles'));
        $this->assertDatabaseMissing('rol', ['id' => $id]);
        $this->assertDatabaseMissing('rol_permiso', ['rol_id' => $id]);
        $this->assertSame(1, DB::table('bitacora')->where('entidad', 'rol')->where('accion', 'DELETE')->where('registro_id', $id)->where('usuario_id', $actor->id)->count());
    }

    public static function accountStates(): array
    {
        return ['usuario activo' => [true], 'usuario inactivo' => [false]];
    }

    #[DataProvider('accountStates')]
    public function test_assigned_role_cannot_be_deleted_even_with_inactive_users(bool $active): void
    {
        $this->seedVidaSalud();
        $id = $this->customRole();
        DB::table('usuario')->insert([
            'rol_id' => $id, 'nombre_usuario' => 'auxiliar', 'nombres' => 'Auxiliar', 'apellidos' => 'Prueba',
            'password_hash' => $this->account('admin')->password_hash, 'activo' => $active,
        ]);
        $this->actingAs($this->account('admin'))->delete(route('catalog.roles.destroy', $id))->assertSessionHasErrors('operacion');
        $this->assertDatabaseHas('rol', ['id' => $id]);
        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $id]);
        $this->assertSame(0, DB::table('bitacora')->where('entidad', 'rol')->where('accion', 'DELETE')->where('registro_id', $id)->count());
    }

    public static function protectedRoles(): array
    {
        return array_map(fn ($name) => [$name], ['Administrador', 'Recepcionista', 'Medico', 'Laboratorista', 'Paciente']);
    }

    #[DataProvider('protectedRoles')]
    public function test_system_roles_cannot_be_deleted(string $name): void
    {
        $this->seedVidaSalud();
        $id = DB::table('rol')->where('nombre', $name)->value('id');
        $this->actingAs($this->account('admin'))->delete(route('catalog.roles.destroy', $id))->assertSessionHasErrors('operacion');
        $this->assertDatabaseHas('rol', ['id' => $id]);
    }

    public function test_actor_cannot_delete_their_own_custom_role(): void
    {
        $this->seedVidaSalud();
        $id = $this->customRole();
        $permission = DB::table('permiso')->where('codigo', 'roles.gestionar')->value('id');
        DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $id, 'permiso_id' => $permission]);
        DB::table('usuario')->where('id', $this->account('recepcion')->id)->update(['rol_id' => $id]);
        $this->actingAs($this->account('recepcion'))->delete(route('catalog.roles.destroy', $id))->assertSessionHasErrors('operacion');
        $this->assertDatabaseHas('rol', ['id' => $id]);
    }

    public function test_role_delete_requires_permission_and_a_real_record(): void
    {
        $this->seedVidaSalud();
        $id = $this->customRole();
        $this->actingAs($this->account('recepcion'))->get(route('catalog.roles.confirm-destroy', $id))->assertForbidden();
        $this->delete(route('catalog.roles.destroy', $id))->assertForbidden();
        $this->actingAs($this->account('admin'))->delete(route('catalog.roles.destroy', 999999))->assertNotFound();
        $this->assertDatabaseHas('rol', ['id' => $id]);
    }

    public function test_reception_edits_doctor_identity_and_contact_without_changing_account_access(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('medico');
        $profileId = DB::table('medico')->where('usuario_id', $account->id)->value('id');
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $profileId]), $this->doctorPayload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'nombres' => 'Camila', 'apellidos' => 'Salcedo', 'correo' => 'camila@vida-salud.test', 'rol_id' => $account->rol_id, 'password_hash' => $account->password_hash, 'activo' => true]);
        $this->assertDatabaseHas('medico', ['id' => $profileId, 'telefono' => '71112222']);
        $this->get(route('catalog.index', ['module' => 'medicos', 'q' => 'Salcedo']))->assertOk()->assertSee('Camila')->assertSee('Medicina General')->assertSee('camila@vida-salud.test');
        $this->get(route('catalog.index', ['module' => 'medicos', 'q' => 'Camila']))->assertSee('Salcedo');
        $this->get(route('catalog.index', ['module' => 'medicos', 'q' => 'MED-TEST-01']))->assertSee('Salcedo');
        $this->get(route('catalog.index', ['module' => 'usuarios']))->assertForbidden();
        $this->get(route('catalog.index', ['module' => 'roles']))->assertForbidden();
    }

    public function test_reception_registers_a_doctor_using_an_existing_unassigned_medical_account(): void
    {
        $this->seedVidaSalud();
        $medical = $this->account('medico');
        $accountId = DB::table('usuario')->insertGetId(['rol_id' => $medical->rol_id, 'nombre_usuario' => 'medica.nueva', 'nombres' => 'Nueva', 'apellidos' => 'Doctora', 'password_hash' => $medical->password_hash]);
        $payload = [...$this->doctorPayload($accountId), 'registro_profesional' => 'MED-NEW-02'];
        $this->actingAs($this->account('recepcion'))->get(route('catalog.create', ['module' => 'medicos']))->assertOk()->assertSee('Nueva Doctora');
        $this->post(route('catalog.store', ['module' => 'medicos']), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medico', ['usuario_id' => $accountId, 'registro_profesional' => 'MED-NEW-02']);
        $this->assertDatabaseHas('usuario', ['id' => $accountId, 'nombres' => 'Camila']);
    }

    public static function forbiddenMedicalFields(): array
    {
        return ['rol' => ['rol_id', 1], 'rol nulo' => ['rol_id', null], 'clave' => ['password', 'NuevaClave2026!'], 'clave vacía' => ['password', ''], 'hash' => ['password_hash', 'alterado'], 'permisos' => ['permiso_ids', [1]], 'permisos vacíos' => ['permiso_ids', []], 'vinculo' => ['paciente_id', 1], 'usuario' => ['nombre_usuario', 'otro']];
    }

    public function test_invalid_input_types_produce_validation_errors_without_partial_updates(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('medico');
        $id = DB::table('medico')->where('usuario_id', $account->id)->value('id');
        $this->actingAs($this->account('recepcion'))->putJson(route('catalog.update', ['module' => 'medicos', 'id' => $id]), [...$this->doctorPayload(), 'correo' => ['incorrecto'], 'registro_profesional' => ['incorrecto']])
            ->assertUnprocessable()->assertJsonValidationErrors(['correo', 'registro_profesional']);
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'nombres' => $account->nombres]);
    }

    #[DataProvider('forbiddenMedicalFields')]
    public function test_medical_form_rejects_account_privilege_fields(string $field, mixed $value): void
    {
        $this->seedVidaSalud();
        $account = $this->account('medico');
        $profileId = DB::table('medico')->where('usuario_id', $account->id)->value('id');
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $profileId]), [...$this->doctorPayload(), $field => $value])
            ->assertSessionHasErrors($field);
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'nombres' => $account->nombres, 'rol_id' => $account->rol_id, 'password_hash' => $account->password_hash]);
    }

    public function test_failed_profile_update_rolls_back_changes_to_the_medical_account(): void
    {
        $this->seedVidaSalud();
        $original = $this->account('medico');
        $profileId = DB::table('medico')->where('usuario_id', $original->id)->value('id');
        $otherId = $this->otherDoctor();
        $otherRegistration = DB::table('medico')->where('id', $otherId)->value('registro_profesional');
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $profileId]), [...$this->doctorPayload(), 'registro_profesional' => $otherRegistration])->assertSessionHasErrors('operacion');
        $this->assertDatabaseHas('usuario', ['id' => $original->id, 'nombres' => $original->nombres, 'correo' => $original->correo]);
        $this->assertDatabaseHas('medico', ['id' => $profileId, 'registro_profesional' => 'MED-TEST-01']);
    }

    public function test_doctor_account_cannot_be_transferred(): void
    {
        $this->seedVidaSalud();
        $id = DB::table('medico')->where('usuario_id', $this->account('medico')->id)->value('id');
        $otherId = $this->otherDoctor();
        $otherUser = DB::table('medico')->where('id', $otherId)->value('usuario_id');
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $id]), $this->doctorPayload($otherUser))->assertSessionHasErrors('usuario_id');
        $this->assertDatabaseHas('medico', ['id' => $id, 'usuario_id' => $this->account('medico')->id]);
    }

    public function test_reception_can_deactivate_profile_after_its_account_was_disabled(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('medico');
        $id = DB::table('medico')->where('usuario_id', $account->id)->value('id');
        DB::table('usuario')->where('id', $account->id)->update(['activo' => false]);
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $id]), [...$this->doctorPayload(), 'activo' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medico', ['id' => $id, 'activo' => false]);
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'activo' => false]);
        $this->put(route('catalog.update', ['module' => 'medicos', 'id' => $id]), $this->doctorPayload())->assertSessionHasErrors('usuario_id');
    }

    public function test_deactivation_with_future_appointment_is_rejected_and_identity_changes_roll_back(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $appointmentId = $this->existingAppointment($payload);
        $account = $this->account('medico');
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $payload['medico_id']]), [...$this->doctorPayload(), 'activo' => '0'])->assertSessionHasErrors('operacion');
        $this->assertDatabaseHas('medico', ['id' => $payload['medico_id'], 'activo' => true]);
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'nombres' => $account->nombres]);
        $this->assertDatabaseHas('cita', ['id' => $appointmentId, 'estado' => 'Programada']);
    }

    public function test_doctor_and_specialty_can_be_deactivated_without_erasing_their_history(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $id = $this->existingAppointment($payload);
        DB::table('cita')->where('id', $id)->update(['estado' => 'Cancelada', 'motivo_cancelacion' => 'Preparación de baja']);
        $this->actingAs($this->account('recepcion'))->put(route('catalog.update', ['module' => 'medicos', 'id' => $payload['medico_id']]), [...$this->doctorPayload(), 'activo' => '0'])->assertSessionHasNoErrors();
        $this->put(route('catalog.update', ['module' => 'especialidades', 'id' => $payload['especialidad_id']]), ['nombre' => 'Medicina General', 'descripcion' => 'Catálogo histórico', 'activo' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medico', ['id' => $payload['medico_id'], 'activo' => false]);
        $this->assertDatabaseHas('especialidad', ['id' => $payload['especialidad_id'], 'activo' => false]);
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Cancelada']);
        $this->assertGreaterThanOrEqual(2, DB::table('evento_cita')->where('cita_id', $id)->count());
    }

    public function test_existing_reception_permissions_are_upgraded_once_without_replacing_custom_assignments(): void
    {
        $this->seedVidaSalud();
        $roleId = $this->account('recepcion')->rol_id;
        $newIds = DB::table('permiso')->whereIn('codigo', ['medicos.gestionar', 'especialidades.gestionar'])->pluck('id');
        DB::table('rol_permiso')->where('rol_id', $roleId)->whereIn('permiso_id', $newIds)->delete();
        $custom = DB::table('permiso')->insertGetId(['codigo' => 'personalizado.lectura', 'descripcion' => 'Personalizado']);
        DB::table('rol_permiso')->insert(['rol_id' => $roleId, 'permiso_id' => $custom]);
        $before = DB::table('rol_permiso')->where('rol_id', $roleId)->count();
        $migration = require database_path('migrations/2026_10_25_000001_grant_reception_directory_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before + 2, DB::table('rol_permiso')->where('rol_id', $roleId)->count());
        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $roleId, 'permiso_id' => $custom]);
        DB::table('rol_permiso')->where('rol_id', $roleId)->whereIn('permiso_id', $newIds)->delete();
        $this->seed(RolePermissionSeeder::class);
        $this->assertSame($before, DB::table('rol_permiso')->where('rol_id', $roleId)->count());
    }
}

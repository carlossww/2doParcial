<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class PatientAccountManagementTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    private function payload(User $account): array
    {
        return [
            'rol_id' => $account->rol_id, 'nombre_usuario' => $account->nombre_usuario,
            'nombres' => $account->nombres, 'apellidos' => $account->apellidos,
            'correo' => $account->correo, 'password' => '', 'activo' => '1',
        ];
    }

    private function unlinkedAccount(): User
    {
        $source = $this->account('paciente');
        $id = DB::table('usuario')->insertGetId([
            'rol_id' => $source->rol_id, 'nombre_usuario' => 'paciente.dos',
            'nombres' => 'Cuenta', 'apellidos' => 'Dos', 'password_hash' => $source->password_hash,
        ]);

        return User::findOrFail($id);
    }

    public function test_ordinary_edit_preserves_the_verified_patient_identity_and_password(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $before = $account->only(['paciente_id', 'paciente_verificado_por', 'paciente_verificado_en', 'password_hash']);
        $this->actingAs($this->account('admin'))->put(route('catalog.update', ['module' => 'usuarios', 'id' => $account->id]), [
            ...$this->payload($account), 'telefono' => 'dato no utilizado', 'nombres' => 'Nombre actualizado',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals($before, $account->fresh()->only(array_keys($before)));
        $this->assertSame('Nombre actualizado', $account->fresh()->nombres);
    }

    public static function protectedFields(): array
    {
        return [['paciente_id', null], ['paciente_id', 99999], ['paciente_verificado_por', null], ['paciente_verificado_en', '2030-01-01']];
    }

    #[DataProvider('protectedFields')]
    public function test_ordinary_edit_rejects_patient_identity_fields(string $field, mixed $value): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $this->actingAs($this->account('admin'))->putJson(route('catalog.update', ['module' => 'usuarios', 'id' => $account->id]), [
            ...$this->payload($account), $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($account->paciente_id, $account->fresh()->paciente_id);
    }

    public function test_role_change_requires_explicit_unlinking_first(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $this->actingAs($this->account('admin'))->putJson(route('catalog.update', ['module' => 'usuarios', 'id' => $account->id]), [
            ...$this->payload($account), 'rol_id' => $this->account('laboratorio')->rol_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('rol_id');
        $this->assertSame($account->rol_id, $account->fresh()->rol_id);
        $this->assertSame($account->paciente_id, $account->fresh()->paciente_id);
    }

    public function test_linking_is_explicit_verified_and_audited(): void
    {
        $this->seedVidaSalud();
        $account = $this->unlinkedAccount();
        $patient = $this->otherPatient();
        $admin = $this->account('admin');
        $auditCount = DB::table('bitacora')->count();
        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '192.0.2.51'])
            ->put(route('catalog.users.patient.update', $account->id), ['paciente_id' => $patient, 'confirmar_vinculo' => '1'])
            ->assertRedirect(route('catalog.users.patient.edit', $account->id))->assertSessionHasNoErrors();
        $after = $account->fresh();
        $this->assertSame($patient, (int) $after->paciente_id);
        $this->assertSame($admin->id, (int) $after->paciente_verificado_por);
        $this->assertNotNull($after->paciente_verificado_en);
        $this->assertSame($auditCount + 1, DB::table('bitacora')->count());
        $this->assertDatabaseHas('bitacora', ['entidad' => 'usuario', 'registro_id' => $account->id, 'accion' => 'UPDATE', 'usuario_id' => $admin->id, 'direccion_ip' => '192.0.2.51']);
    }

    public function test_linking_requires_confirmation_and_the_explicit_patient_field(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $this->actingAs($this->account('admin'))->putJson(route('catalog.users.patient.update', $account->id), ['paciente_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmar_vinculo');
        $this->putJson(route('catalog.users.patient.update', $account->id), ['confirmar_vinculo' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('paciente_id');
        $this->assertSame($account->paciente_id, $account->fresh()->paciente_id);
    }

    public function test_confirmed_unlink_keeps_the_patient_and_its_history(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $appointment = $this->historicalAppointment();
        $this->actingAs($this->account('admin'))->put(route('catalog.users.patient.update', $account->id), ['paciente_id' => '', 'confirmar_vinculo' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('usuario', ['id' => $account->id, 'paciente_id' => null, 'paciente_verificado_por' => null, 'paciente_verificado_en' => null]);
        $this->assertDatabaseHas('paciente', ['id' => $account->paciente_id]);
        $this->assertDatabaseHas('cita', ['id' => $appointment, 'paciente_id' => $account->paciente_id]);
    }

    public function test_unchanged_inactive_patient_link_preserves_verification_without_extra_audit(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        DB::table('paciente')->where('id', $account->paciente_id)->update(['activo' => false]);
        $other = $this->otherPatient();
        $count = DB::table('bitacora')->count();
        $this->actingAs($this->account('admin'))->get(route('catalog.users.patient.edit', $account->id))
            ->assertOk()->assertSee('(inactivo)')->assertViewHas('patients', fn ($options) => isset($options[$account->paciente_id], $options[$other]));
        $this->put(route('catalog.users.patient.update', $account->id), ['paciente_id' => $account->paciente_id, 'confirmar_vinculo' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($account->paciente_verificado_en, $account->fresh()->paciente_verificado_en);
        $this->assertSame($count, DB::table('bitacora')->count());
    }

    public function test_cannot_assign_a_patient_already_linked_to_another_account(): void
    {
        $this->seedVidaSalud();
        $account = $this->unlinkedAccount();
        $owner = $this->account('paciente');
        $this->actingAs($this->account('admin'))->get(route('catalog.users.patient.edit', $account->id))
            ->assertOk()->assertViewHas('patients', fn ($options) => ! isset($options[$owner->paciente_id]));
        $this->putJson(route('catalog.users.patient.update', $account->id), ['paciente_id' => $owner->paciente_id, 'confirmar_vinculo' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('paciente_id');
        $this->assertNull($account->fresh()->paciente_id);
        $this->assertSame($owner->paciente_id, $owner->fresh()->paciente_id);
    }

    public function test_cannot_assign_a_new_inactive_patient_or_forge_verification(): void
    {
        $this->seedVidaSalud();
        $account = $this->unlinkedAccount();
        $patient = $this->otherPatient();
        DB::table('paciente')->where('id', $patient)->update(['activo' => false]);
        $this->actingAs($this->account('admin'))->putJson(route('catalog.users.patient.update', $account->id), ['paciente_id' => $patient, 'confirmar_vinculo' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('paciente_id');
        $this->putJson(route('catalog.users.patient.update', $account->id), ['paciente_id' => null, 'confirmar_vinculo' => '1', 'paciente_verificado_por' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('paciente_verificado_por');
        $this->assertNull($account->fresh()->paciente_id);
    }

    public static function unauthorizedAccounts(): array
    {
        return [['recepcion'], ['medico'], ['laboratorio'], ['paciente']];
    }

    #[DataProvider('unauthorizedAccounts')]
    public function test_only_account_management_can_read_or_update_links(string $login): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $this->actingAs($this->account($login))->get(route('catalog.users.patient.edit', $account->id))->assertForbidden();
        $this->putJson(route('catalog.users.patient.update', $account->id), ['paciente_id' => null, 'confirmar_vinculo' => '1'])->assertForbidden();
        $this->assertSame($account->paciente_id, $account->fresh()->paciente_id);
    }

    public function test_missing_or_nonpatient_accounts_and_guests_are_rejected(): void
    {
        $this->seedVidaSalud();
        $this->get(route('catalog.users.patient.edit', $this->account('paciente')->id))->assertRedirectToRoute('login');
        $this->actingAs($this->account('admin'))->get(route('catalog.users.patient.edit', 999999))->assertNotFound();
        $this->get(route('catalog.users.patient.edit', $this->account('medico')->id))->assertNotFound();
        $this->putJson(route('catalog.users.patient.update', $this->account('medico')->id), ['paciente_id' => null, 'confirmar_vinculo' => '1'])->assertNotFound();
    }

    public function test_new_patient_account_can_be_linked_from_its_list_action(): void
    {
        $this->seedVidaSalud();
        $patient = $this->otherPatient();
        $source = $this->account('paciente');
        $this->actingAs($this->account('admin'))->post(route('catalog.store', 'usuarios'), [
            ...$this->payload($source), 'nombre_usuario' => 'paciente.nuevo', 'correo' => 'nuevo@example.test', 'password' => 'PruebaPaciente2026!',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $account = $this->account('paciente.nuevo');
        $this->assertNull($account->paciente_id);
        $this->get(route('catalog.index', 'usuarios'))->assertOk()->assertSee(route('catalog.users.patient.edit', $account->id), false);
        $this->put(route('catalog.users.patient.update', $account->id), ['paciente_id' => $patient, 'confirmar_vinculo' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($patient, (int) $account->fresh()->paciente_id);
    }
}

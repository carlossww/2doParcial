<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class PortalRegistrationTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    public function test_public_registration_creates_a_pending_inactive_account_without_linking_the_claimed_patient(): void
    {
        $this->seedVidaSalud();
        $patientCount = DB::table('paciente')->count();

        $response = $this->post(route('register'), [
            'nombre_usuario' => 'ana.solicitud', 'nombres' => 'Ana', 'apellidos' => 'Prueba',
            'correo' => 'solicitud@example.test', 'ci' => ' test-001 ', 'fecha_nacimiento' => '1995-05-15',
            'telefono' => '70000001', 'password' => 'Solicitud2026!Segura', 'password_confirmation' => 'Solicitud2026!Segura',
            'activo' => true, 'paciente_id' => $this->account('paciente')->paciente_id,
            'rol_id' => $this->account('admin')->rol_id,
        ]);

        $response->assertRedirectToRoute('login')->assertSessionHasNoErrors();
        $user = $this->account('ana.solicitud');
        $this->assertDatabaseHas('usuario', ['id' => $user->getKey(), 'activo' => false, 'paciente_id' => null, 'rol_id' => $this->account('paciente')->rol_id]);
        $this->assertDatabaseHas('solicitud_portal', ['usuario_id' => $user->getKey(), 'estado' => 'Pendiente', 'ci_solicitado' => 'TEST-001']);
        $this->assertDatabaseCount('paciente', $patientCount);
        $this->assertTrue(Hash::check('Solicitud2026!Segura', $user->password_hash));
        $this->assertGuest();
    }

    public function test_reception_approves_a_matching_unlinked_patient_only_with_verified_identity(): void
    {
        $this->seedVidaSalud();
        $patientId = $this->otherPatient();
        $pending = $this->pendingRequest('TEST-002', '1992-04-15');

        $response = $this->actingAs($this->account('recepcion'))->post(route('portal.verify', ['solicitud' => $pending['request']]), [
            'action' => 'aprobar', 'paciente_id' => $patientId, 'identidad_verificada' => '1',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('usuario', ['id' => $pending['user'], 'activo' => true, 'paciente_id' => $patientId, 'paciente_verificado_por' => $this->account('recepcion')->getKey()]);
        $this->assertDatabaseHas('solicitud_portal', ['id' => $pending['request'], 'estado' => 'Aprobada']);
    }

    public function test_verification_does_not_take_over_a_patient_who_already_has_an_account(): void
    {
        $this->seedVidaSalud();
        $patientAccount = $this->account('paciente');
        $pending = $this->pendingRequest('TEST-001', '1995-05-15');

        $response = $this->actingAs($this->account('recepcion'))->postJson(route('portal.verify', ['solicitud' => $pending['request']]), [
            'action' => 'aprobar', 'paciente_id' => $patientAccount->paciente_id, 'identidad_verificada' => '1',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('usuario', ['id' => $pending['user'], 'activo' => false, 'paciente_id' => null]);
        $this->assertDatabaseHas('usuario', ['id' => $patientAccount->getKey(), 'paciente_id' => $patientAccount->paciente_id, 'activo' => true]);
        $this->assertDatabaseHas('solicitud_portal', ['id' => $pending['request'], 'estado' => 'Pendiente']);
    }

    /** @return array{user: int, request: int} */
    private function pendingRequest(string $ci, string $birthDate): array
    {
        $user = User::factory()->withRole('Paciente')->create(['activo' => false]);
        $requestId = DB::table('solicitud_portal')->insertGetId([
            'usuario_id' => $user->getKey(), 'ci_solicitado' => $ci, 'fecha_nacimiento' => $birthDate, 'telefono' => '70000009',
        ]);

        return ['user' => (int) $user->getKey(), 'request' => (int) $requestId];
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    public function test_guest_is_redirected_to_login_from_home(): void
    {
        $this->get(route('home'))->assertRedirectToRoute('login');
    }

    /** @return array<string, array{string}> */
    public static function demoAccounts(): array
    {
        return [
            'administrador' => ['admin'], 'recepción' => ['recepcion'],
            'médico' => ['medico'], 'laboratorio' => ['laboratorio'],
            'paciente verificado' => ['paciente'],
        ];
    }

    #[DataProvider('demoAccounts')]
    public function test_active_account_can_sign_in_with_its_login(string $login): void
    {
        $this->seedVidaSalud();

        $response = $this->post(route('login'), [
            'login' => $login, 'password' => env('DEMO_PASSWORD', 'VidaSalud2026!'),
        ]);

        $response->assertRedirectToRoute('home');
        $this->assertAuthenticatedAs($this->account($login));
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $this->seedVidaSalud();

        $response = $this->from(route('login'))->post(route('login'), [
            'login' => 'admin', 'password' => 'clave-incorrecta',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $this->seedVidaSalud();
        DB::table('usuario')->where('id', $this->account('medico')->getKey())->update(['activo' => false]);

        $response = $this->post(route('login'), [
            'login' => 'medico', 'password' => env('DEMO_PASSWORD', 'VidaSalud2026!'),
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $this->seedVidaSalud();
        $actor = $this->account('recepcion');

        $response = $this->actingAs($actor)->withSession(['private_marker' => 'sesion-anterior'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.15'])->post(route('logout'));

        $response->assertRedirectToRoute('login')->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertDatabaseHas('bitacora', [
            'usuario_id' => $actor->id, 'accion' => 'SALIDA', 'entidad' => 'usuario',
            'registro_id' => $actor->id, 'descripcion' => 'Cierre de sesión', 'direccion_ip' => '203.0.113.15',
        ]);
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'SALIDA')->count());
        $this->get(route('home'))->assertRedirectToRoute('login');
    }

    public function test_logout_confirmation_and_cancel_preserve_the_session_without_an_exit_event(): void
    {
        $this->seedVidaSalud();
        $actor = $this->account('recepcion');

        $this->actingAs($actor)->get(route('logout.confirm'))->assertOk()
            ->assertSee('¿Deseas cerrar tu sesión?')->assertSee('Sí, cerrar sesión')
            ->assertSee('Cancelar')->assertSee('method="POST"', false)
            ->assertSee('name="_token"', false)->assertSee(route('logout'), false);
        $this->get(route('home'))->assertOk();

        $this->assertAuthenticatedAs($actor);
        $this->assertSame(0, DB::table('bitacora')->where('accion', 'SALIDA')->count());
    }

    public function test_json_logout_can_be_retried_after_completion_without_duplicate_exit_events(): void
    {
        $this->seedVidaSalud();

        $this->actingAs($this->account('recepcion'))->postJson(route('logout'))
            ->assertOk()->assertExactJson(['redirect' => route('login')]);
        $this->assertGuest();

        $this->postJson(route('logout'))->assertUnauthorized();
        $this->get(route('logout.confirm'))->assertRedirectToRoute('login');
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'SALIDA')->count());
    }

    public function test_guest_cannot_open_the_logout_confirmation(): void
    {
        $this->get(route('logout.confirm'))->assertRedirectToRoute('login');
    }

    public function test_requests_already_authenticated_for_the_same_session_record_only_one_exit(): void
    {
        $this->seedVidaSalud();
        $actor = $this->account('recepcion');
        $sessionId = Str::random(40);
        $key = 'logout:'.hash('sha256', $sessionId);

        // actingAs representa dos peticiones que ya habían resuelto al usuario
        // antes de que la primera terminara. Ambas conservan la misma cookie.
        $this->withCredentials()->withCookie(config('session.cookie'), $sessionId)->actingAs($actor)
            ->postJson(route('logout'), ['clave_operacion' => 'clave-del-cliente'])->assertOk();
        $this->assertDatabaseHas('bitacora', ['accion' => 'SALIDA', 'clave_operacion' => $key]);
        $this->actingAs($actor)->postJson(route('logout'), ['clave_operacion' => 'otra-clave'])->assertOk();

        $this->assertSame(1, DB::table('bitacora')->where('accion', 'SALIDA')->count());
        $this->assertDatabaseHas('bitacora', ['accion' => 'SALIDA', 'clave_operacion' => $key]);
        $this->assertDatabaseMissing('bitacora', ['clave_operacion' => 'clave-del-cliente']);

        $this->withCookie(config('session.cookie'), Str::random(40))->actingAs($actor)
            ->postJson(route('logout'))->assertOk();
        $this->assertSame(2, DB::table('bitacora')->where('accion', 'SALIDA')->count());
    }

    public function test_operation_key_uniqueness_is_enforced_by_postgresql(): void
    {
        $this->seedVidaSalud();
        $entry = ['accion' => 'SALIDA', 'clave_operacion' => 'logout:prueba-restriccion'];
        DB::table('bitacora')->insert($entry);

        try {
            DB::transaction(fn () => DB::table('bitacora')->insert($entry));
            $this->fail('PostgreSQL debe rechazar claves de operación duplicadas.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        }

        $this->assertSame(1, DB::table('bitacora')->where('clave_operacion', $entry['clave_operacion'])->count());
    }

    public function test_failed_audit_transaction_preserves_authentication_and_rolls_back_exit(): void
    {
        $this->seedVidaSalud();
        $actor = $this->account('recepcion');
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower($query->sql), 'insert')
                && str_contains($query->sql, 'bitacora') && in_array('SALIDA', $query->bindings, true)) {
                throw new RuntimeException('Fallo simulado después de insertar la auditoría.');
            }
        });

        try {
            $this->actingAs($actor)->withSession(['private_marker' => 'sesion-preservada'])->postJson(route('logout'));
            $this->fail('El fallo de auditoría debe impedir finalizar la sesión.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado después de insertar la auditoría.', $exception->getMessage());
        }

        $this->assertAuthenticatedAs($actor);
        $this->assertSame('sesion-preservada', session('private_marker'));
        $this->assertSame(0, DB::table('bitacora')->where('accion', 'SALIDA')->count());
    }

    public function test_idempotency_migration_cannot_be_reverted_after_recording_operation_keys(): void
    {
        DB::table('bitacora')->insert(['accion' => 'SALIDA', 'clave_operacion' => 'logout:proteger-historial']);
        $migration = require database_path('migrations/2026_10_25_000003_make_logout_audit_idempotent.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se retira la protección de idempotencia');
        $migration->down();
    }
}

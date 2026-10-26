<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    private function event(?int $userId, array $overrides = []): int
    {
        return DB::table('bitacora')->insertGetId([
            'usuario_id' => $userId,
            'fecha' => '2026-01-15 12:00:00-04',
            'accion' => 'PRUEBA_LECTURA',
            'entidad' => 'auditoria_prueba',
            'registro_id' => 42,
            'descripcion' => 'Evento de prueba',
            'direccion_ip' => '192.0.2.10',
            ...$overrides,
        ]);
    }

    public function test_admin_can_combine_filters_without_including_other_events(): void
    {
        $this->seedVidaSalud();
        $admin = $this->account('admin');
        $expected = $this->event($admin->id);
        $this->event($this->account('recepcion')->id);
        $this->event($admin->id, ['accion' => 'PRUEBA_CAMBIO']);
        $this->event($admin->id, ['entidad' => 'otra_entidad']);
        $this->event($admin->id, ['fecha' => '2026-01-16 00:00:00-04']);

        $this->actingAs($admin)->get(route('auditoria.index', [
            'usuario_id' => $admin->id, 'accion' => 'PRUEBA_LECTURA',
            'entidad' => 'auditoria_prueba', 'desde' => '2026-01-15', 'hasta' => '2026-01-15',
        ]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$expected]);
    }

    public function test_system_filter_only_returns_events_without_a_user(): void
    {
        $this->seedVidaSalud();
        $expected = $this->event(null);
        $this->event($this->account('admin')->id);

        $this->actingAs($this->account('admin'))->get(route('auditoria.index', [
            'usuario_id' => 'sistema', 'entidad' => 'auditoria_prueba',
        ]))->assertOk()->assertSee('Sistema (sin usuario)')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$expected]);
    }

    public function test_date_filter_includes_the_last_instant_of_the_final_day(): void
    {
        $this->seedVidaSalud();
        $admin = $this->account('admin');
        $first = $this->event($admin->id, ['fecha' => '2026-01-15 00:00:00-04']);
        $last = $this->event($admin->id, ['fecha' => '2026-01-15 23:59:59.999999-04']);
        $this->event($admin->id, ['fecha' => '2026-01-14 23:59:59.999999-04']);
        $this->event($admin->id, ['fecha' => '2026-01-16 00:00:00-04']);

        $this->actingAs($admin)->get(route('auditoria.index', [
            'desde' => '2026-01-15', 'hasta' => '2026-01-15', 'entidad' => 'auditoria_prueba',
        ]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$last, $first]);
    }

    public function test_final_date_can_be_used_without_a_start_date(): void
    {
        $this->seedVidaSalud();
        $expected = $this->event($this->account('admin')->id);
        $this->event(null, ['fecha' => '2026-01-16 00:00:00-04']);

        $this->actingAs($this->account('admin'))->get(route('auditoria.index', [
            'hasta' => '2026-01-15', 'entidad' => 'auditoria_prueba',
        ]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$expected]);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->seedVidaSalud();
        $this->actingAs($this->account('admin'))->getJson(route('auditoria.index', [
            'usuario_id' => '1 OR 1=1', 'accion' => 'ACCION_INEXISTENTE',
            'desde' => '2026-01-20', 'hasta' => '2026-01-15', 'page' => '0',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['usuario_id', 'accion', 'hasta', 'page']);
    }

    public function test_year_zero_is_rejected_before_querying_postgresql_dates(): void
    {
        $this->seedVidaSalud();

        $this->actingAs($this->account('admin'))->getJson(route('auditoria.index', [
            'desde' => '0000-01-01', 'hasta' => '0000-01-02',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['desde', 'hasta']);
    }

    public function test_pagination_and_detail_preserve_only_the_validated_filters(): void
    {
        $this->seedVidaSalud();
        $admin = $this->account('admin');
        $ids = [];
        for ($i = 0; $i < 31; $i++) {
            $ids[] = $this->event($admin->id);
        }
        $filters = [
            'usuario_id' => (string) $admin->id, 'accion' => 'PRUEBA_LECTURA',
            'entidad' => 'auditoria_prueba', 'desde' => '2026-01-15', 'hasta' => '2026-01-15', 'page' => '2',
        ];

        $response = $this->actingAs($admin)->get(route('auditoria.index', $filters + ['return' => 'https://example.invalid']));
        $response->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 31 && $rows->count() === 1)
            ->assertDontSee('example.invalid');
        preg_match_all('/href="([^"]+)"/', $response->getContent(), $links);
        $detailUrl = collect($links[1])->map(fn ($link) => html_entity_decode($link))
            ->first(fn ($link) => parse_url($link, PHP_URL_PATH) === '/bitacora/'.$ids[0]);
        $this->assertNotNull($detailUrl);
        parse_str(parse_url($detailUrl, PHP_URL_QUERY), $detailFilters);
        $this->assertEquals($filters, $detailFilters);

        $this->get(route('auditoria.show', ['id' => $ids[0], ...$filters, 'return' => 'https://example.invalid']))
            ->assertOk()->assertDontSee('example.invalid')->assertViewHas('backUrl', function ($url) use ($filters) {
                parse_str(parse_url($url, PHP_URL_QUERY), $backFilters);

                return parse_url($url, PHP_URL_PATH) === '/bitacora' && $backFilters == $filters;
            });
    }

    public function test_detail_shows_all_fields_escaped_without_changing_the_log(): void
    {
        $this->seedVidaSalud();
        $id = $this->event(null, ['descripcion' => '<script>alert(1)</script>']);
        $before = DB::table('bitacora')->orderBy('id')->get()->toJson();

        $this->actingAs($this->account('admin'))->get(route('auditoria.show', ['id' => $id]))
            ->assertOk()->assertSee('Sistema')->assertSee('15/01/2026 · 12:00:00')
            ->assertSee('PRUEBA_LECTURA')->assertSee('auditoria_prueba')->assertSee('42')->assertSee('192.0.2.10')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->assertSame($before, DB::table('bitacora')->orderBy('id')->get()->toJson());
    }

    public function test_missing_event_returns_404(): void
    {
        $this->seedVidaSalud();
        $this->actingAs($this->account('admin'))->get(route('auditoria.show', ['id' => 999999999]))->assertNotFound();
    }

    public static function unauthorizedRoles(): array
    {
        return [['recepcion'], ['medico'], ['laboratorio'], ['paciente']];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_role_without_permission_cannot_read_list_or_detail(string $login): void
    {
        $this->seedVidaSalud();
        $id = $this->event(null);
        $this->actingAs($this->account($login))->get(route('auditoria.index'))->assertForbidden();
        $this->get(route('auditoria.show', ['id' => $id]))->assertForbidden();
    }

    public function test_guest_cannot_read_audit_detail(): void
    {
        $this->seedVidaSalud();
        $id = $this->event(null);
        $this->get(route('auditoria.show', ['id' => $id]))->assertRedirectToRoute('login');
    }
}

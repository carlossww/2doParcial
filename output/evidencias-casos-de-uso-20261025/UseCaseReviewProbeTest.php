<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

/** Reproducciones de revisión: describen defectos actuales, no aceptación funcional. */
class UseCaseReviewProbeTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    private function evidence(string $name, array $result): void
    {
        file_put_contents(__DIR__.'/reproducciones.ndjson', json_encode([
            'escenario' => $name,
            'base' => DB::selectOne('select current_database() as name')->name,
            ...$result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);
    }

    public function test_probe_schedule_search_accepts_times_but_does_not_search_doctor_names(): void
    {
        $this->seedVidaSalud();
        $this->actingAs($this->account('admin'));
        $this->get(route('catalog.index', ['module' => 'horarios']))->assertOk();
        $response = $this->get(route('catalog.index', ['module' => 'horarios', 'q' => '09:00']));
        $response->assertOk();
        $byDoctor = $this->get(route('catalog.index', ['module' => 'horarios', 'q' => 'Dr Uno']));
        $byDoctor->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        $this->evidence('CU18 búsqueda de horarios', [
            'sin_filtro' => 200,
            'busqueda_por_hora' => $response->status(),
            'busqueda_por_medico' => $byDoctor->status(),
            'filas_al_buscar_medico_existente' => 0,
        ]);
    }

    public function test_probe_editing_patient_account_removes_its_verified_link(): void
    {
        $this->seedVidaSalud();
        $account = $this->account('paciente');
        $this->assertNotNull($account->paciente_id);
        $this->assertNotNull($account->paciente_verificado_en);
        $this->actingAs($this->account('admin'));
        $this->get(route('catalog.edit', ['module' => 'usuarios', 'id' => $account->id]))
            ->assertOk()->assertDontSee('name="paciente_id"', false);
        $response = $this->put(route('catalog.update', ['module' => 'usuarios', 'id' => $account->id]), [
            'rol_id' => $account->rol_id,
            'nombre_usuario' => $account->nombre_usuario,
            'password' => '',
            'nombres' => $account->nombres,
            'apellidos' => $account->apellidos,
            'correo' => $account->correo,
            'activo' => '1',
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $after = $account->fresh();
        $this->evidence('CU3 edición de cuenta de paciente sin cambiar sus datos', [
            'estado_http' => $response->status(),
            'vinculo_antes' => $account->paciente_id !== null,
            'vinculo_despues' => $after->paciente_id !== null,
            'verificacion_despues' => $after->paciente_verificado_en !== null,
        ]);
        $this->assertNull($after->paciente_id);
        $this->assertNull($after->paciente_verificado_por);
        $this->assertNull($after->paciente_verificado_en);
    }

    public function test_probe_patient_account_creation_form_has_no_patient_link_field(): void
    {
        $this->seedVidaSalud();
        $this->actingAs($this->account('admin'));
        $this->get(route('catalog.create', ['module' => 'usuarios']))
            ->assertOk()->assertDontSee('name="paciente_id"', false);
        $source = $this->account('paciente');
        $this->post(route('catalog.store', ['module' => 'usuarios']), [
            'rol_id' => $source->rol_id,
            'nombre_usuario' => 'paciente.revision',
            'password' => 'RevisionFicticia2026!',
            'nombres' => 'Cuenta',
            'apellidos' => 'Revision',
            'correo' => 'revision@example.test',
            'activo' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $created = $this->account('paciente.revision');
        $this->evidence('Prerrequisito CU17 creación de cuenta de paciente desde Usuarios', [
            'campo_vinculo_en_formulario' => false,
            'cuenta_creada' => true,
            'vinculo_verificado_creado' => $created->paciente_id !== null,
        ]);
        $this->assertNull($created->paciente_id);
    }

    public function test_probe_pending_modules_have_no_web_routes(): void
    {
        $this->seedVidaSalud();
        $results = [];
        foreach ([
            ['recepcion', '/agenda'],
            ['medico', '/historia'],
            ['medico', '/atencion/1'],
            ['laboratorio', '/laboratorio/ordenes'],
            ['paciente', '/portal/reservar'],
            ['admin', '/reportes'],
        ] as [$login, $url]) {
            $response = $this->actingAs($this->account($login))->get($url);
            $results[$url] = $response->status();
            $response->assertNotFound();
        }
        $this->evidence('Rutas de módulos pendientes', ['resultados' => $results]);
    }
}

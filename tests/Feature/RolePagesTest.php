<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class RolePagesTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    /** @return array<string, array{string, string, array<string, string>, string}> */
    public static function allowedPages(): array
    {
        $pages = [];
        foreach (['admin', 'recepcion', 'medico', 'laboratorio', 'paciente'] as $login) {
            $pages[$login.' inicio'] = [$login, 'home', [], 'none'];
        }
        foreach (['pacientes', 'medicos', 'especialidades', 'horarios', 'usuarios', 'roles', 'analisis'] as $module) {
            foreach (['index', 'create', 'edit'] as $action) {
                $pages['admin '.$module.' '.$action] = ['admin', 'catalog.'.$action, ['module' => $module], $action === 'edit' ? 'catalog' : 'none'];
            }
        }
        foreach (['pacientes', 'medicos', 'especialidades', 'horarios'] as $module) {
            $pages['recepcion '.$module] = ['recepcion', 'catalog.index', ['module' => $module], 'none'];
        }
        foreach (['create', 'edit'] as $action) {
            $pages['recepcion pacientes '.$action] = ['recepcion', 'catalog.'.$action, ['module' => 'pacientes'], $action === 'edit' ? 'catalog' : 'none'];
        }
        foreach (['admin', 'recepcion', 'medico', 'paciente'] as $login) {
            foreach (['index', 'show'] as $action) {
                $pages[$login.' agenda '.$action] = [$login, 'agenda.'.$action, [], $action === 'show' ? 'appointment' : 'none'];
            }
        }
        foreach (['admin', 'recepcion', 'paciente'] as $login) {
            $pages[$login.' reservar'] = [$login, 'agenda.create', [], 'none'];
        }
        foreach (['admin', 'recepcion'] as $login) {
            foreach (['citas', 'pacientes'] as $type) {
                $pages[$login.' reporte '.$type] = [$login, 'reportes.index', ['tipo' => $type], 'none'];
            }
            $pages[$login.' verificar portal'] = [$login, 'portal.requests', [], 'none'];
        }
        $pages['admin bitacora'] = ['admin', 'auditoria.index', [], 'none'];
        $pages['medico atencion'] = ['medico', 'clinica.index', [], 'none'];
        $pages['medico consulta'] = ['medico', 'clinica.show', [], 'consultation'];
        $pages['medico reporte'] = ['medico', 'reportes.index', ['tipo' => 'atenciones'], 'none'];
        foreach (['medico', 'laboratorio'] as $login) {
            $pages[$login.' laboratorio'] = [$login, 'laboratorio.index', [], 'none'];
            $pages[$login.' orden'] = [$login, 'laboratorio.show', [], 'order'];
        }
        $pages['laboratorio reporte'] = ['laboratorio', 'reportes.index', ['tipo' => 'ordenes'], 'none'];
        $pages['paciente portal'] = ['paciente', 'portal.index', [], 'none'];
        $pages['paciente perfil'] = ['paciente', 'portal.profile', [], 'none'];

        return $pages;
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('allowedPages')]
    public function test_allowed_role_page_renders_its_complete_html(string $login, string $routeName, array $parameters, string $fixture): void
    {
        $this->seedVidaSalud();
        if ($fixture === 'catalog') {
            $table = match ($parameters['module']) {
                'pacientes' => 'paciente', 'medicos' => 'medico', 'especialidades' => 'especialidad',
                'horarios' => 'horario', 'usuarios' => 'usuario', 'roles' => 'rol', 'analisis' => 'analisis_laboratorio',
            };
            $parameters['id'] = DB::table($table)->orderBy('id')->value('id');
        } elseif ($fixture === 'appointment') {
            $parameters['cita'] = $this->existingAppointment($this->appointmentPayload());
        } elseif ($fixture === 'consultation') {
            $order = $this->laboratoryOrder();
            $consultationId = DB::table('orden_laboratorio')->where('id', $order['order'])->value('consulta_id');
            $diagnosisId = DB::table('diagnostico')->insertGetId(['consulta_id' => $consultationId, 'descripcion' => 'Diagnóstico de prueba']);
            DB::table('tratamiento')->insert(['diagnostico_id' => $diagnosisId, 'descripcion' => 'Tratamiento de prueba']);
            $parameters['cita'] = DB::table('consulta')->where('id', $consultationId)->value('cita_id');
        } elseif ($fixture === 'order') {
            $order = $this->laboratoryOrder();
            $resultId = DB::table('resultado_laboratorio')->insertGetId([
                'orden_analisis_id' => $order['items'][0], 'resultado' => 'Valor original', 'laboratorista_id' => $this->account('laboratorio')->getKey(),
            ]);
            DB::table('resultado_laboratorio')->where('id', $resultId)->update(['resultado' => 'Valor corregido']);
            $parameters['orden'] = $order['order'];
        }

        $response = $this->actingAs($this->account($login))->get(route($routeName, $parameters));

        $response->assertOk()->assertSee('<html', false)->assertSee('</html>', false)->assertSee('Vida Salud');
    }

    /** @return array<string, array{string, string}> */
    public static function forbiddenPages(): array
    {
        return [
            'admin sin clínica' => ['admin', 'clinica.index'], 'admin sin laboratorio' => ['admin', 'laboratorio.index'],
            'recepcion sin clínica' => ['recepcion', 'clinica.index'], 'recepcion sin laboratorio' => ['recepcion', 'laboratorio.index'],
            'laboratorio sin clínica' => ['laboratorio', 'clinica.index'], 'paciente sin laboratorio interno' => ['paciente', 'laboratorio.index'],
            'paciente sin reportes' => ['paciente', 'reportes.index'], 'paciente sin bitácora' => ['paciente', 'auditoria.index'],
        ];
    }

    #[DataProvider('forbiddenPages')]
    public function test_role_without_permission_gets_403(string $login, string $routeName): void
    {
        $this->seedVidaSalud();

        $this->actingAs($this->account($login))->get(route($routeName))->assertForbidden();
    }
}

<?php

namespace App\Modules\Inicio\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $modules = [];
        $stats = [];
        $add = function (string $title, string $description, string $icon, string $url) use (&$modules) {
            $modules[] = compact('title', 'description', 'icon', 'url');
        };
        if ($user->isPatient()) {
            $add('Mi portal', 'Mis citas, perfil y resultados publicados', 'user', route('portal.index'));
            $add('Reservar cita', 'Consulta los bloques disponibles', 'calendar', route('agenda.create'));
            $stats['Mis citas'] = Clinic::appointments($user)->count();
        } else {
            if ($user->hasPermission('pacientes.gestionar')) {
                $add('Pacientes', 'Registro de pacientes', 'users', route('catalog.index', 'pacientes'));
                $add('Verificación del portal', 'Solicitudes de cuenta e identidad', 'shield', route('portal.requests'));
                $stats['Pacientes activos'] = DB::table('paciente')->where('activo', true)->count();
            }
            if ($user->hasPermission('agenda.consultar')) {
                $add('Citas', 'Agenda médica', 'calendar', route('agenda.index'));
                $stats['Citas de hoy'] = Clinic::appointments($user)->where('c.inicio', '>=', now()->startOfDay())->where('c.inicio', '<', now()->addDay()->startOfDay())->count();
            }
            if ($user->hasPermission('atencion.registrar')) {
                $add('Consultas', 'Atención médica e historia clínica', 'stethoscope', route('clinica.index'));
            }
            foreach (['medicos', 'especialidades', 'horarios', 'usuarios', 'roles', 'analisis'] as $module) {
                $def = config('clinic.catalogs.'.$module);
                if ($user->hasPermission($def['permission']) || (in_array($module, ['medicos', 'especialidades', 'horarios'], true) && $user->hasPermission('pacientes.gestionar'))) {
                    $add($def['title'], match ($module) {
                        'medicos' => 'Equipo médico','especialidades' => 'Catálogo de especialidades','horarios' => 'Disponibilidad semanal','usuarios' => 'Acceso al sistema','roles' => 'Permisos por operación',default => 'Catálogo de análisis'
                    }, match ($module) {
                        'medicos' => 'doctor','usuarios','roles' => 'user','horarios' => 'calendar',default => 'file'
                    }, route('catalog.index', $module));
                }
            }
            if ($user->hasPermission('laboratorio.consultar')) {
                $add('Laboratorio', 'Órdenes, resultados e informes', 'flask', route('laboratorio.index'));
            }
            if ($user->hasPermission('reportes.administrativos') || $user->hasPermission('reportes.clinicos') || $user->hasPermission('reportes.laboratorio')) {
                $add('Reportes', 'Filtros, listados y totales', 'chart', route('reportes.index'));
            }
            if ($user->hasPermission('bitacora.consultar')) {
                $add('Bitácora', 'Actividad y trazabilidad', 'shield', route('auditoria.index'));
            }
        }

        return view('home', compact('modules', 'stats'));
    }
}

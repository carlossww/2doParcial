<?php

namespace App\Modules\Reportes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $types = [];
        if ($user->hasPermission('reportes.administrativos')) {
            $types += ['citas' => 'Citas', 'pacientes' => 'Pacientes registrados'];
        }
        if ($user->hasPermission('reportes.clinicos')) {
            $types += ['atenciones' => 'Atenciones médicas'];
            if ($user->hasPermission('laboratorio.consultar')) {
                $types += ['resultados' => 'Resultados de laboratorio'];
            }
        }
        if ($user->hasPermission('reportes.laboratorio')) {
            $types += ['ordenes' => 'Órdenes de laboratorio'];
            if ($user->hasPermission('laboratorio.consultar') && ! $user->isPatient()) {
                $types += ['resultados' => 'Resultados de laboratorio'];
            }
        }
        abort_unless($types, 403);
        $data = $request->validate(['tipo' => ['nullable', Rule::in(array_keys($types))], 'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'], 'medico_id' => ['nullable', 'integer'], 'especialidad_id' => ['nullable', 'integer'], 'estado' => ['nullable', 'string', 'max:20']]);
        $type = $data['tipo'] ?? array_key_first($types);
        $from = Clinic::date($data['desde'] ?? now()->startOfMonth()->format('Y-m-d'))->startOfDay();
        $to = Clinic::date($data['hasta'] ?? now()->format('Y-m-d'))->addDay()->startOfDay();
        if ($type === 'pacientes') {
            $columns = ['ci' => 'CI', 'paciente' => 'Paciente', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'activo' => 'Activo', 'creado_en' => 'Registrado'];
            $query = DB::table('paciente')->select('ci', DB::raw("nombres || ' ' || apellidos as paciente"), 'telefono', 'correo', 'activo', 'creado_en')->where('creado_en', '>=', $from)->where('creado_en', '<', $to)->orderByDesc('creado_en');
        } elseif ($type === 'citas') {
            $columns = ['codigo' => 'Código', 'paciente' => 'Paciente', 'medico' => 'Médico', 'especialidad' => 'Especialidad', 'inicio' => 'Inicio', 'estado' => 'Estado'];
            $query = Clinic::appointments($user, 'reportes.administrativos')->select('c.codigo', DB::raw("p.nombres || ' ' || p.apellidos as paciente"), DB::raw("u.nombres || ' ' || u.apellidos as medico"), 'e.nombre as especialidad', 'c.inicio', 'c.estado')->where('c.inicio', '>=', $from)->where('c.inicio', '<', $to);
            $this->appointmentFilters($query, $data);
            $query->orderBy('c.inicio');
        } elseif ($type === 'atenciones') {
            $columns = ['paciente' => 'Paciente', 'fecha_atencion' => 'Fecha de atención', 'medico' => 'Médico', 'especialidad' => 'Especialidad'];
            $query = DB::table('consulta as co')->join('cita as c', 'c.id', '=', 'co.cita_id')->join('paciente as p', 'p.id', '=', 'c.paciente_id')->join('medico as m', 'm.id', '=', 'c.medico_id')->join('usuario as u', 'u.id', '=', 'm.usuario_id')->join('especialidad as e', 'e.id', '=', 'c.especialidad_id')->select(DB::raw("p.nombres || ' ' || p.apellidos as paciente"), 'co.fecha_atencion', DB::raw("u.nombres || ' ' || u.apellidos as medico"), 'e.nombre as especialidad')->where('co.fecha_atencion', '>=', $from)->where('co.fecha_atencion', '<', $to)->where('c.medico_id', Clinic::doctorId($user));
            $this->appointmentFilters($query, $data);
            $query->orderBy('co.fecha_atencion');
        } elseif ($type === 'resultados') {
            Clinic::authorize($user, 'laboratorio.consultar');
            $columns = ['paciente' => 'Paciente', 'analisis' => 'Análisis', 'fecha_registro' => 'Fecha', 'resultado' => 'Resultado', 'observaciones' => 'Observaciones'];
            $query = DB::table('resultado_laboratorio as r')
                ->join('orden_analisis as d', 'd.id', '=', 'r.orden_analisis_id')
                ->join('analisis_laboratorio as a', 'a.id', '=', 'd.analisis_id')
                ->join('orden_laboratorio as o', 'o.id', '=', 'd.orden_id')
                ->join('consulta as co', 'co.id', '=', 'o.consulta_id')
                ->join('cita as c', 'c.id', '=', 'co.cita_id')
                ->join('paciente as p', 'p.id', '=', 'c.paciente_id')
                ->select(DB::raw("p.nombres || ' ' || p.apellidos as paciente"), 'a.nombre as analisis', 'r.fecha_registro', 'r.resultado', 'r.observaciones')
                ->where('r.fecha_registro', '>=', $from)->where('r.fecha_registro', '<', $to);
            if ($user->isDoctor()) {
                Clinic::doctorId($user);
                $query->where('o.solicitada_por', $user->id);
            } else {
                abort_if($user->isPatient(), 403);
            }
            if (! empty($data['estado'])) {
                $query->where('o.estado', $data['estado']);
            }
            $this->appointmentFilters($query, array_diff_key($data, ['estado' => true]));
            $query->orderByDesc('r.fecha_registro');
        } else {
            $columns = ['id' => 'Orden', 'paciente' => 'Paciente', 'fecha_solicitud' => 'Fecha', 'estado' => 'Estado'];
            $query = DB::table('orden_laboratorio as o')->join('consulta as co', 'co.id', '=', 'o.consulta_id')->join('cita as c', 'c.id', '=', 'co.cita_id')->join('paciente as p', 'p.id', '=', 'c.paciente_id')->select('o.id', DB::raw("p.nombres || ' ' || p.apellidos as paciente"), 'o.fecha_solicitud', 'o.estado')->where('o.fecha_solicitud', '>=', $from)->where('o.fecha_solicitud', '<', $to);
            if (! empty($data['estado'])) {
                $query->where('o.estado', $data['estado']);
            }$this->appointmentFilters($query, array_diff_key($data, ['estado' => true]));
            $query->orderBy('o.fecha_solicitud');
        }
        $total = (clone $query)->count();
        $rows = $query->paginate(50)->withQueryString();
        $doctors = Clinic::doctors();
        $specialties = Clinic::specialties();

        return view('reportes.index', compact('rows', 'columns', 'total', 'types', 'type', 'doctors', 'specialties'));
    }

    private function appointmentFilters(Builder $query, array $data): void
    {
        if (! empty($data['medico_id'])) {
            $query->where('c.medico_id', $data['medico_id']);
        }if (! empty($data['especialidad_id'])) {
            $query->where('c.especialidad_id', $data['especialidad_id']);
        }if (! empty($data['estado'])) {
            $query->where('c.estado', $data['estado']);
        }
    }
}

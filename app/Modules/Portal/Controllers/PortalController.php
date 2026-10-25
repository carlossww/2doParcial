<?php

namespace App\Modules\Portal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $id = Clinic::patientId($request->user());
        Clinic::authorize($request->user(), 'perfil.propio');
        $patient = DB::table('paciente')->where('id', $id)->first();
        $appointments = Clinic::appointments($request->user())->orderByDesc('c.inicio')->paginate(10);
        $results = DB::table('resultado_laboratorio as r')->join('orden_analisis as d', 'd.id', '=', 'r.orden_analisis_id')->join('analisis_laboratorio as a', 'a.id', '=', 'd.analisis_id')->join('orden_laboratorio as o', 'o.id', '=', 'd.orden_id')->join('consulta as co', 'co.id', '=', 'o.consulta_id')->join('cita as c', 'c.id', '=', 'co.cita_id')->where('c.paciente_id', $id)->whereNotNull('r.publicado_en')->select('r.id', 'a.nombre', 'r.resultado', 'r.observaciones', 'r.fecha_registro', 'r.archivo_referencia')->orderByDesc('r.fecha_registro')->limit(100)->get();
        $diagnoses = DB::table('diagnostico as d')->join('consulta as co', 'co.id', '=', 'd.consulta_id')->join('cita as c', 'c.id', '=', 'co.cita_id')->where('c.paciente_id', $id)->select('d.id', 'd.descripcion', 'd.codigo_clasificacion', 'co.fecha_atencion')->orderByDesc('co.fecha_atencion')->limit(100)->get();
        foreach ($diagnoses as $diagnosis) {
            $diagnosis->treatments = DB::table('tratamiento')->where('diagnostico_id', $diagnosis->id)->select('descripcion', 'indicaciones', 'fecha_inicio', 'fecha_fin')->get();
        }

        return view('portal.index', compact('patient', 'appointments', 'results', 'diagnoses'));
    }

    public function profile(Request $request): View
    {
        Clinic::authorize($request->user(), 'perfil.propio');
        $patient = DB::table('paciente')->where('id', Clinic::patientId($request->user()))->first();

        return view('portal.profile', compact('patient'));
    }

    public function update(Request $request): RedirectResponse
    {
        Clinic::authorize($request->user(), 'perfil.propio');
        $id = Clinic::patientId($request->user());
        $data = $request->validate(['telefono' => ['required', 'string', 'max:40'], 'correo' => ['nullable', 'email', 'max:150'], 'direccion' => ['nullable', 'string', 'max:3000'], 'contacto_emergencia' => ['nullable', 'string', 'max:150'], 'telefono_emergencia' => ['nullable', 'string', 'max:40']]);
        Clinic::transaction($request->user(), fn () => DB::table('paciente')->where('id', $id)->update($data));

        return redirect()->route('portal.profile')->with('success', 'Datos de contacto actualizados.');
    }
}

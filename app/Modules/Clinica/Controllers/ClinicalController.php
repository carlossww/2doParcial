<?php

namespace App\Modules\Clinica\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinica\Services\ClinicalService;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicalController extends Controller
{
    public function __construct(private ClinicalService $clinical) {}

    public function index(Request $request): View
    {
        Clinic::authorize($request->user(), 'atencion.registrar');
        $appointments = Clinic::appointments($request->user())->whereIn('c.estado', ['Programada', 'Confirmada', 'Atendida'])->orderByDesc('c.inicio')->paginate(15);

        return view('clinica.index', compact('appointments'));
    }

    public function show(Request $request, int $cita): View
    {
        $appointment = $this->clinical->appointment($request->user(), $cita);
        Clinic::clinicalPatient($request->user(), $appointment->paciente_id);
        $patient = DB::table('paciente')->where('id', $appointment->paciente_id)->first();
        $history = DB::table('historia_clinica')->where('paciente_id', $patient->id)->first();
        $consultation = DB::table('consulta')->where('cita_id', $cita)->first();
        $diagnoses = collect();
        $orders = collect();
        if ($consultation) {
            $diagnoses = DB::table('diagnostico')->where('consulta_id', $consultation->id)->orderByDesc('id')->get();
            foreach ($diagnoses as $diagnosis) {
                $diagnosis->treatments = DB::table('tratamiento')->where('diagnostico_id', $diagnosis->id)->get();
            }
            $orders = DB::table('orden_laboratorio')->where('consulta_id', $consultation->id)->orderByDesc('id')->get();
        }
        $patientHistory = DB::table('consulta as co')->join('cita as c', 'c.id', '=', 'co.cita_id')
            ->join('usuario as u', 'u.id', '=', 'co.registrada_por')
            ->where('c.paciente_id', $patient->id)->where('c.id', '<>', $cita)
            ->select('co.*', DB::raw("u.nombres || ' ' || u.apellidos as doctor"))
            ->orderByDesc('co.fecha_atencion')->limit(100)->get();
        foreach ($patientHistory as $previous) {
            $previous->diagnoses = DB::table('diagnostico')->where('consulta_id', $previous->id)->get();
            foreach ($previous->diagnoses as $diagnosis) {
                $diagnosis->treatments = DB::table('tratamiento')->where('diagnostico_id', $diagnosis->id)->get();
            }
        }
        $analyses = DB::table('analisis_laboratorio')->where('activo', true)->pluck('nombre', 'id')->all();

        return view('clinica.show', compact('appointment', 'patient', 'history', 'consultation', 'diagnoses', 'orders', 'analyses', 'patientHistory'));
    }

    public function history(Request $request, int $paciente): RedirectResponse
    {
        $data = $request->validate(['antecedentes_personales' => ['nullable', 'string', 'max:20000'], 'antecedentes_familiares' => ['nullable', 'string', 'max:20000'], 'alergias' => ['nullable', 'string', 'max:10000']]);
        $this->clinical->history($request->user(), $paciente, $data);

        return back()->with('success', 'Historia clínica actualizada.');
    }

    public function consultation(Request $request, int $cita): RedirectResponse
    {
        $data = $request->validate(['notas_clinicas' => ['required', 'string', 'max:20000']]);
        $this->clinical->attend($request->user(), $cita, $data);

        return redirect()->route('clinica.show', $cita)->with('success', 'Consulta guardada y cita marcada como atendida.');
    }

    public function diagnosis(Request $request, int $consulta): RedirectResponse
    {
        $data = $request->validate(['descripcion' => ['required', 'string', 'max:10000'], 'codigo_clasificacion' => ['nullable', 'string', 'max:30']]);
        $this->clinical->diagnosis($request->user(), $consulta, $data);

        return back()->with('success', 'Diagnóstico registrado.');
    }

    public function treatment(Request $request, int $diagnostico): RedirectResponse
    {
        $data = $request->validate(['descripcion' => ['required', 'string', 'max:10000'], 'indicaciones' => ['nullable', 'string', 'max:10000'], 'fecha_inicio' => ['nullable', 'date'], 'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio']]);
        $this->clinical->treatment($request->user(), $diagnostico, $data);

        return back()->with('success', 'Tratamiento registrado.');
    }

    public function order(Request $request, int $consulta): RedirectResponse
    {
        $data = $request->validate(['analisis_ids' => ['required', 'array', 'min:1'], 'analisis_ids.*' => ['required', 'integer', 'distinct', 'exists:analisis_laboratorio,id'], 'indicaciones' => ['nullable', 'string', 'max:10000']]);
        $id = $this->clinical->order($request->user(), $consulta, $data);

        return redirect()->route('laboratorio.show', $id)->with('success', 'Orden de laboratorio solicitada.');
    }

    public function publish(Request $request, int $orden): RedirectResponse
    {
        $this->clinical->publish($request->user(), $orden);

        return back()->with('success', 'Resultados publicados en el portal del paciente.');
    }
}

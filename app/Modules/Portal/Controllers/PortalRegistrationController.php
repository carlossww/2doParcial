<?php

namespace App\Modules\Portal\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PortalRegistrationController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['nombre_usuario' => mb_strtolower(trim((string) $request->input('nombre_usuario'))), 'correo' => mb_strtolower(trim((string) $request->input('correo'))), 'ci' => trim((string) $request->input('ci')) ?: null]);
        $data = $request->validate(['nombre_usuario' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'], 'nombres' => ['required', 'string', 'max:100'], 'apellidos' => ['required', 'string', 'max:100'], 'correo' => ['required', 'email', 'max:150'], 'ci' => ['nullable', 'string', 'max:40'], 'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'], 'telefono' => ['required', 'string', 'max:40'], 'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']]);
        DB::transaction(function () use ($data): void {
            $role = DB::table('rol')->where('nombre', 'Paciente')->where('activo', true)->first();
            abort_unless($role, 503);
            $id = DB::table('usuario')->insertGetId(['rol_id' => $role->id, 'nombre_usuario' => $data['nombre_usuario'], 'nombres' => $data['nombres'], 'apellidos' => $data['apellidos'], 'correo' => $data['correo'], 'password_hash' => Hash::make($data['password']), 'activo' => false]);
            DB::table('solicitud_portal')->insert(['usuario_id' => $id, 'ci_solicitado' => $data['ci'] ? mb_strtoupper($data['ci']) : null, 'fecha_nacimiento' => $data['fecha_nacimiento'], 'telefono' => $data['telefono']]);
            DB::table('bitacora')->insert(['usuario_id' => $id, 'accion' => 'SOLICITUD_PORTAL', 'entidad' => 'usuario', 'registro_id' => $id, 'descripcion' => 'Cuenta pendiente de verificación presencial', 'direccion_ip' => request()->ip()]);
        });

        return redirect()->route('login')->with('success', 'Solicitud registrada. Acude a recepción para verificar tu identidad y habilitar la cuenta; todavía no se vinculó a ningún paciente.');
    }

    public function requests(Request $request): View
    {
        Clinic::authorize($request->user(), 'pacientes.gestionar');
        $requests = DB::table('solicitud_portal as s')->join('usuario as u', 'u.id', '=', 's.usuario_id')->select('s.*', 'u.nombre_usuario', 'u.nombres', 'u.apellidos', 'u.correo')->orderByRaw("CASE WHEN s.estado='Pendiente' THEN 0 ELSE 1 END")->orderByDesc('s.creado_en')->paginate(15);
        $patients = DB::table('paciente')->where('activo', true)->pluck(DB::raw("nombres || ' ' || apellidos || ' · ' || coalesce(ci,'Sin CI') as label"), 'id')->all();

        return view('portal.requests', compact('requests', 'patients'));
    }

    public function verify(Request $request, int $solicitud): RedirectResponse
    {
        Clinic::authorize($request->user(), 'pacientes.gestionar');
        $data = $request->validate(['action' => ['required', Rule::in(['aprobar', 'rechazar'])], 'paciente_id' => ['required_if:action,aprobar', 'nullable', 'integer', 'exists:paciente,id'], 'identidad_verificada' => ['accepted_if:action,aprobar'], 'observacion' => ['required_if:action,rechazar', 'nullable', 'string', 'max:2000']]);
        Clinic::transaction($request->user(), function () use ($request, $solicitud, $data): void {
            $pending = DB::table('solicitud_portal')->where('id', $solicitud)->lockForUpdate()->first();
            abort_unless($pending, 404);
            if ($pending->estado !== 'Pendiente') {
                Clinic::fail('La solicitud ya fue resuelta.');
            }
            $user = User::where('id', $pending->usuario_id)->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'aprobar') {
                $patient = DB::table('paciente')->where('id', $data['paciente_id'])->lockForUpdate()->first();
                if (! $patient->activo || (string) $patient->fecha_nacimiento !== (string) $pending->fecha_nacimiento || ($pending->ci_solicitado && mb_strtoupper(trim((string) $patient->ci)) !== mb_strtoupper(trim($pending->ci_solicitado)))) {
                    Clinic::fail('El paciente no coincide con el CI y la fecha de nacimiento de la solicitud.', 'paciente_id');
                }
                if ($user->paciente_id && (int) $user->paciente_id !== (int) $patient->id) {
                    Clinic::fail('La cuenta ya tiene otro vínculo verificado.');
                }
                $user->forceFill(['activo' => true, 'paciente_id' => $patient->id, 'paciente_verificado_por' => $request->user()->id, 'paciente_verificado_en' => now()])->save();
            }
            DB::table('solicitud_portal')->where('id', $solicitud)->update(['estado' => $data['action'] === 'aprobar' ? 'Aprobada' : 'Rechazada', 'resuelta_por' => $request->user()->id, 'resuelta_en' => now(), 'observacion' => $data['observacion'] ?? null]);
            DB::table('bitacora')->insert(['usuario_id' => $request->user()->id, 'accion' => strtoupper($data['action']).'_PORTAL', 'entidad' => 'solicitud_portal', 'registro_id' => $solicitud, 'descripcion' => 'Resolución de verificación de identidad', 'direccion_ip' => $request->ip()]);
        });

        return back()->with('success', 'Solicitud resuelta correctamente.');
    }
}

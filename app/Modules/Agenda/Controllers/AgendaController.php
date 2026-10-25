<?php

namespace App\Modules\Agenda\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Modules\Agenda\Services\AppointmentService;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgendaController extends Controller
{
    public function __construct(private AppointmentService $appointments) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d'], 'medico_id' => ['nullable', 'integer'], 'estado' => ['nullable', Rule::in(Clinic::STATES)]]);
        $query = Clinic::appointments($request->user());
        if (! empty($filters['fecha'])) {
            $from = Clinic::date($filters['fecha'])->startOfDay();
            $query->where('c.inicio', '>=', $from)->where('c.inicio', '<', $from->addDay());
        }
        if (! empty($filters['medico_id'])) {
            $query->where('c.medico_id', $filters['medico_id']);
        } if (! empty($filters['estado'])) {
            $query->where('c.estado', $filters['estado']);
        }
        $stats = ['Citas encontradas' => (clone $query)->count()];
        $appointments = $query->orderByDesc('c.inicio')->paginate(15)->withQueryString();
        $doctors = Clinic::doctors();

        return view('agenda.index', compact('appointments', 'doctors', 'stats'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('citas.gestionar') || $user->hasPermission('citas.propias'), 403);
        $q = DB::table('paciente')->where('activo', true);
        if ($user->isPatient()) {
            $q->where('id', Clinic::patientId($user));
        }
        $patients = $q->pluck(DB::raw("nombres || ' ' || apellidos || ' · ' || coalesce(ci,'Sin CI') as label"), 'id')->all();
        $doctors = Clinic::doctors();
        $specialties = Clinic::specialties();

        return view('agenda.create', compact('patients', 'doctors', 'specialties'));
    }

    public function availability(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('citas.gestionar') || $user->hasPermission('citas.propias'), 403);
        $data = $request->validate(['medico_id' => ['required', 'integer', 'exists:medico,id'], 'especialidad_id' => ['required', 'integer', 'exists:especialidad,id'], 'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']]);
        abort_unless(DB::table('medico_especialidad')->where('medico_id', $data['medico_id'])->where('especialidad_id', $data['especialidad_id'])->where('activo', true)->exists(), 422, 'El médico no atiende esta especialidad.');
        $slots = DB::select('SELECT * FROM vida_salud.horarios_disponibles(?,?::date)', [$data['medico_id'], $data['fecha']]);
        $slots = collect($slots)->filter(function ($slot) use ($user) {
            if (! $user->isPatient()) {
                return true;
            }

            return ! DB::table('cita')->where('paciente_id', Clinic::patientId($user))->whereIn('estado', ['Programada', 'Confirmada', 'Atendida'])->where('inicio', '<', $slot->fin)->where('fin', '>', $slot->inicio)->exists();
        })->map(fn ($slot) => ['inicio' => Clinic::date($slot->inicio)->toIso8601String(), 'label' => Clinic::date($slot->inicio)->format('H:i').' – '.Clinic::date($slot->fin)->format('H:i')])->values();

        return response()->json(['slots' => $slots]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        $id = $this->appointments->create($request->user(), $request->validated());

        return redirect()->route('agenda.show', $id)->with('success', 'Cita reservada correctamente.');
    }

    public function show(Request $request, int $cita): View
    {
        $appointment = Clinic::appointments($request->user())->where('c.id', $cita)->first();
        abort_unless($appointment, 404);
        $events = DB::table('evento_cita as e')->join('usuario as u', 'u.id', '=', 'e.usuario_id')->where('e.cita_id', $cita)->select('e.*', 'u.nombre_usuario as actor')->orderByDesc('e.ocurrido_en')->get();
        if (! $request->user()->isDoctor() && ! $request->user()->isPatient()) {
            $appointment->motivo_consulta = null;
        }

        return view('agenda.show', compact('appointment', 'events'));
    }

    public function action(Request $request, int $cita): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['confirmar', 'cancelar', 'reprogramar', 'atendida', 'inasistencia'])], 'inicio' => ['required_if:action,reprogramar', 'nullable', 'date'], 'motivo_cancelacion' => ['required_if:action,cancelar', 'nullable', 'string', 'min:3', 'max:2000']]);
        $this->appointments->action($request->user(), $cita, $data);

        return redirect()->route('agenda.show', $cita)->with('success', 'Cita actualizada; el evento quedó registrado.');
    }
}

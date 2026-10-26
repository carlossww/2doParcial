<?php

namespace App\Modules\Agenda\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agenda\Services\AppointmentQueryService;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentQueryController extends Controller
{
    public function __construct(private AppointmentQueryService $appointments) {}

    public function index(Request $request): View
    {
        return $this->agenda($request);
    }

    public function doctor(Request $request, int $id): View
    {
        return $this->agenda($request, $id);
    }

    private function agenda(Request $request, ?int $doctorId = null): View
    {
        $scope = $this->appointments->doctorScope($request->user());
        if ($scope !== null) {
            abort_if($doctorId !== null && $scope !== $doctorId, 404);
        }
        $filters = $this->filters($request);
        if ($scope !== null && ! empty($filters['medico_id'])) {
            abort_if((int) $filters['medico_id'] !== $scope, 404);
        }
        if ($doctorId !== null && ! empty($filters['medico_id'])) {
            abort_if((int) $filters['medico_id'] !== $doctorId, 404);
        }
        $fixedDoctor = $scope ?? $doctorId;
        $doctor = $fixedDoctor === null ? null : DB::table('medico as m')
            ->join('usuario as u', 'u.id', '=', 'm.usuario_id')->where('m.id', $fixedDoctor)
            ->select('m.id')->selectRaw("concat_ws(' ', u.nombres, u.apellidos) as nombre")->first();
        abort_if($fixedDoctor !== null && ! $doctor, 404);
        if (! $request->has('desde') && ! $request->has('hasta')) {
            $filters['desde'] = $filters['hasta'] = now('America/La_Paz')->toDateString();
        }
        if ($fixedDoctor !== null) {
            $filters['medico_id'] = $fixedDoctor;
        } elseif (! empty($filters['medico_id'])) {
            $request->validate(['medico_id' => ['exists:medico,id']]);
        }
        $showReason = $scope !== null && $request->user()->hasPermission('atencion.registrar');
        $query = $this->appointments->query($showReason);
        $this->appointments->filter($query, $filters);
        $rows = $query->orderBy('c.inicio')->orderBy('c.id')->paginate(20)->appends($filters);
        $title = $doctor ? 'Agenda de '.$doctor->nombre : 'Agenda médica';
        $backUrl = $scope === null && $doctorId !== null ? route('catalog.index', 'medicos') : route('home');
        $filterUrl = $doctorId === null ? route('agenda.index') : route('agenda.doctor', $doctorId);
        $doctors = $fixedDoctor === null ? Clinic::doctors(includeInactive: true) : null;
        $isHistory = false;
        $states = array_combine(AppointmentQueryService::STATES, AppointmentQueryService::STATES);

        return view('agenda.index', compact('rows', 'filters', 'title', 'backUrl', 'filterUrl', 'doctors', 'isHistory', 'showReason', 'states'));
    }

    public function patient(Request $request, int $id): View
    {
        Clinic::authorize($request->user(), 'pacientes.gestionar');
        $patient = DB::table('paciente')->where('id', $id)->first();
        abort_unless($patient, 404);
        $filters = $this->filters($request);
        if (! empty($filters['medico_id'])) {
            $request->validate(['medico_id' => ['exists:medico,id']]);
        }
        $query = $this->appointments->query()->where('c.paciente_id', $id);
        $this->appointments->filter($query, $filters);
        $rows = $query->orderByDesc('c.inicio')->orderByDesc('c.id')->paginate(20)->appends($filters);
        $title = 'Citas de '.$patient->nombres.' '.$patient->apellidos;
        $backUrl = route('catalog.index', 'pacientes');
        $filterUrl = route('appointments.patient', $id);
        $doctors = Clinic::doctors(includeInactive: true);
        $isHistory = true;
        $showReason = false;
        $states = array_combine(AppointmentQueryService::STATES, AppointmentQueryService::STATES);

        return view('agenda.index', compact('rows', 'filters', 'title', 'backUrl', 'filterUrl', 'doctors', 'isHistory', 'showReason', 'states'));
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'desde' => ['nullable', 'date', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')],
            'estado' => ['nullable', Rule::in(AppointmentQueryService::STATES)],
            'medico_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}

<?php

namespace App\Modules\Laboratorio\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabResultRequest;
use App\Modules\Laboratorio\Services\LabService;
use App\Modules\Shared\Clinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabController extends Controller
{
    public function __construct(private LabService $lab) {}

    public function index(Request $request): View
    {
        $data = $request->validate(['estado' => ['nullable', Rule::in(['Pendiente', 'En proceso', 'Completada', 'Cancelada'])]]);
        $query = $this->lab->orders($request->user());
        if (! empty($data['estado'])) {
            $query->where('o.estado', $data['estado']);
        }$orders = $query->orderByDesc('o.fecha_solicitud')->paginate(15)->withQueryString();

        return view('laboratorio.index', compact('orders'));
    }

    public function show(Request $request, int $orden): View
    {
        $order = $this->lab->orders($request->user())->where('o.id', $orden)->first();
        abort_unless($order, 404);
        $items = DB::table('orden_analisis as d')->join('analisis_laboratorio as a', 'a.id', '=', 'd.analisis_id')->leftJoin('resultado_laboratorio as r', 'r.orden_analisis_id', '=', 'd.id')->where('d.orden_id', $orden)->select('d.id', 'a.nombre', 'r.id as resultado_id', 'r.resultado', 'r.observaciones', 'r.archivo_referencia', 'r.fecha_registro', 'r.publicado_en')->orderBy('d.id')->get();
        $versions = DB::table('resultado_version as v')->join('usuario as u', 'u.id', '=', 'v.corregido_por')->whereIn('v.resultado_id', $items->pluck('resultado_id')->filter())->select('v.*', 'u.nombre_usuario as autor')->orderByDesc('v.corregido_en')->get()->groupBy('resultado_id');

        return view('laboratorio.show', compact('order', 'items', 'versions'));
    }

    public function result(LabResultRequest $request, int $ordenAnalisis): RedirectResponse
    {
        $this->lab->result($request->user(), $ordenAnalisis, $request->validated(), $request->file('archivo'));

        return back()->with('success', 'Resultado guardado. Las correcciones conservan su versión anterior.');
    }

    public function action(Request $request, int $orden): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', Rule::in(['En proceso', 'Cancelada'])], 'motivo_cancelacion' => ['required_if:estado,Cancelada', 'nullable', 'string', 'min:3', 'max:2000']]);
        $this->lab->action($request->user(), $orden, $data);

        return back()->with('success', 'Orden actualizada.');
    }

    public function download(Request $request, int $resultado): StreamedResponse
    {
        $result = DB::table('resultado_laboratorio as r')->join('orden_analisis as d', 'd.id', '=', 'r.orden_analisis_id')->join('orden_laboratorio as o', 'o.id', '=', 'd.orden_id')->join('consulta as co', 'co.id', '=', 'o.consulta_id')->join('cita as c', 'c.id', '=', 'co.cita_id')->where('r.id', $resultado)->select('r.*', 'o.solicitada_por', 'c.paciente_id')->first();
        abort_unless($result && $result->archivo_referencia, 404);
        $user = $request->user();
        if ($user->isPatient()) {
            abort_unless((int) $result->paciente_id === Clinic::patientId($user) && $result->publicado_en, 404);
        } elseif ($user->isDoctor()) {
            Clinic::authorize($user, 'laboratorio.consultar');
            Clinic::doctorId($user);
            abort_unless((int) $result->solicitada_por === (int) $user->id, 404);
        } else {
            Clinic::authorize($user, 'laboratorio.consultar');
        }
        abort_unless(Storage::disk('local')->exists($result->archivo_referencia), 404);

        return Storage::disk('local')->download($result->archivo_referencia, 'informe-'.$resultado.'.'.pathinfo($result->archivo_referencia, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff']);
    }
}

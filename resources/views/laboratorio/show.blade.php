@extends('layouts.app')
@section('title', 'Orden de laboratorio #'.$order->id)
@section('content')
    @php($canProcess = auth()->user()->hasPermission('laboratorio.procesar') && $order->estado !== 'Cancelada')
    <x-page-heading :title="'Orden de laboratorio #'.$order->id" description="Registra los resultados y consulta sus versiones anteriores." :back="route('laboratorio.index')"><x-status :value="$order->estado" /></x-page-heading>
    <div class="two-column">
        <div class="section-stack">
            @forelse($items as $item)
                <section class="panel"><div class="panel-heading"><h2>{{ $item->nombre }}</h2><x-status :value="$item->resultado_id ? 'Registrado' : 'Pendiente'" /></div>
                    @if($item->resultado_id)<dl class="detail-list"><div><dt>Resultado</dt><dd class="text-pre">{{ $item->resultado }}</dd></div>@if($item->observaciones)<div><dt>Observaciones</dt><dd class="text-pre">{{ $item->observaciones }}</dd></div>@endif</dl>@if($item->archivo_referencia)<p style="margin-top: 16px"><a class="button button-small" href="{{ route('laboratorio.download', ['resultado' => $item->resultado_id]) }}"><x-icon name="download" />Descargar archivo</a></p>@endif @else<p class="text-muted">Este análisis aún no tiene un resultado registrado.</p>@endif
                    @if($canProcess)
                        <details class="disclosure" @if(old('_section') === 'result-'.$item->id || !$item->resultado_id) open @endif><summary>{{ $item->resultado_id ? 'Registrar nueva versión del resultado' : 'Registrar resultado' }}</summary>
                            <form method="POST" action="{{ route('laboratorio.result', ['ordenAnalisis' => $item->id]) }}" enctype="multipart/form-data" class="form-stack" data-submit-form>
                                @csrf<input type="hidden" name="_section" value="result-{{ $item->id }}">
                                <x-field name="resultado" :scope="'result-'.$item->id" :id="'result-'.$item->id" label="Resultado" type="textarea" :value="$item->resultado" :required="true" maxlength="30000" />
                                <x-field name="observaciones" :scope="'result-'.$item->id" :id="'observations-'.$item->id" label="Observaciones" type="textarea" :value="$item->observaciones" maxlength="10000" />
                                <x-field name="archivo" :scope="'result-'.$item->id" :id="'file-'.$item->id" label="Archivo del resultado" type="file" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" data-max-size="5242880" hint="Opcional. PDF, PNG o JPEG, hasta 5 MB. Si la validación falla, vuelve a seleccionar el archivo." />
                                <button class="button button-primary" type="submit">{{ $item->resultado_id ? 'Guardar nueva versión' : 'Guardar resultado' }}</button>
                            </form>
                        </details>
                    @endif
                    @if($item->resultado_id && count($versions[$item->resultado_id] ?? []))<details class="disclosure"><summary>Historial de versiones</summary><ol class="timeline">@foreach($versions[$item->resultado_id] as $version)<li><strong>Versión anterior</strong><p class="text-pre text-small">{{ $version->valor_anterior }}</p>@if($version->observaciones_anteriores)<p class="text-pre text-small text-muted">{{ $version->observaciones_anteriores }}</p>@endif @if(data_get($version, 'autor'))<p class="text-small text-muted">{{ $version->autor }}</p>@endif<time>{{ \Illuminate\Support\Carbon::parse($version->corregido_en)->format('d/m/Y · H:i:s') }}</time></li>@endforeach</ol></details>@endif
                </section>
            @empty<x-empty-state title="Sin análisis asociados" message="Esta orden no tiene análisis para mostrar." icon="flask" />@endforelse
        </div>
        <aside class="section-stack">
            <section class="panel"><h2>Información de la orden</h2><dl class="detail-list"><div><dt>Paciente</dt><dd>{{ data_get($order, 'paciente', '—') }}</dd></div><div><dt>Médico solicitante</dt><dd>{{ data_get($order, 'medico', '—') }}</dd></div><div><dt>Fecha de solicitud</dt><dd>{{ data_get($order, 'fecha_solicitud') ? \Illuminate\Support\Carbon::parse($order->fecha_solicitud)->format('d/m/Y · H:i') : '—' }}</dd></div><div><dt>Indicaciones</dt><dd class="text-pre">{{ data_get($order, 'indicaciones') ?: 'Sin indicaciones adicionales.' }}</dd></div>@if(data_get($order, 'motivo_cancelacion'))<div><dt>Motivo de cancelación</dt><dd class="text-pre">{{ $order->motivo_cancelacion }}</dd></div>@endif</dl></section>
            @if($canProcess && in_array($order->estado, ['Pendiente', 'En proceso'], true))<section class="panel"><h2>Estado de la orden</h2><form method="POST" action="{{ route('laboratorio.action', ['orden' => $order->id]) }}" class="form-stack" data-submit-form>@csrf<x-field name="estado" label="Nuevo estado" type="select" :options="['En proceso' => 'En proceso', 'Cancelada' => 'Cancelada']" :required="true" /><x-field name="motivo_cancelacion" label="Motivo de cancelación" type="textarea" hint="Obligatorio al cancelar una orden." /><button class="button button-primary" type="submit">Actualizar estado</button></form></section>@endif
        </aside>
    </div>
@endsection

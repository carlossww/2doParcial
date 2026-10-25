@extends('layouts.app')
@section('title', 'Solicitudes del portal')
@section('content')
    <x-page-heading title="Solicitudes del portal" description="Revisa los registros y verifica presencialmente la identidad antes de activar una cuenta."><a class="button" href="{{ route('catalog.create', ['module' => 'pacientes']) }}"><x-icon name="plus" />Registrar paciente</a></x-page-heading>
    <div class="alert alert-info"><x-icon name="shield" /><p>Para aprobar una solicitud, selecciona un paciente existente y confirma que verificaste físicamente su identidad. Si es un paciente nuevo, <a href="{{ route('catalog.create', ['module' => 'pacientes']) }}">regístralo primero en Pacientes</a> y regresa a esta solicitud.</p></div>
    <div class="section-stack">
        @forelse($requests as $requestRecord)
            @php($pending = in_array(mb_strtolower($requestRecord->estado), ['pendiente', 'solicitada'], true))
            <article class="panel">
                <div class="panel-heading"><div><h2>{{ $requestRecord->nombres }} {{ $requestRecord->apellidos }}</h2><p class="text-small text-muted">Solicitud #{{ $requestRecord->id }} · {{ $requestRecord->nombre_usuario }}</p></div><x-status :value="$requestRecord->estado" /></div>
                <dl class="detail-list detail-grid"><div><dt>Correo electrónico</dt><dd>{{ $requestRecord->correo }}</dd></div><div><dt>CI declarado</dt><dd>{{ $requestRecord->ci_solicitado ?: 'No declarado' }}</dd></div><div><dt>Fecha de nacimiento</dt><dd>{{ \Illuminate\Support\Carbon::parse($requestRecord->fecha_nacimiento)->format('d/m/Y') }}</dd></div><div><dt>Teléfono</dt><dd>{{ $requestRecord->telefono }}</dd></div><div><dt>Fecha de solicitud</dt><dd>{{ \Illuminate\Support\Carbon::parse($requestRecord->creado_en)->format('d/m/Y · H:i') }}</dd></div></dl>
                @if(!$pending && data_get($requestRecord, 'observacion'))<p class="text-pre request-observation"><strong>Observación de la revisión:</strong> {{ $requestRecord->observacion }}</p>@endif
                @if($pending && auth()->user()->hasPermission('pacientes.gestionar'))
                    <details class="disclosure" @if(old('_section') === 'approve-'.$requestRecord->id) open @endif><summary>Verificar y aprobar solicitud</summary>
                        <form method="POST" action="{{ route('portal.verify', ['solicitud' => $requestRecord->id]) }}" class="form-stack" data-submit-form data-confirm="¿Confirmas que verificaste presencialmente la identidad y que la cuenta corresponde al paciente seleccionado?">
                            @csrf<input type="hidden" name="action" value="aprobar"><input type="hidden" name="_section" value="approve-{{ $requestRecord->id }}">
                            <x-field name="paciente_id" :id="'verify-patient-'.$requestRecord->id" :scope="'approve-'.$requestRecord->id" label="Paciente verificado" type="select" :options="$patients" :required="true" hint="Comprueba que el documento y los datos coincidan con la solicitud." />
                            <x-field name="identidad_verificada" :id="'verify-identity-'.$requestRecord->id" :scope="'approve-'.$requestRecord->id" label="He verificado presencialmente el documento y la identidad de esta persona." type="checkbox" :required="true" />
                            <x-field name="observacion" :id="'approve-observation-'.$requestRecord->id" :scope="'approve-'.$requestRecord->id" label="Observación (opcional)" type="textarea" maxlength="2000" />
                            @if(count($patients))<button class="button button-primary" type="submit"><x-icon name="check" />Aprobar y activar cuenta</button>@else<p class="alert alert-warning">Primero registra al paciente para poder aprobar esta solicitud.</p>@endif
                        </form>
                    </details>
                    <details class="disclosure" @if(old('_section') === 'reject-'.$requestRecord->id) open @endif><summary>Rechazar solicitud</summary>
                        <form method="POST" action="{{ route('portal.verify', ['solicitud' => $requestRecord->id]) }}" class="form-stack" data-submit-form data-confirm="¿Deseas rechazar esta solicitud de acceso al portal?">
                            @csrf<input type="hidden" name="action" value="rechazar"><input type="hidden" name="_section" value="reject-{{ $requestRecord->id }}">
                            <x-field name="observacion" :id="'reject-observation-'.$requestRecord->id" :scope="'reject-'.$requestRecord->id" label="Motivo del rechazo" type="textarea" :required="true" maxlength="2000" hint="Explica el motivo para dejar constancia de la revisión." />
                            <button class="button button-danger" type="submit">Rechazar solicitud</button>
                        </form>
                    </details>
                @endif
            </article>
        @empty<x-empty-state title="No hay solicitudes para revisar" message="Los nuevos registros del portal aparecerán aquí para su verificación." icon="shield" />@endforelse
    </div>
    {{ $requests->withQueryString()->links('pagination.default') }}
@endsection

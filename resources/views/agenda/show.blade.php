@extends('layouts.app')
@section('title', 'Cita '.$appointment->codigo)
@section('content')
    @php
        $user = auth()->user();
        $canManage = !$user->isPatient() && !$user->isDoctor() && $user->hasPermission('citas.gestionar');
        $isOpen = in_array($appointment->estado, ['Programada', 'Confirmada'], true);
        $canPatientAct = $user->isPatient() && (int) $user->paciente_id === (int) $appointment->paciente_id && $user->hasPermission('citas.propias') && \Illuminate\Support\Carbon::parse($appointment->inicio)->isFuture();
    @endphp
    <x-page-heading :title="'Cita '.$appointment->codigo" description="Información de la reserva y seguimiento de cambios." :back="route('agenda.index')"><x-status :value="$appointment->estado" /></x-page-heading>
    <div class="two-column">
        <div class="section-stack">
            <section class="panel"><h2>Información de la cita</h2><dl class="detail-list detail-grid">
                <div><dt>Paciente</dt><dd>{{ $appointment->paciente }}</dd></div><div><dt>Médico</dt><dd>{{ $appointment->medico }}</dd></div><div><dt>Especialidad</dt><dd>{{ $appointment->especialidad }}</dd></div>
                <div><dt>Fecha y horario</dt><dd>{{ \Illuminate\Support\Carbon::parse($appointment->inicio)->format('d/m/Y · H:i') }}–{{ \Illuminate\Support\Carbon::parse($appointment->fin)->format('H:i') }}</dd></div>
                @if($user->isPatient() || $user->isDoctor())<div class="grid-full"><dt>Motivo de consulta</dt><dd class="text-pre">{{ $appointment->motivo_consulta ?: 'No registrado.' }}</dd></div>@endif
                @if($appointment->motivo_cancelacion ?? null)<div class="grid-full"><dt>Motivo de cancelación</dt><dd class="text-pre">{{ $appointment->motivo_cancelacion }}</dd></div>@endif
            </dl></section>
            @if($isOpen && ($canManage || $canPatientAct))
                <section class="panel"><h2>Gestionar cita</h2><div class="section-stack">
                    @if($appointment->estado === 'Programada')<form method="POST" action="{{ route('agenda.action', ['cita' => $appointment->id]) }}" data-submit-form>@csrf<input type="hidden" name="action" value="confirmar"><button class="button button-primary" type="submit"><x-icon name="check" />Confirmar cita</button></form>@endif
                    <details class="disclosure" @if(old('action') === 'reprogramar') open @endif><summary>Reprogramar cita</summary>
                        <form method="POST" action="{{ route('agenda.action', ['cita' => $appointment->id]) }}" class="form-stack" data-submit-form data-availability="{{ route('agenda.availability') }}">
                            @csrf<input type="hidden" name="action" value="reprogramar"><input type="hidden" name="medico_id" value="{{ $appointment->medico_id }}"><input type="hidden" name="especialidad_id" value="{{ $appointment->especialidad_id }}">
                            <x-field name="fecha" label="Nueva fecha" type="date" :min="now()->toDateString()" :required="true" />
                            <x-field name="inicio" label="Nuevo horario" type="select" :required="true" :data-selected="old('inicio', '')" />
                            <p class="availability-status" data-availability-status role="status" aria-live="polite">Selecciona una fecha para consultar los horarios.</p><button type="button" class="button" data-availability-retry hidden>Volver a consultar</button>
                            <button type="submit" class="button button-primary" data-book-appointment>Guardar nuevo horario</button>
                        </form>
                    </details>
                    <details class="disclosure" @if(old('action') === 'cancelar') open @endif><summary>Cancelar cita</summary><form method="POST" action="{{ route('agenda.action', ['cita' => $appointment->id]) }}" class="form-stack" data-submit-form data-confirm="¿Deseas cancelar esta cita?">@csrf<input type="hidden" name="action" value="cancelar"><x-field name="motivo_cancelacion" label="Motivo de cancelación" type="textarea" :required="true" minlength="3" maxlength="2000" /><button class="button button-danger" type="submit">Cancelar cita</button></form></details>
                    @if($canManage)<div class="button-row">@foreach(['inasistencia' => 'Registrar inasistencia', 'atendida' => 'Marcar como atendida'] as $action => $label)@if(($action === 'inasistencia' && \Illuminate\Support\Carbon::parse($appointment->fin)->isPast()) || ($action === 'atendida' && !\Illuminate\Support\Carbon::parse($appointment->inicio)->isFuture()))<form method="POST" action="{{ route('agenda.action', ['cita' => $appointment->id]) }}" data-submit-form data-confirm="¿Confirmas esta acción sobre la cita?">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="button" type="submit">{{ $label }}</button></form>@endif @endforeach</div>@endif
                </div></section>
            @endif
            @if($user->hasPermission('atencion.registrar'))<a class="button" href="{{ route('clinica.show', ['cita' => $appointment->id]) }}"><x-icon name="stethoscope" />Ver atención clínica</a>@endif
        </div>
        <section class="panel"><h2>Historial de la cita</h2>
            @if(count($events))
                <ol class="timeline">
                    @foreach($events as $event)
                        <li>
                            <p class="text-strong">@if($event->estado_anterior){{ $event->estado_anterior }} <span aria-label="cambió a">→</span> @else Cita registrada: @endif{{ $event->estado_nuevo }}</p>
                            <dl class="detail-list event-details">
                                @if($event->inicio_anterior && $event->inicio_anterior !== $event->inicio_nuevo)<div><dt>Horario anterior</dt><dd>{{ \Illuminate\Support\Carbon::parse($event->inicio_anterior)->format('d/m/Y · H:i') }}</dd></div>@endif
                                <div><dt>{{ $event->inicio_anterior && $event->inicio_anterior !== $event->inicio_nuevo ? 'Nuevo horario' : 'Horario de la cita' }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($event->inicio_nuevo)->format('d/m/Y · H:i') }}</dd></div>
                            </dl>
                            @if($event->detalle)<p class="text-small text-pre">{{ $event->detalle }}</p>@endif
                            <p class="text-small text-muted">Registrado por {{ $event->actor ?: 'Sistema' }}</p>
                            <time datetime="{{ $event->ocurrido_en }}">{{ \Illuminate\Support\Carbon::parse($event->ocurrido_en)->format('d/m/Y · H:i:s') }}</time>
                        </li>
                    @endforeach
                </ol>
            @else<p class="text-muted">Aún no hay cambios registrados.</p>@endif
        </section>
    </div>
@endsection

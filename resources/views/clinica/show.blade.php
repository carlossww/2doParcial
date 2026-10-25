@extends('layouts.app')
@section('title', 'Atención · '.$appointment->codigo)
@section('content')
    @php
        $canAttend = auth()->user()->hasPermission('atencion.registrar');
        $canOrder = auth()->user()->hasPermission('laboratorio.solicitar');
    @endphp
    <x-page-heading :title="'Atención · '.$appointment->codigo" :description="$appointment->paciente.' · '.$appointment->especialidad" :back="route('clinica.index')"><x-status :value="$appointment->estado" /></x-page-heading>
    <div class="two-column">
        <div class="section-stack">
            <section class="panel" id="historia"><div class="panel-heading"><h2>Historia clínica</h2><x-icon name="history" /></div>
                @if($history)<dl class="detail-list"><div><dt>Antecedentes personales</dt><dd class="text-pre">{{ data_get($history, 'antecedentes_personales') ?: 'No registrados.' }}</dd></div><div><dt>Antecedentes familiares</dt><dd class="text-pre">{{ data_get($history, 'antecedentes_familiares') ?: 'No registrados.' }}</dd></div><div><dt>Alergias</dt><dd class="text-pre">{{ data_get($history, 'alergias') ?: 'No registradas.' }}</dd></div></dl>@else<p class="text-muted">El paciente aún no tiene antecedentes registrados.</p>@endif
                @if($canAttend)<details class="disclosure" @if(old('_section') === 'history') open @endif><summary>{{ $history ? 'Actualizar antecedentes' : 'Registrar antecedentes' }}</summary><form method="POST" action="{{ route('clinica.history', ['paciente' => $patient->id]) }}" class="form-stack" data-submit-form>@csrf<input type="hidden" name="_section" value="history"><x-field name="antecedentes_personales" label="Antecedentes personales" type="textarea" :value="data_get($history, 'antecedentes_personales')" /><x-field name="antecedentes_familiares" label="Antecedentes familiares" type="textarea" :value="data_get($history, 'antecedentes_familiares')" /><x-field name="alergias" label="Alergias" type="textarea" :value="data_get($history, 'alergias')" /><button class="button button-primary" type="submit">Guardar antecedentes</button></form></details>@endif
            </section>
            <section class="panel" id="atenciones-anteriores">
                <div class="panel-heading"><h2>Atenciones anteriores del paciente</h2><x-icon name="history" /></div>
                <p class="text-muted text-small">De la más reciente a la más antigua. La atención actual se muestra por separado.</p>
                @forelse(collect($patientHistory ?? [])->sortByDesc('fecha_atencion') as $previousAttention)
                    <details class="disclosure">
                        <summary>{{ \Illuminate\Support\Carbon::parse($previousAttention->fecha_atencion)->format('d/m/Y · H:i') }} · {{ $previousAttention->doctor }}</summary>
                        <dl class="detail-list"><div><dt>Notas clínicas</dt><dd class="text-pre">{{ $previousAttention->notas_clinicas ?: 'Sin notas registradas.' }}</dd></div></dl>
                        @forelse($previousAttention->diagnoses as $previousDiagnosis)
                            <article class="record-card"><h3>{{ $previousDiagnosis->descripcion }}</h3>@if($previousDiagnosis->codigo_clasificacion)<p class="field-hint">Clasificación: {{ $previousDiagnosis->codigo_clasificacion }}</p>@endif
                                @forelse($previousDiagnosis->treatments as $previousTreatment)
                                    <div class="record-card"><p class="text-strong">{{ $previousTreatment->descripcion }}</p><p class="text-small text-pre">{{ $previousTreatment->indicaciones ?: 'Sin indicaciones adicionales.' }}</p>@if(data_get($previousTreatment, 'fecha_inicio') || data_get($previousTreatment, 'fecha_fin'))<p class="field-hint">{{ data_get($previousTreatment, 'fecha_inicio') ?: 'Sin fecha de inicio' }} @if(data_get($previousTreatment, 'fecha_fin')) · Hasta {{ $previousTreatment->fecha_fin }} @endif</p>@endif</div>
                                @empty<p class="text-muted text-small">Sin tratamientos registrados.</p>@endforelse
                            </article>
                        @empty<p class="text-muted text-small">Sin diagnósticos registrados en esta atención.</p>@endforelse
                    </details>
                @empty<p class="text-muted" style="margin-top: 16px">El paciente no tiene atenciones anteriores registradas.</p>@endforelse
            </section>
            <section class="panel" id="consulta"><h2>Consulta médica</h2>
                <p class="field-hint">Motivo de consulta</p><p class="text-pre">{{ $appointment->motivo_consulta ?? 'No registrado.' }}</p>
                @if($canAttend)<form method="POST" action="{{ route('clinica.consultation', ['cita' => $appointment->id]) }}" class="form-stack" data-submit-form>@csrf<input type="hidden" name="_section" value="consultation"><x-field name="notas_clinicas" label="Notas clínicas" type="textarea" :value="data_get($consultation, 'notas_clinicas')" :required="true" hint="Registra la valoración y los hallazgos de esta atención." /><button class="button button-primary" type="submit">{{ $consultation ? 'Actualizar consulta' : 'Guardar consulta' }}</button></form>@else<dl class="detail-list"><div><dt>Notas clínicas</dt><dd class="text-pre">{{ data_get($consultation, 'notas_clinicas') ?: 'Aún no se ha registrado la consulta.' }}</dd></div></dl>@endif
            </section>
            <section class="panel" id="diagnosticos"><h2>Diagnósticos y tratamientos</h2>
                @forelse($diagnoses as $diagnosis)
                    <article class="record-card"><h3>{{ $diagnosis->descripcion }}</h3>@if($diagnosis->codigo_clasificacion ?? null)<p class="record-meta">Clasificación: {{ $diagnosis->codigo_clasificacion }}</p>@endif
                        @forelse(data_get($diagnosis, 'treatments', []) as $treatment)<div class="record-card"><p class="text-strong">{{ data_get($treatment, 'descripcion') }}</p><p class="text-pre text-small">{{ data_get($treatment, 'indicaciones') }}</p><p class="field-hint">{{ data_get($treatment, 'fecha_inicio') ?: 'Sin fecha de inicio' }} @if(data_get($treatment, 'fecha_fin')) · Hasta {{ data_get($treatment, 'fecha_fin') }}@endif</p></div>@empty<p class="text-muted text-small">Sin tratamientos registrados.</p>@endforelse
                        @if($canAttend)<details class="disclosure" @if(old('_section') === 'treatment-'.$diagnosis->id) open @endif><summary>Agregar tratamiento</summary><form method="POST" action="{{ route('clinica.treatment', ['diagnostico' => $diagnosis->id]) }}" class="form-stack" data-submit-form>@csrf<input type="hidden" name="_section" value="treatment-{{ $diagnosis->id }}"><x-field name="descripcion" :id="'treatment-description-'.$diagnosis->id" label="Tratamiento" type="textarea" :required="true" /><x-field name="indicaciones" :id="'treatment-instructions-'.$diagnosis->id" label="Indicaciones" type="textarea" :required="true" /><div class="form-grid"><x-field name="fecha_inicio" :id="'treatment-start-'.$diagnosis->id" label="Fecha de inicio" type="date" :required="true" :value="now()->toDateString()" /><x-field name="fecha_fin" :id="'treatment-end-'.$diagnosis->id" label="Fecha de finalización" type="date" /></div><button class="button button-primary" type="submit">Guardar tratamiento</button></form></details>@endif
                    </article>
                @empty<p class="text-muted">Aún no se han registrado diagnósticos.</p>@endforelse
                @if($consultation && $canAttend)<details class="disclosure" @if(old('_section') === 'diagnosis') open @endif><summary>Agregar diagnóstico</summary><form method="POST" action="{{ route('clinica.diagnosis', ['consulta' => $consultation->id]) }}" class="form-stack" data-submit-form>@csrf<input type="hidden" name="_section" value="diagnosis"><x-field name="descripcion" id="diagnosis-description" label="Descripción del diagnóstico" type="textarea" :required="true" /><x-field name="codigo_clasificacion" label="Código de clasificación" /><button class="button button-primary" type="submit">Guardar diagnóstico</button></form></details>@elseif(!$consultation && $canAttend)<p class="field-hint">Guarda la consulta para agregar diagnósticos y tratamientos.</p>@endif
            </section>
            <section class="panel" id="laboratorio"><h2>Órdenes de laboratorio</h2>
                @forelse($orders as $order)<article class="record-card"><div class="panel-heading"><h3>Orden {{ data_get($order, 'codigo', '#'.$order->id) }}</h3><x-status :value="$order->estado" /></div><p class="text-small text-pre">{{ data_get($order, 'indicaciones') }}</p><div class="button-row">
                    @if(auth()->user()->hasPermission('laboratorio.consultar') || auth()->user()->hasPermission('laboratorio.procesar'))<a class="button button-small" href="{{ route('laboratorio.show', ['orden' => $order->id]) }}">Ver orden</a>@endif
                    @if($canOrder && $order->estado === 'Completada')<form method="POST" action="{{ route('clinica.publish', ['orden' => $order->id]) }}" data-submit-form data-confirm="¿Deseas publicar los resultados de esta orden en el portal del paciente?">@csrf<button class="button button-primary button-small" type="submit">Publicar al paciente</button></form>@endif
                </div></article>@empty<p class="text-muted">No hay órdenes de laboratorio para esta consulta.</p>@endforelse
                @if($consultation && $canOrder)<details class="disclosure" @if(old('_section') === 'order') open @endif><summary>Solicitar análisis</summary><form method="POST" action="{{ route('clinica.order', ['consulta' => $consultation->id]) }}" class="form-stack" data-submit-form>@csrf<input type="hidden" name="_section" value="order"><x-field name="analisis_ids" label="Análisis a solicitar" type="select" :multiple="true" :options="$analyses" :required="true" hint="Selecciona uno o varios análisis." /><x-field name="indicaciones" id="order-instructions" label="Indicaciones para laboratorio" type="textarea" /><button class="button button-primary" type="submit">Crear orden</button></form></details>@endif
            </section>
        </div>
        <aside class="panel"><h2>Datos del paciente</h2><dl class="detail-list"><div><dt>Paciente</dt><dd>{{ $appointment->paciente }}</dd></div><div><dt>Documento</dt><dd>{{ data_get($patient, 'numero_documento', data_get($patient, 'ci', data_get($patient, 'documento', '—'))) }}</dd></div><div><dt>Fecha de nacimiento</dt><dd>{{ data_get($patient, 'fecha_nacimiento') ?: 'No registrada.' }}</dd></div><div><dt>Teléfono</dt><dd>{{ data_get($patient, 'telefono') ?: 'No registrado.' }}</dd></div><div><dt>Médico tratante</dt><dd>{{ $appointment->medico }}</dd></div><div><dt>Fecha de atención</dt><dd>{{ \Illuminate\Support\Carbon::parse($appointment->inicio)->format('d/m/Y · H:i') }}</dd></div></dl></aside>
    </div>
@endsection

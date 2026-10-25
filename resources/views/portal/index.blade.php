@extends('layouts.app')
@section('title', 'Mi salud')
@section('content')
    <x-page-heading title="Mi salud" description="Tus citas, diagnósticos y resultados en un solo lugar."><a class="button" href="{{ route('portal.profile') }}"><x-icon name="user" />Mis datos</a>@if(auth()->user()->hasPermission('citas.propias'))<a class="button button-primary" href="{{ route('agenda.create') }}"><x-icon name="plus" />Reservar cita</a>@endif</x-page-heading>
    <div class="section-stack">
        <section><div class="panel-heading"><h2>Mis citas</h2>@if(auth()->user()->hasPermission('citas.propias'))<a href="{{ route('agenda.index') }}">Ver agenda <x-icon name="arrow-right" /></a>@endif</div><div class="table-panel">@include('partials.appointments')</div></section>
        <div class="two-column">
            <section class="panel"><div class="panel-heading"><h2>Mis resultados de laboratorio</h2><x-icon name="flask" /></div>
                @forelse($results as $result)<article class="record-card"><h3>{{ $result->nombre }}</h3><p class="record-meta">{{ \Illuminate\Support\Carbon::parse($result->fecha_registro)->format('d/m/Y · H:i') }}<span class="badge badge-success">Publicado</span></p><p class="text-pre">{{ $result->resultado }}</p>@if($result->observaciones)<p class="field-hint text-pre">{{ $result->observaciones }}</p>@endif @if(data_get($result, 'archivo_referencia'))<p style="margin-top: 12px"><a class="button button-small" href="{{ route('laboratorio.download', ['resultado' => $result->id]) }}"><x-icon name="download" />Descargar archivo</a></p>@endif</article>@empty<x-empty-state title="Aún no tienes resultados publicados" message="Cuando tu médico publique un resultado, podrás consultarlo aquí." icon="flask" />@endforelse
            </section>
            <section class="panel"><div class="panel-heading"><h2>Mis diagnósticos y tratamientos</h2><x-icon name="history" /></div>
                @forelse($diagnoses as $diagnosis)
                    <article class="record-card"><h3>{{ $diagnosis->descripcion }}</h3><p class="record-meta">{{ \Illuminate\Support\Carbon::parse($diagnosis->fecha_atencion)->format('d/m/Y') }}</p>@if(data_get($diagnosis, 'codigo_clasificacion'))<p class="text-small text-muted">Clasificación: {{ $diagnosis->codigo_clasificacion }}</p>@endif
                        @foreach(data_get($diagnosis, 'treatments', []) as $treatment)
                            <div class="record-card"><p class="text-strong text-small">{{ $treatment->descripcion }}</p><p class="text-pre text-small">{{ $treatment->indicaciones }}</p>@if($treatment->fecha_inicio || $treatment->fecha_fin)<p class="field-hint">{{ $treatment->fecha_inicio ? \Illuminate\Support\Carbon::parse($treatment->fecha_inicio)->format('d/m/Y') : 'Sin fecha de inicio' }}@if($treatment->fecha_fin) · Hasta {{ \Illuminate\Support\Carbon::parse($treatment->fecha_fin)->format('d/m/Y') }} @endif</p>@endif</div>
                        @endforeach
                    </article>
                @empty<p class="text-muted">Aún no hay diagnósticos registrados en tu historia.</p>@endforelse
            </section>
        </div>
    </div>
@endsection

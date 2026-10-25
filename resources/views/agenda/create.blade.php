@extends('layouts.app')
@section('title', 'Nueva cita')
@section('content')
    <x-page-heading title="Nueva cita" description="Selecciona la especialidad, el médico y un horario disponible." :back="route('agenda.index')" />
    <section class="panel form-panel">
        <form method="POST" action="{{ route('agenda.store') }}" class="form-stack" data-submit-form data-availability="{{ route('agenda.availability') }}">
            @csrf
            <div class="form-grid">
                <div class="grid-full"><x-field name="paciente_id" label="Paciente" type="select" :options="$patients" :required="true" :value="count($patients) === 1 ? array_key_first((array) $patients) : null" /></div>
                <x-field name="especialidad_id" label="Especialidad" type="select" :options="$specialties" :required="true" />
                <x-field name="medico_id" label="Médico" type="select" :options="$doctors" :required="true" />
                <x-field name="fecha" label="Fecha" type="date" :value="request('fecha', now()->toDateString())" :min="now()->toDateString()" :required="true" />
                <x-field name="inicio" label="Horario disponible" type="select" :required="true" :data-selected="old('inicio', '')" />
                <div class="grid-full"><p class="availability-status" data-availability-status role="status" aria-live="polite">Selecciona médico, especialidad y fecha para consultar los horarios.</p><button class="button button-small" type="button" data-availability-retry hidden>Volver a consultar</button><noscript><p class="alert alert-warning">Activa JavaScript para consultar los horarios disponibles y reservar una cita.</p></noscript></div>
                <div class="grid-full"><x-field name="motivo_consulta" label="Motivo de consulta" type="textarea" maxlength="2000" placeholder="Describe brevemente el motivo de tu cita (opcional)." /></div>
            </div>
            <div class="form-actions"><button class="button button-primary" type="submit" data-book-appointment><x-icon name="calendar" />Reservar cita</button><a class="button" href="{{ route('agenda.index') }}">Cancelar</a></div>
        </form>
    </section>
@endsection

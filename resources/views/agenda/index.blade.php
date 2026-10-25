@extends('layouts.app')
@section('title', 'Agenda médica')
@section('content')
    <x-page-heading title="Agenda médica" description="Organiza las citas y consulta su estado.">
        @if(auth()->user()->hasPermission('citas.gestionar') || auth()->user()->hasPermission('citas.propias'))<a class="button button-primary" href="{{ route('agenda.create') }}"><x-icon name="plus" />Nueva cita</a>@endif
    </x-page-heading>
    <x-stats :items="$stats ?? []" />
    <form class="filters" method="GET" action="{{ route('agenda.index') }}"><div class="filter-grid">
        <x-field name="medico_id" label="Médico" type="select" :options="$doctors" :value="request('medico_id')" />
        <x-field name="fecha" label="Fecha" type="date" :value="request('fecha')" />
        <x-field name="estado" label="Estado" type="select" :options="['Programada' => 'Programada', 'Confirmada' => 'Confirmada', 'Cancelada' => 'Cancelada', 'Inasistencia' => 'Inasistencia', 'Atendida' => 'Atendida']" :value="request('estado')" />
        <div class="filter-actions"><button class="button button-primary" type="submit"><x-icon name="search" />Filtrar</button><a class="button" href="{{ route('agenda.index') }}">Limpiar</a></div>
    </div></form>
    <div class="table-panel">@include('partials.appointments')</div>
@endsection

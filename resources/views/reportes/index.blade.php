@extends('layouts.app')
@section('title', 'Reportes')
@section('content')
    <x-page-heading title="Reportes" description="Consulta la actividad de la clínica con los filtros que necesitas."><button class="button" type="button" data-print hidden><x-icon name="file" />Imprimir reporte</button></x-page-heading>
    <form class="filters" method="GET" action="{{ route('reportes.index') }}"><div class="filter-grid">
        <x-field name="tipo" label="Tipo de reporte" type="select" :options="$types" :value="$type" :required="true" />
        <x-field name="desde" label="Desde" type="date" :value="request('desde', now()->startOfMonth()->toDateString())" /><x-field name="hasta" label="Hasta" type="date" :value="request('hasta', now()->toDateString())" />
        <x-field name="medico_id" label="Médico" type="select" :options="$doctors" :value="request('medico_id')" /><x-field name="especialidad_id" label="Especialidad" type="select" :options="$specialties" :value="request('especialidad_id')" />
        <x-field name="estado" label="Estado" type="select" :options="in_array($type, ['ordenes', 'resultados'], true) ? ['Pendiente' => 'Pendiente', 'En proceso' => 'En proceso', 'Completada' => 'Completada', 'Cancelada' => 'Cancelada'] : ['Programada' => 'Programada', 'Confirmada' => 'Confirmada', 'Atendida' => 'Atendida', 'Inasistencia' => 'Inasistencia', 'Cancelada' => 'Cancelada']" :value="request('estado')" />
        <div class="filter-actions"><button class="button button-primary" type="submit"><x-icon name="search" />Consultar</button><a class="button" href="{{ route('reportes.index', ['tipo' => $type]) }}">Limpiar</a></div>
    </div></form>
    <x-stats :items="['Total de registros' => $total]" />
    <div class="table-panel">
        @if(count($rows))<div class="table-scroll" role="region" aria-label="Resultados del reporte" tabindex="0"><table><caption>{{ $types[$type] ?? 'Resultados del reporte' }}</caption><thead><tr>@foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@foreach($rows as $row)<tr>@foreach($columns as $field => $label)<td>@if($field === 'estado')<x-status :value="data_get($row, $field)" />@else{{ data_get($row, $field) ?? '—' }}@endif</td>@endforeach</tr>@endforeach</tbody></table></div>@else<x-empty-state title="No hay resultados para esta consulta" message="Ajusta las fechas o los filtros y vuelve a consultar el reporte." icon="chart" />@endif
        {{ $rows->withQueryString()->links('pagination.default') }}
    </div>
@endsection

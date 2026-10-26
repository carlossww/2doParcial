@extends('layouts.app')
@section('title', $title)
@section('content')
    <x-page-heading :title="$title" :description="$isHistory ? 'Historial de citas, incluidas las pasadas y canceladas.' : 'Consulta las citas por fecha y estado.'" :back="$backUrl" />
    <form method="GET" action="{{ $filterUrl }}" class="filters">
        <div class="filter-grid">
            <x-field name="desde" label="Desde" type="date" :value="$filters['desde'] ?? null" />
            <x-field name="hasta" label="Hasta" type="date" :value="$filters['hasta'] ?? null" />
            @if($doctors !== null)
                <x-field name="medico_id" label="Médico" type="select" :options="['' => 'Todos los médicos'] + $doctors" :value="$filters['medico_id'] ?? null" />
            @endif
            <x-field name="estado" label="Estado de cita" type="select" :options="['' => 'Todos los estados'] + $states" :value="$filters['estado'] ?? null" />
            <div class="filter-actions">
                <button type="submit" class="button button-primary"><x-icon name="search" />Filtrar</button>
                <a class="button" href="{{ $filterUrl }}">Limpiar</a>
            </div>
        </div>
    </form>
    <p class="text-muted">{{ $rows->total() }} {{ $rows->total() === 1 ? 'cita encontrada' : 'citas encontradas' }}.</p>
    <div class="table-panel">
        @if($rows->count())
            <div class="table-scroll" role="region" aria-label="{{ $title }}" tabindex="0">
                <table>
                    <caption class="sr-only">{{ $title }}</caption>
                    <thead><tr><th scope="col">Código</th><th scope="col">Fecha y hora</th><th scope="col">{{ $isHistory ? 'Médico' : 'Paciente' }}</th><th scope="col">Especialidad</th><th scope="col">Estado</th><th scope="col">Registrada el</th>@if($showReason)<th scope="col">Motivo de consulta</th>@endif</tr></thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>{{ $row->codigo }}</td>
                                <td class="cell-date">{{ \App\Modules\Shared\Clinic::date($row->inicio)->format('d/m/Y H:i') }}–{{ \App\Modules\Shared\Clinic::date($row->fin)->format('H:i') }}</td>
                                <td>{{ $isHistory ? $row->medico : $row->paciente }}@if(!$isHistory && $doctors !== null)<br><span class="text-muted">{{ $row->medico }}</span>@endif</td>
                                <td>{{ $row->especialidad }}</td>
                                <td><x-status :value="$row->estado" /></td>
                                <td class="cell-date">{{ \App\Modules\Shared\Clinic::date($row->creada_en)->format('d/m/Y H:i') }}</td>
                                @if($showReason)<td>{{ $row->motivo_consulta ?: '—' }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $rows->links('pagination.default') }}
        @else
            <x-empty-state title="Sin citas para mostrar" message="No hay citas que coincidan con los filtros seleccionados." icon="calendar" />
        @endif
    </div>
@endsection

@if($appointments->count())
    <div class="table-scroll" role="region" aria-label="Listado de citas" tabindex="0"><table><caption class="sr-only">Citas médicas</caption><thead><tr><th scope="col">Código</th><th scope="col">Paciente</th><th scope="col">Médico</th><th scope="col">Especialidad</th><th scope="col">Fecha y hora</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody>
        @foreach($appointments as $appointment)<tr>
            <td class="text-strong">{{ $appointment->codigo }}</td><td>{{ $appointment->paciente }}</td><td>{{ $appointment->medico }}</td><td>{{ $appointment->especialidad }}</td>
            <td class="cell-date"><time datetime="{{ $appointment->inicio }}">{{ \Illuminate\Support\Carbon::parse($appointment->inicio)->format('d/m/Y') }}<small>{{ \Illuminate\Support\Carbon::parse($appointment->inicio)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($appointment->fin)->format('H:i') }}</small></time></td>
            <td><x-status :value="$appointment->estado" /></td><td class="cell-actions"><a class="button button-small" href="{{ route($appointmentRoute ?? 'agenda.show', ['cita' => $appointment->id]) }}" aria-label="Ver cita {{ $appointment->codigo }}"><x-icon name="arrow-right" />{{ ($appointmentRoute ?? '') === 'clinica.show' ? 'Ver atención' : 'Ver detalle' }}</a></td>
        </tr>@endforeach
    </tbody></table></div>
    {{ $appointments->withQueryString()->links('pagination.default') }}
@else
    <x-empty-state title="No hay citas para mostrar" message="Las citas aparecerán aquí cuando estén registradas. Si aplicaste filtros, prueba con otra selección." icon="calendar" />
@endif

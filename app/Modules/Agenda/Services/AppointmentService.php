<?php

namespace App\Modules\Agenda\Services;

use App\Models\User;
use App\Modules\Shared\Clinic;
use Illuminate\Support\Facades\DB;
use stdClass;

class AppointmentService
{
    /**
     * Revalida el bloque al guardar; la disponibilidad de pantalla puede haber cambiado.
     * La cita y el evento generado por PostgreSQL se confirman o revierten juntos.
     *
     * @param  array{paciente_id: int|string, medico_id: int|string, especialidad_id: int|string, inicio: string, motivo_consulta?: ?string}  $data
     */
    public function create(User $user, array $data): int
    {
        if ($user->isPatient()) {
            Clinic::authorize($user, 'citas.propias');
            abort_unless((int) $data['paciente_id'] === Clinic::patientId($user), 404);
        } else {
            Clinic::authorize($user, 'citas.gestionar');
        }

        return Clinic::transaction($user, function () use ($user, $data) {
            $this->lockResources((int) $data['medico_id'], (int) $data['paciente_id']);
            $this->validateSlot($data);

            return DB::table('cita')->insertGetId(['paciente_id' => $data['paciente_id'], 'medico_id' => $data['medico_id'], 'especialidad_id' => $data['especialidad_id'], 'inicio' => Clinic::date($data['inicio']), 'fin' => Clinic::date($data['inicio'])->addMinutes(30), 'estado' => 'Programada', 'motivo_consulta' => $data['motivo_consulta'] ?? null, 'creada_por' => $user->id, 'modificada_por' => $user->id]);
        });
    }

    public function action(User $user, int $id, array $data): void
    {
        $initial = Clinic::appointments($user)->where('c.id', $id)->first();
        abort_unless($initial, 404);
        if ($user->isPatient()) {
            Clinic::authorize($user, 'citas.propias');
            if (! in_array($data['action'], ['confirmar', 'cancelar', 'reprogramar'], true)) {
                abort(403);
            }
        } elseif ($user->isDoctor()) {
            Clinic::authorize($user, 'atencion.registrar');
            abort_unless($data['action'] === 'atendida', 403);
        } else {
            Clinic::authorize($user, 'citas.gestionar');
        }
        Clinic::transaction($user, function () use ($user, $id, $initial, $data) {
            $this->lockResources($initial->medico_id, $initial->paciente_id);
            $cita = DB::table('cita')->where('id', $id)->lockForUpdate()->first();
            if (! in_array($cita->estado, ['Programada', 'Confirmada'], true)) {
                Clinic::fail('La cita está finalizada y conserva su historial.');
            }
            if ($user->isPatient() && Clinic::date($cita->inicio)->lessThanOrEqualTo(now())) {
                Clinic::fail('Solo puedes gestionar una cita antes de su inicio.');
            }
            $changes = ['modificada_por' => $user->id];
            switch ($data['action']) {
                case 'confirmar':
                    if ($cita->estado !== 'Programada') {
                        Clinic::fail('Solo una cita programada puede confirmarse.');
                    }
                    $changes['estado'] = 'Confirmada';
                    break;
                case 'cancelar':
                    $changes['estado'] = 'Cancelada';
                    $changes['motivo_cancelacion'] = $data['motivo_cancelacion'];
                    break;
                case 'reprogramar':
                    $slot = ['medico_id' => $cita->medico_id, 'paciente_id' => $cita->paciente_id, 'especialidad_id' => $cita->especialidad_id, 'inicio' => $data['inicio']];
                    $this->validateSlot($slot, $id);
                    $changes['inicio'] = Clinic::date($data['inicio']);
                    $changes['fin'] = Clinic::date($data['inicio'])->addMinutes(30);
                    $changes['estado'] = 'Programada';
                    break;
                case 'atendida':
                    if (Clinic::date($cita->inicio)->isFuture()) {
                        Clinic::fail('La atención puede registrarse desde el inicio de la cita.');
                    }
                    $changes['estado'] = 'Atendida';
                    break;
                case 'inasistencia':
                    if (Clinic::date($cita->fin)->isFuture()) {
                        Clinic::fail('La inasistencia se registra cuando termina el bloque.');
                    }
                    $changes['estado'] = 'Inasistencia';
                    break;
                default:
                    Clinic::fail('Acción no válida.');
            }
            DB::table('cita')->where('id', $id)->update($changes);
        });
    }

    /** Mantiene el mismo orden de bloqueo en reserva, cambios de estado y reprogramación. */
    private function lockResources(int $doctorId, int $patientId): void
    {
        abort_unless(DB::table('medico')->where('id', $doctorId)->lockForUpdate()->first(), 404);
        abort_unless(DB::table('paciente')->where('id', $patientId)->lockForUpdate()->first(), 404);
    }

    /**
     * Usa intervalos [inicio, fin): dos bloques contiguos no se superponen.
     * PostgreSQL añade la garantía de exclusión frente a conexiones concurrentes.
     *
     * @param  array{paciente_id: int|string, medico_id: int|string, especialidad_id: int|string, inicio: string}  $data
     */
    private function validateSlot(array $data, ?int $exclude = null): void
    {
        $start = Clinic::date($data['inicio']);
        $end = $start->addMinutes(30);
        if ($start->lessThanOrEqualTo(now())) {
            Clinic::fail('Selecciona un bloque futuro.', 'inicio');
        }
        if (! DB::table('paciente')->where('id', $data['paciente_id'])->where('activo', true)->exists()) {
            Clinic::fail('El paciente está desactivado.', 'paciente_id');
        }
        $valid = DB::table('medico as m')->join('usuario as u', 'u.id', '=', 'm.usuario_id')->join('rol as r', 'r.id', '=', 'u.rol_id')->join('medico_especialidad as me', 'me.medico_id', '=', 'm.id')->join('especialidad as e', 'e.id', '=', 'me.especialidad_id')->where('m.id', $data['medico_id'])->where('e.id', $data['especialidad_id'])->where('m.activo', true)->where('u.activo', true)->where('r.activo', true)->where('e.activo', true)->where('me.activo', true)->exists();
        if (! $valid) {
            Clinic::fail('El médico no está disponible para esta especialidad.', 'medico_id');
        }
        $schedule = DB::table('horario')->where('medico_id', $data['medico_id'])->where('activo', true)->where('dia_semana', $start->isoWeekday())->where('hora_inicio', '<=', $start->format('H:i:s'))->where('hora_fin', '>=', $end->format('H:i:s'))->get();
        $aligned = $start->isSameDay($end) && $schedule->contains(function (stdClass $h) use ($start) {
            $base = Clinic::date($start->format('Y-m-d').' '.$h->hora_inicio);

            return ((int) $base->diffInSeconds($start)) % 1800 === 0 && $start->micro === 0;
        });
        if (! $aligned) {
            Clinic::fail('Elige un bloque completo de 30 minutos del horario médico.', 'inicio');
        }
        $conflict = DB::table('cita')->whereIn('estado', ['Programada', 'Confirmada', 'Atendida'])->where('inicio', '<', $end)->where('fin', '>', $start)->where(function ($q) use ($data) {
            $q->where('medico_id', $data['medico_id'])->orWhere('paciente_id', $data['paciente_id']);
        });
        if ($exclude) {
            $conflict->where('id', '<>', $exclude);
        } if ($conflict->exists()) {
            Clinic::fail('El médico o el paciente ya tienen una cita en ese intervalo.', 'inicio');
        }
    }
}

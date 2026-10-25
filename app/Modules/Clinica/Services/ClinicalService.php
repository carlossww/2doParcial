<?php

namespace App\Modules\Clinica\Services;

use App\Models\User;
use App\Modules\Shared\Clinic;
use Illuminate\Support\Facades\DB;
use stdClass;

class ClinicalService
{
    public function appointment(User $user, int $id): stdClass
    {
        Clinic::authorize($user, 'atencion.registrar');
        $appointment = Clinic::appointments($user)->where('c.id', $id)->first();
        abort_unless($appointment, 404);

        return $appointment;
    }

    public function consultation(User $user, int $id): stdClass
    {
        Clinic::authorize($user, 'atencion.registrar');
        $record = DB::table('consulta as co')->join('cita as c', 'c.id', '=', 'co.cita_id')->where('co.id', $id)->where('c.medico_id', Clinic::doctorId($user))->select('co.*', 'c.paciente_id')->first();
        abort_unless($record, 404);

        return $record;
    }

    public function history(User $user, int $patientId, array $data): void
    {
        Clinic::clinicalPatient($user, $patientId);
        Clinic::authorize($user, 'atencion.registrar');
        Clinic::transaction($user, function () use ($user, $patientId, $data) {
            DB::table('paciente')->where('id', $patientId)->lockForUpdate()->first();
            $record = DB::table('historia_clinica')->where('paciente_id', $patientId)->first();
            $data['actualizada_por'] = $user->id;
            $data['actualizada_en'] = now();
            if ($record) {
                DB::table('historia_clinica')->where('paciente_id', $patientId)->update($data);
            } else {
                $data['paciente_id'] = $patientId;
                $data['registrada_por'] = $user->id;
                DB::table('historia_clinica')->insert($data);
            }
        });
    }

    public function attend(User $user, int $appointmentId, array $data): int
    {
        $appointment = $this->appointment($user, $appointmentId);

        return Clinic::transaction($user, function () use ($user, $appointment, $data) {
            DB::table('medico')->where('id', $appointment->medico_id)->lockForUpdate()->first();
            DB::table('paciente')->where('id', $appointment->paciente_id)->lockForUpdate()->first();
            DB::table('cita')->where('id', $appointment->id)->lockForUpdate()->first();
            $existing = DB::table('consulta')->where('cita_id', $appointment->id)->first();
            if ($existing) {
                DB::table('consulta')->where('id', $existing->id)->update(['notas_clinicas' => $data['notas_clinicas']]);

                return $existing->id;
            }

            return DB::table('consulta')->insertGetId(['cita_id' => $appointment->id, 'fecha_atencion' => now(), 'notas_clinicas' => $data['notas_clinicas'], 'registrada_por' => $user->id]);
        });
    }

    public function diagnosis(User $user, int $consultationId, array $data): void
    {
        $this->consultation($user, $consultationId);
        Clinic::transaction($user, fn () => DB::table('diagnostico')->insert($data + ['consulta_id' => $consultationId]));
    }

    public function treatment(User $user, int $diagnosisId, array $data): void
    {
        $diagnosis = DB::table('diagnostico')->where('id', $diagnosisId)->first();
        abort_unless($diagnosis, 404);
        $this->consultation($user, $diagnosis->consulta_id);
        Clinic::transaction($user, fn () => DB::table('tratamiento')->insert($data + ['diagnostico_id' => $diagnosisId]));
    }

    public function order(User $user, int $consultationId, array $data): int
    {
        $this->consultation($user, $consultationId);
        Clinic::authorize($user, 'laboratorio.solicitar');

        return Clinic::transaction($user, function () use ($user, $consultationId, $data) {
            if (DB::table('analisis_laboratorio')->whereIn('id', $data['analisis_ids'])->where('activo', true)->count() !== count($data['analisis_ids'])) {
                Clinic::fail('Selecciona análisis activos.', 'analisis_ids');
            }
            $id = DB::table('orden_laboratorio')->insertGetId(['consulta_id' => $consultationId, 'solicitada_por' => $user->id, 'indicaciones' => $data['indicaciones'] ?? null, 'estado' => 'Pendiente']);
            foreach ($data['analisis_ids'] as $analysisId) {
                DB::table('orden_analisis')->insert(['orden_id' => $id, 'analisis_id' => $analysisId]);
            }

            return $id;
        });
    }

    public function publish(User $user, int $orderId): void
    {
        Clinic::authorize($user, 'laboratorio.solicitar');
        Clinic::transaction($user, function () use ($user, $orderId) {
            $order = DB::table('orden_laboratorio')->where('id', $orderId)->where('solicitada_por', $user->id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->estado !== 'Completada') {
                Clinic::fail('Completa todos los análisis antes de publicar la orden.');
            }
            $ids = DB::table('orden_analisis')->where('orden_id', $orderId)->pluck('id');
            DB::table('resultado_laboratorio')->whereIn('orden_analisis_id', $ids)->update(['publicado_por' => $user->id, 'publicado_en' => now()]);
        });
    }
}

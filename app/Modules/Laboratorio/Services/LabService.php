<?php

namespace App\Modules\Laboratorio\Services;

use App\Models\User;
use App\Modules\Shared\Clinic;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LabService
{
    public function orders(User $user): Builder
    {
        Clinic::authorize($user, 'laboratorio.consultar');
        abort_if($user->isPatient(), 403);
        $q = DB::table('orden_laboratorio as o')->join('consulta as co', 'co.id', '=', 'o.consulta_id')->join('cita as c', 'c.id', '=', 'co.cita_id')->join('paciente as p', 'p.id', '=', 'c.paciente_id')->join('usuario as u', 'u.id', '=', 'o.solicitada_por')->select('o.*', 'c.paciente_id', DB::raw("p.nombres || ' ' || p.apellidos as paciente"), DB::raw("u.nombres || ' ' || u.apellidos as medico"));
        if ($user->isDoctor()) {
            Clinic::doctorId($user);
            $q->where('o.solicitada_por', $user->id);
        }

        return $q;
    }

    /**
     * Los adjuntos permanecen privados. Si falla la transacción, se elimina el
     * archivo recién subido; los informes anteriores se conservan para sus versiones.
     *
     * @param  array{resultado: string, observaciones?: ?string, archivo?: UploadedFile}  $data
     */
    public function result(User $user, int $itemId, array $data, ?UploadedFile $file): void
    {
        Clinic::authorize($user, 'laboratorio.procesar');
        $path = $file?->store('laboratorio', 'local');
        try {
            Clinic::transaction($user, function () use ($user, $itemId, $data, $path) {
                $item = DB::table('orden_analisis')->where('id', $itemId)->first();
                abort_unless($item, 404);
                $order = DB::table('orden_laboratorio')->where('id', $item->orden_id)->lockForUpdate()->first();
                if ($order->estado === 'Cancelada') {
                    Clinic::fail('No puedes registrar resultados de una orden cancelada.');
                }
                $existing = DB::table('resultado_laboratorio')->where('orden_analisis_id', $itemId)->lockForUpdate()->first();
                unset($data['archivo']);
                if ($existing && ! $path && $existing->resultado === $data['resultado']
                    && ($existing->observaciones ?? '') === ($data['observaciones'] ?? $existing->observaciones ?? '')) {
                    return;
                }
                $values = $data + ['laboratorista_id' => $user->id, 'fecha_registro' => now()];
                if ($path) {
                    $values['archivo_referencia'] = $path;
                }
                if ($existing) {
                    DB::table('resultado_laboratorio')->where('id', $existing->id)->update($values);
                } else {
                    DB::table('resultado_laboratorio')->insert($values + ['orden_analisis_id' => $itemId]);
                }
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }

    public function action(User $user, int $orderId, array $data): void
    {
        Clinic::authorize($user, 'laboratorio.procesar');
        Clinic::transaction($user, function () use ($orderId, $data) {
            $order = DB::table('orden_laboratorio')->where('id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404);
            if (! in_array($order->estado, ['Pendiente', 'En proceso'], true)) {
                Clinic::fail('La orden está finalizada.');
            }
            DB::table('orden_laboratorio')->where('id', $orderId)->update($data);
        });
    }
}

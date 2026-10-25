<?php

namespace App\Modules\Shared;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Clinic
{
    public const STATES = ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'];

    public static function authorize(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403, 'No tienes autorización para esta operación.');
    }

    public static function fail(string $message, string $field = 'operacion'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /**
     * El contexto de auditoría solo vive durante la transacción. Los disparadores
     * PostgreSQL registran actor e IP sin crear eventos duplicados en PHP.
     */
    public static function transaction(User $user, Closure $callback): mixed
    {
        return DB::transaction(function () use ($user, $callback) {
            DB::select("SELECT set_config('app.usuario_id', ?, true), set_config('app.direccion_ip', ?, true)", [(string) $user->id, request()->ip() ?? '127.0.0.1']);

            return $callback();
        }, 3);
    }

    /** Obtiene el vínculo verificado de la sesión, nunca un ID enviado por el navegador. */
    public static function patientId(User $user): int
    {
        abort_unless($user->isPatient() && $user->paciente_id && $user->paciente_verificado_en, 403, 'La cuenta necesita un vínculo verificado por recepción.');

        return (int) $user->paciente_id;
    }

    public static function doctorId(User $user): int
    {
        $id = DB::table('medico')->where('usuario_id', $user->id)->where('activo', true)->value('id');
        abort_unless($id, 403, 'No tienes un perfil médico activo.');

        return (int) $id;
    }

    /** Aplica permiso y propiedad antes de filtrar, paginar o buscar una cita por ID. */
    public static function appointments(User $user, string $permission = 'agenda.consultar'): Builder
    {
        $query = DB::table('cita as c')->join('paciente as p', 'p.id', '=', 'c.paciente_id')->join('medico as m', 'm.id', '=', 'c.medico_id')->join('usuario as u', 'u.id', '=', 'm.usuario_id')->join('especialidad as e', 'e.id', '=', 'c.especialidad_id')->select('c.*', DB::raw("p.nombres || ' ' || p.apellidos as paciente"), DB::raw("u.nombres || ' ' || u.apellidos as medico"), 'e.nombre as especialidad');
        if ($user->isPatient()) {
            self::authorize($user, 'citas.propias');
            $query->where('c.paciente_id', self::patientId($user));
        } elseif ($user->isDoctor()) {
            self::authorize($user, $permission);
            $query->where('c.medico_id', self::doctorId($user));
        } else {
            self::authorize($user, $permission);
        }

        return $query;
    }

    public static function clinicalPatient(User $user, int $patientId): void
    {
        self::authorize($user, 'historia.consultar');
        abort_unless(DB::table('cita')->where('paciente_id', $patientId)->where('medico_id', self::doctorId($user))->whereIn('estado', ['Programada', 'Confirmada', 'Atendida'])->exists(), 404);
    }

    public static function date(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'America/La_Paz')->setTimezone('America/La_Paz');
    }

    public static function doctors(): array
    {
        return DB::table('medico as m')->join('usuario as u', 'u.id', '=', 'm.usuario_id')->join('rol as r', 'r.id', '=', 'u.rol_id')->where('m.activo', true)->where('u.activo', true)->where('r.activo', true)->orderBy('u.apellidos')->pluck(DB::raw("u.nombres || ' ' || u.apellidos as label"), 'm.id')->all();
    }

    public static function specialties(): array
    {
        return DB::table('especialidad')->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all();
    }
}

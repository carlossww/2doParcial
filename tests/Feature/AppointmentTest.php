<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    public function test_reception_books_a_future_30_minute_appointment_and_records_its_actor(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $reception = $this->account('recepcion');

        $response = $this->actingAs($reception)->post(route('agenda.store'), $payload);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cita', [
            'paciente_id' => $payload['paciente_id'], 'medico_id' => $payload['medico_id'],
            'inicio' => $payload['inicio'], 'estado' => 'Programada', 'creada_por' => $reception->getKey(),
        ]);
        $appointment = DB::table('cita')->where('motivo_consulta', 'Control preventivo')->first();
        $this->assertSame(1800.0, Carbon::parse($appointment->inicio)->diffInSeconds(Carbon::parse($appointment->fin), true));
        $this->assertDatabaseHas('bitacora', [
            'entidad' => 'cita', 'registro_id' => $appointment->id, 'usuario_id' => $reception->getKey(), 'accion' => 'INSERT',
        ]);
    }

    public function test_available_slots_omit_an_existing_reservation_and_keep_the_next_block(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $this->existingAppointment($payload);

        $response = $this->actingAs($this->account('recepcion'))->getJson(route('agenda.availability', [
            'medico_id' => $payload['medico_id'], 'especialidad_id' => $payload['especialidad_id'],
            'fecha' => Carbon::parse($payload['inicio'])->format('Y-m-d'),
        ]));

        $response->assertOk()->assertJsonStructure(['slots' => [['inicio', 'label']]]);
        $starts = array_map(static fn (array $slot): string => Carbon::parse($slot['inicio'])->format('H:i'), $response->json('slots'));
        $this->assertNotContains('09:00', $starts);
        $this->assertContains('09:30', $starts);
    }

    /** @return array<string, array{string}> */
    public static function invalidStarts(): array
    {
        return ['pasado' => ['past'], 'fuera del horario' => ['outside'], 'bloque sin alinear' => ['unaligned']];
    }

    #[DataProvider('invalidStarts')]
    public function test_returns_422_for_invalid_start_without_creating_an_appointment(string $case): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $start = Carbon::parse($payload['inicio']);
        $payload['inicio'] = match ($case) {
            'past' => Carbon::now('America/La_Paz')->subDay()->setTime(9, 0)->toIso8601String(),
            'outside' => $start->setTime(13, 0)->toIso8601String(),
            'unaligned' => $start->setTime(9, 15)->toIso8601String(),
        };

        $response = $this->actingAs($this->account('recepcion'))->postJson(route('agenda.store'), $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('inicio');
        $this->assertDatabaseCount('cita', 0);
    }

    public function test_returns_422_for_an_inactive_patient_without_creating_an_appointment(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        DB::table('paciente')->where('id', $payload['paciente_id'])->update(['activo' => false]);

        $response = $this->actingAs($this->account('recepcion'))->postJson(route('agenda.store'), $payload);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('cita', 0);
    }

    public function test_returns_422_for_a_specialty_not_assigned_to_the_doctor(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $payload['especialidad_id'] = (int) DB::table('especialidad')->insertGetId(['nombre' => 'Neurología']);

        $response = $this->actingAs($this->account('recepcion'))->postJson(route('agenda.store'), $payload);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('cita', 0);
    }

    public function test_returns_422_for_an_overlapping_doctor_reservation_without_changing_the_existing_one(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $existingId = $this->existingAppointment($payload);
        $payload['paciente_id'] = $this->otherPatient();

        $response = $this->actingAs($this->account('recepcion'))->postJson(route('agenda.store'), $payload);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('cita', 1);
        $this->assertDatabaseHas('cita', ['id' => $existingId, 'estado' => 'Programada']);
    }

    public function test_cancellation_requires_a_motive_and_preserves_the_reservation_on_failure(): void
    {
        $this->seedVidaSalud();
        $id = $this->existingAppointment($this->appointmentPayload());

        $response = $this->actingAs($this->account('recepcion'))->postJson(route('agenda.action', ['cita' => $id]), [
            'action' => 'cancelar', 'motivo_cancelacion' => '   ',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('motivo_cancelacion');
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada']);
    }

    public function test_patient_can_cancel_their_own_future_appointment(): void
    {
        $this->seedVidaSalud();
        $id = $this->existingAppointment($this->appointmentPayload());

        $response = $this->actingAs($this->account('paciente'))->post(route('agenda.action', ['cita' => $id]), [
            'action' => 'cancelar', 'motivo_cancelacion' => 'Viaje familiar',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Cancelada', 'motivo_cancelacion' => 'Viaje familiar']);
    }

    public function test_reprogramming_updates_the_time_and_returns_the_appointment_to_programada(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $id = $this->existingAppointment($payload);
        DB::table('cita')->where('id', $id)->update(['estado' => 'Confirmada']);
        $newStart = Carbon::parse($payload['inicio'])->addHour()->toIso8601String();

        $response = $this->actingAs($this->account('paciente'))->post(route('agenda.action', ['cita' => $id]), [
            'action' => 'reprogramar', 'inicio' => $newStart,
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada', 'inicio' => $newStart]);
        $this->assertDatabaseCount('cita', 1);
    }

    public function test_patient_cannot_cancel_another_patients_appointment(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $payload['paciente_id'] = $this->otherPatient();
        $id = $this->existingAppointment($payload);

        $response = $this->actingAs($this->account('paciente'))->post(route('agenda.action', ['cita' => $id]), [
            'action' => 'cancelar', 'motivo_cancelacion' => 'Intento ajeno',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada', 'motivo_cancelacion' => null]);
    }

    public function test_patient_cannot_book_an_appointment_for_another_patient(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $payload['paciente_id'] = $this->otherPatient();

        $response = $this->actingAs($this->account('paciente'))->post(route('agenda.store'), $payload);

        $response->assertNotFound();
        $this->assertDatabaseCount('cita', 0);
    }

    public function test_doctor_cannot_read_another_doctors_appointment(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $payload['medico_id'] = $this->otherDoctor();
        DB::table('medico_especialidad')->insert(['medico_id' => $payload['medico_id'], 'especialidad_id' => $payload['especialidad_id']]);
        DB::table('horario')->insert(['medico_id' => $payload['medico_id'], 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00']);
        $id = $this->existingAppointment($payload);

        $this->actingAs($this->account('medico'))->get(route('agenda.show', ['cita' => $id]))->assertNotFound();
    }

    public function test_patient_cannot_promote_their_appointment_to_attended(): void
    {
        $this->seedVidaSalud();
        $id = $this->existingAppointment($this->appointmentPayload());

        $response = $this->actingAs($this->account('paciente'))->post(route('agenda.action', ['cita' => $id]), ['action' => 'atendida']);

        $response->assertForbidden();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada']);
    }

    public function test_inactive_doctor_gets_403_instead_of_falling_back_to_the_general_agenda(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $ownDoctorId = $payload['medico_id'];
        $payload['medico_id'] = $this->otherDoctor();
        DB::table('medico_especialidad')->insert(['medico_id' => $payload['medico_id'], 'especialidad_id' => $payload['especialidad_id']]);
        DB::table('horario')->insert(['medico_id' => $payload['medico_id'], 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00']);
        $this->existingAppointment($payload);
        DB::table('medico')->where('id', $ownDoctorId)->update(['activo' => false]);

        $this->actingAs($this->account('medico'))->get(route('agenda.index'))->assertForbidden();
    }
}

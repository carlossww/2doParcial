<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class AcceptanceRulesTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    public function test_laboratory_read_permission_is_separate_from_processing_permission(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();
        $laboratory = $this->account('laboratorio');
        $permissionId = DB::table('permiso')->where('codigo', 'laboratorio.procesar')->value('id');
        DB::table('rol_permiso')->where('rol_id', $laboratory->rol_id)->where('permiso_id', $permissionId)->delete();
        $this->actingAs($laboratory)->get(route('laboratorio.show', $order['order']))->assertOk();
        $this->post(route('laboratorio.result', $order['items'][0]), ['resultado' => 'Intento sin permiso'])->assertForbidden();
        $this->assertDatabaseCount('resultado_laboratorio', 0);
        DB::table('medico')->where('usuario_id', $this->account('medico')->id)->update(['activo' => false]);
        $this->actingAs($this->account('medico'))->get(route('laboratorio.show', $order['order']))->assertForbidden();
    }

    public function test_result_reports_require_clinical_permission_and_doctor_ownership(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();
        DB::table('resultado_laboratorio')->insert([
            'orden_analisis_id' => $order['items'][0], 'resultado' => 'VALOR-CLINICO-REPORTE',
            'laboratorista_id' => $this->account('laboratorio')->id,
        ]);
        $filters = ['tipo' => 'resultados', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString(), 'estado' => 'Completada'];
        $this->actingAs($this->account('medico'))->get(route('reportes.index', $filters))
            ->assertOk()->assertViewHas('total', 1)->assertSee('VALOR-CLINICO-REPORTE');
        $this->actingAs($this->account('laboratorio'))->get(route('reportes.index', $filters))
            ->assertOk()->assertViewHas('total', 1);
        $doctorId = $this->otherDoctor();
        $otherUserId = DB::table('medico')->where('id', $doctorId)->value('usuario_id');
        $this->actingAs(User::findOrFail($otherUserId))->get(route('reportes.index', $filters))
            ->assertOk()->assertViewHas('total', 0)->assertDontSee('VALOR-CLINICO-REPORTE');
        $this->actingAs($this->account('admin'))->getJson(route('reportes.index', $filters))
            ->assertUnprocessable()->assertDontSee('VALOR-CLINICO-REPORTE');
    }

    public function test_cp12_doctor_cannot_administer_users_even_through_direct_urls(): void
    {
        $this->seedVidaSalud();
        $this->actingAs($this->account('medico'))->get(route('catalog.index', ['module' => 'usuarios']))->assertForbidden();
        $this->post(route('catalog.store', ['module' => 'usuarios']), [])->assertForbidden();
        $this->assertDatabaseCount('usuario', 5);
    }

    public function test_cp24_adjacent_blocks_both_persist(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $this->existingAppointment($payload);
        $payload['paciente_id'] = $this->otherPatient();
        $payload['inicio'] = Carbon::parse($payload['inicio'])->addMinutes(30)->toIso8601String();
        $this->actingAs($this->account('recepcion'))->postJson(route('agenda.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('cita', 2);
        $this->assertDatabaseCount('evento_cita', 2);
    }

    public function test_cp06_cancelled_appointment_stays_in_history_and_frees_its_block(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $id = $this->existingAppointment($payload);
        $this->actingAs($this->account('recepcion'))->post(route('agenda.action', $id), ['action' => 'cancelar', 'motivo_cancelacion' => 'Paciente no puede asistir'])->assertSessionHasNoErrors();
        $payload['paciente_id'] = $this->otherPatient();
        $this->postJson(route('agenda.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Cancelada']);
        $this->assertDatabaseCount('cita', 2);
        $this->assertDatabaseHas('evento_cita', ['cita_id' => $id, 'estado_anterior' => 'Programada', 'estado_nuevo' => 'Cancelada']);
        $this->get(route('agenda.show', $id))->assertOk()->assertSee('Cancelada')->assertSee('Paciente no puede asistir');
    }

    public function test_cp18_conflicting_reschedule_preserves_original_time_state_and_events(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $id = $this->existingAppointment($payload);
        DB::table('cita')->where('id', $id)->update(['estado' => 'Confirmada']);
        $other = $payload;
        $other['paciente_id'] = $this->otherPatient();
        $other['inicio'] = Carbon::parse($payload['inicio'])->addMinutes(30)->toIso8601String();
        $this->existingAppointment($other);
        $before = DB::table('evento_cita')->count();
        $this->actingAs($this->account('recepcion'))->postJson(route('agenda.action', $id), ['action' => 'reprogramar', 'inicio' => $other['inicio']])->assertUnprocessable();
        $this->assertDatabaseHas('cita', ['id' => $id, 'inicio' => $payload['inicio'], 'estado' => 'Confirmada']);
        $this->assertDatabaseCount('evento_cita', $before);
    }

    public function test_cp17_reducing_an_occupied_schedule_rolls_back(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $this->existingAppointment($payload);
        $schedule = DB::table('horario')->where('medico_id', $payload['medico_id'])->first();
        $this->actingAs($this->account('admin'))->postJson(route('catalog.update', ['module' => 'horarios', 'id' => $schedule->id]), ['medico_id' => $payload['medico_id'], 'dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'activo' => true])->assertUnprocessable();
        $this->assertDatabaseHas('horario', ['id' => $schedule->id, 'hora_inicio' => '09:00:00', 'activo' => true]);
        $this->assertDatabaseCount('cita', 1);
    }

    public function test_cp16_deactivation_requires_resolving_future_visits_and_never_deletes_history(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $id = $this->existingAppointment($payload);
        $patient = DB::table('paciente')->where('id', $payload['paciente_id'])->first();
        $fields = ['ci' => $patient->ci, 'nombres' => $patient->nombres, 'apellidos' => $patient->apellidos, 'fecha_nacimiento' => $patient->fecha_nacimiento, 'telefono' => $patient->telefono, 'activo' => false];
        $this->actingAs($this->account('recepcion'))->postJson(route('catalog.update', ['module' => 'pacientes', 'id' => $patient->id]), $fields)->assertUnprocessable();
        $this->assertDatabaseHas('paciente', ['id' => $patient->id, 'activo' => true]);
        $this->post(route('agenda.action', $id), ['action' => 'cancelar', 'motivo_cancelacion' => 'Resolver cita antes de desactivar'])->assertSessionHasNoErrors();
        $this->postJson(route('catalog.update', ['module' => 'pacientes', 'id' => $patient->id]), $fields)->assertRedirect();
        $this->assertDatabaseHas('paciente', ['id' => $patient->id, 'activo' => false]);
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Cancelada']);
        $this->postJson(route('agenda.store'), $payload)->assertUnprocessable();
    }

    public function test_cp15_an_inactive_doctor_cannot_receive_new_reservations(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        DB::table('medico')->where('id', $payload['medico_id'])->update(['activo' => false]);
        $this->actingAs($this->account('recepcion'))->postJson(route('agenda.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('cita', 0);
    }

    public function test_cp19_absence_before_end_and_reopening_cancelled_visits_are_rejected(): void
    {
        $this->seedVidaSalud();
        $id = $this->existingAppointment($this->appointmentPayload());
        $this->actingAs($this->account('recepcion'))->postJson(route('agenda.action', $id), ['action' => 'inasistencia'])->assertUnprocessable();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada']);
        $this->post(route('agenda.action', $id), ['action' => 'cancelar', 'motivo_cancelacion' => 'Cancelación previa'])->assertSessionHasNoErrors();
        $this->postJson(route('agenda.action', $id), ['action' => 'atendida'])->assertUnprocessable();
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Cancelada']);
    }

    public function test_cp21_reports_include_the_last_day_and_exclude_next_day_and_clinical_content(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $payload['motivo_consulta'] = 'MOTIVO-CLINICO-PRIVADO';
        $id = $this->existingAppointment($payload);
        $second = $payload;
        $second['inicio'] = Carbon::parse($payload['inicio'])->addMinutes(30)->toIso8601String();
        $this->existingAppointment($second);
        $third = $payload;
        $third['inicio'] = Carbon::parse($payload['inicio'])->addWeek()->toIso8601String();
        $this->existingAppointment($third);
        $this->actingAs($this->account('recepcion'))->post(route('agenda.action', $id), ['action' => 'cancelar', 'motivo_cancelacion' => 'Control del reporte'])->assertSessionHasNoErrors();
        $date = Carbon::parse($payload['inicio'])->format('Y-m-d');
        $this->get(route('reportes.index', ['tipo' => 'citas', 'desde' => $date, 'hasta' => $date]))->assertOk()->assertViewHas('total', 2)->assertDontSee('MOTIVO-CLINICO-PRIVADO');
        $this->get(route('reportes.index', ['tipo' => 'citas', 'desde' => $date, 'hasta' => $date, 'estado' => 'Cancelada']))->assertOk()->assertViewHas('total', 1);
        $this->get(route('agenda.index', ['fecha' => $date, 'estado' => 'Cancelada']))->assertOk()->assertViewHas('stats', fn (array $stats): bool => $stats['Citas encontradas'] === 1);
    }

    public function test_revoking_history_permission_blocks_clinical_history_even_with_attention_permission(): void
    {
        $this->seedVidaSalud();
        $id = $this->historicalAppointment();
        $doctor = $this->account('medico');
        $permission = DB::table('permiso')->where('codigo', 'historia.consultar')->value('id');
        DB::table('rol_permiso')->where('rol_id', $doctor->rol_id)->where('permiso_id', $permission)->delete();
        $this->assertTrue($doctor->hasPermission('atencion.registrar'));
        $this->actingAs($doctor)->get(route('clinica.show', $id))->assertForbidden();
    }

    public function test_completed_results_can_be_published_after_the_original_laboratory_author_is_inactive(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();
        $id = DB::table('resultado_laboratorio')->insertGetId(['orden_analisis_id' => $order['items'][0], 'resultado' => 'Resultado correcto', 'laboratorista_id' => $this->account('laboratorio')->id]);
        DB::table('usuario')->where('id', $this->account('laboratorio')->id)->update(['activo' => false]);
        $this->actingAs($this->account('medico'))->post(route('clinica.publish', $order['order']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('resultado_laboratorio', ['id' => $id, 'publicado_por' => $this->account('medico')->id]);
        $this->assertDatabaseHas('bitacora', ['entidad' => 'resultado_laboratorio', 'registro_id' => $id, 'accion' => 'UPDATE', 'usuario_id' => $this->account('medico')->id]);
        $this->assertDatabaseCount('resultado_version', 0);
    }

    public function test_private_attachments_require_publication_and_patient_ownership(): void
    {
        Storage::fake('local');
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();
        $this->actingAs($this->account('laboratorio'))->post(route('laboratorio.result', $order['items'][0]), ['resultado' => 'Informe adjunto', 'archivo' => UploadedFile::fake()->createWithContent('informe.pdf', "%PDF-1.4\nInforme de prueba")])->assertSessionHasNoErrors();
        $result = DB::table('resultado_laboratorio')->first();
        Storage::disk('local')->assertExists($result->archivo_referencia);
        $this->actingAs($this->account('paciente'))->get(route('laboratorio.download', $result->id))->assertNotFound();
        $this->actingAs($this->account('medico'))->get(route('laboratorio.download', $result->id))->assertOk();
        $this->post(route('clinica.publish', $order['order']))->assertSessionHasNoErrors();
        $this->actingAs($this->account('paciente'))->get(route('laboratorio.download', $result->id))->assertOk();
        $otherId = $this->otherPatient();
        $this->account('paciente')->forceFill(['paciente_id' => $otherId])->save();
        $this->actingAs($this->account('paciente'))->get(route('laboratorio.download', $result->id))->assertNotFound();
        $this->actingAs($this->account('admin'))->get(route('laboratorio.download', $result->id))->assertForbidden();
    }

    public function test_valid_treatment_is_saved_for_the_authorized_diagnosis(): void
    {
        $this->seedVidaSalud();
        $consultation = $this->historicalConsultation();
        $diagnosis = DB::table('diagnostico')->insertGetId(['consulta_id' => $consultation, 'descripcion' => 'Diagnóstico de prueba']);
        $this->actingAs($this->account('medico'))->post(route('clinica.treatment', $diagnosis), ['descripcion' => 'Reposo', 'indicaciones' => 'Seguir valoración del médico', 'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->addDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tratamiento', ['diagnostico_id' => $diagnosis, 'descripcion' => 'Reposo']);
    }
}

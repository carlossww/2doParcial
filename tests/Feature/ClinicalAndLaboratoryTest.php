<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsVidaSalud;
use Tests\TestCase;

class ClinicalAndLaboratoryTest extends TestCase
{
    use RefreshDatabase, SeedsVidaSalud;

    /** @return array<string, array{string}> */
    public static function nonClinicalRoles(): array
    {
        return ['administración' => ['admin'], 'recepción' => ['recepcion'], 'laboratorio' => ['laboratorio'], 'paciente' => ['paciente']];
    }

    #[DataProvider('nonClinicalRoles')]
    public function test_non_clinical_roles_cannot_write_consultation_notes(string $login): void
    {
        $this->seedVidaSalud();
        $id = $this->historicalAppointment();

        $response = $this->actingAs($this->account($login))->post(route('clinica.consultation', ['cita' => $id]), ['notas_clinicas' => 'Intento no autorizado']);

        $response->assertForbidden();
        $this->assertDatabaseCount('consulta', 0);
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada']);
    }

    public function test_doctor_records_a_consultation_and_marks_their_appointment_attended(): void
    {
        $this->seedVidaSalud();
        $id = $this->historicalAppointment();

        $response = $this->actingAs($this->account('medico'))->post(route('clinica.consultation', ['cita' => $id]), ['notas_clinicas' => 'Evolución favorable']);

        $response->assertRedirectToRoute('clinica.show', ['cita' => $id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('consulta', ['cita_id' => $id, 'notas_clinicas' => 'Evolución favorable', 'registrada_por' => $this->account('medico')->getKey()]);
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Atendida']);
    }

    public function test_future_appointment_cannot_be_attended_and_creates_no_consultation(): void
    {
        $this->seedVidaSalud();
        $id = $this->existingAppointment($this->appointmentPayload());

        $response = $this->actingAs($this->account('medico'))->postJson(route('clinica.consultation', ['cita' => $id]), ['notas_clinicas' => 'Atención prematura']);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('consulta', 0);
        $this->assertDatabaseHas('cita', ['id' => $id, 'estado' => 'Programada']);
    }

    public function test_doctor_saves_patient_history_for_a_patient_in_their_agenda(): void
    {
        $this->seedVidaSalud();
        $payload = $this->appointmentPayload();
        $this->existingAppointment($payload);

        $response = $this->actingAs($this->account('medico'))->post(route('clinica.history', ['paciente' => $payload['paciente_id']]), [
            'antecedentes_personales' => 'Hipertensión', 'alergias' => 'Penicilina',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('historia_clinica', ['paciente_id' => $payload['paciente_id'], 'alergias' => 'Penicilina']);
    }

    public function test_doctor_cannot_read_or_write_history_for_an_unrelated_patient(): void
    {
        $this->seedVidaSalud();
        $patientId = $this->otherPatient();

        $response = $this->actingAs($this->account('medico'))->post(route('clinica.history', ['paciente' => $patientId]), ['alergias' => 'Dato ajeno']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('historia_clinica', ['paciente_id' => $patientId]);
    }

    public function test_doctor_records_a_diagnosis_on_their_consultation(): void
    {
        $this->seedVidaSalud();
        $consultationId = $this->historicalConsultation();

        $response = $this->actingAs($this->account('medico'))->post(route('clinica.diagnosis', ['consulta' => $consultationId]), ['descripcion' => 'Hipertensión arterial', 'codigo_clasificacion' => 'I10']);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diagnostico', ['consulta_id' => $consultationId, 'descripcion' => 'Hipertensión arterial', 'codigo_clasificacion' => 'I10']);
    }

    public function test_treatment_with_end_before_start_is_rejected_without_writing(): void
    {
        $this->seedVidaSalud();
        $consultationId = $this->historicalConsultation();
        $diagnosisId = DB::table('diagnostico')->insertGetId(['consulta_id' => $consultationId, 'descripcion' => 'Control']);

        $response = $this->actingAs($this->account('medico'))->postJson(route('clinica.treatment', ['diagnostico' => $diagnosisId]), [
            'descripcion' => 'Reposo', 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-19',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
        $this->assertDatabaseCount('tratamiento', 0);
    }

    public function test_doctor_orders_selected_analyses_on_their_consultation(): void
    {
        $this->seedVidaSalud();
        $consultationId = $this->historicalConsultation();
        $analyses = DB::table('analisis_laboratorio')->where('activo', true)->orderBy('id')->limit(2)->pluck('id')->all();

        $response = $this->actingAs($this->account('medico'))->post(route('clinica.order', ['consulta' => $consultationId]), ['analisis_ids' => $analyses]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orden_laboratorio', ['consulta_id' => $consultationId, 'estado' => 'Pendiente', 'solicitada_por' => $this->account('medico')->getKey()]);
        $this->assertDatabaseCount('orden_analisis', 2);
    }

    public function test_laboratory_cannot_order_analyses_even_with_a_valid_consultation(): void
    {
        $this->seedVidaSalud();
        $consultationId = $this->historicalConsultation();
        $analysisId = DB::table('analisis_laboratorio')->value('id');

        $response = $this->actingAs($this->account('laboratorio'))->post(route('clinica.order', ['consulta' => $consultationId]), ['analisis_ids' => [$analysisId]]);

        $response->assertForbidden();
        $this->assertDatabaseCount('orden_laboratorio', 0);
    }

    public function test_first_result_keeps_a_multi_analysis_order_in_process(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder(2);

        $response = $this->actingAs($this->account('laboratorio'))->post(route('laboratorio.result', ['ordenAnalisis' => $order['items'][0]]), ['resultado' => 'Normal']);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('resultado_laboratorio', ['orden_analisis_id' => $order['items'][0], 'resultado' => 'Normal', 'publicado_en' => null]);
        $this->assertDatabaseHas('orden_laboratorio', ['id' => $order['order'], 'estado' => 'En proceso']);
    }

    public function test_last_result_completes_the_order_without_publishing_it(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();

        $response = $this->actingAs($this->account('laboratorio'))->post(route('laboratorio.result', ['ordenAnalisis' => $order['items'][0]]), ['resultado' => 'Glucosa 95 mg/dL']);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orden_laboratorio', ['id' => $order['order'], 'estado' => 'Completada']);
        $this->assertDatabaseHas('resultado_laboratorio', ['orden_analisis_id' => $order['items'][0], 'resultado' => 'Glucosa 95 mg/dL', 'publicado_en' => null]);
    }

    public function test_cancelling_an_order_with_results_is_rejected_without_hiding_the_result(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder(2);
        $this->storeResult($order['items'][0], 'Normal');

        $response = $this->actingAs($this->account('laboratorio'))->postJson(route('laboratorio.action', ['orden' => $order['order']]), ['estado' => 'Cancelada', 'motivo_cancelacion' => 'Intento tardío']);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('orden_laboratorio', ['id' => $order['order'], 'estado' => 'En proceso']);
        $this->assertDatabaseHas('resultado_laboratorio', ['orden_analisis_id' => $order['items'][0], 'resultado' => 'Normal']);
    }

    public function test_doctor_publishes_completed_results_for_the_verified_patient_portal(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();
        $resultId = $this->storeResult($order['items'][0], 'Resultado listo para publicar');
        DB::table('usuario')->where('id', $this->account('laboratorio')->getKey())->update(['activo' => false]);

        $response = $this->actingAs($this->account('medico'))->post(route('clinica.publish', ['orden' => $order['order']]));

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('resultado_laboratorio', ['id' => $resultId, 'publicado_por' => $this->account('medico')->getKey()]);
        $this->assertNotNull(DB::table('resultado_laboratorio')->where('id', $resultId)->value('publicado_en'));
        $this->assertDatabaseCount('resultado_version', 0);
        $this->assertDatabaseHas('bitacora', ['entidad' => 'resultado_laboratorio', 'registro_id' => $resultId, 'accion' => 'UPDATE', 'usuario_id' => $this->account('medico')->getKey()]);
    }

    public function test_correcting_a_published_result_keeps_the_prior_version_and_withdraws_publication(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder();
        $resultId = $this->storeResult($order['items'][0], 'Valor anterior');
        $this->publishResultFixture($resultId);

        $response = $this->actingAs($this->account('laboratorio'))->post(route('laboratorio.result', ['ordenAnalisis' => $order['items'][0]]), ['resultado' => 'Valor corregido', 'observaciones' => 'Muestra revisada']);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('resultado_laboratorio', ['id' => $resultId, 'resultado' => 'Valor corregido', 'publicado_por' => null, 'publicado_en' => null]);
        $this->assertDatabaseCount('resultado_version', 1);
        $this->assertDatabaseHas('resultado_version', ['resultado_id' => $resultId, 'valor_anterior' => 'Valor anterior', 'corregido_por' => $this->account('laboratorio')->getKey()]);
    }

    public function test_patient_portal_shows_diagnosis_treatment_and_published_results_but_hides_internal_notes(): void
    {
        $this->seedVidaSalud();
        $order = $this->laboratoryOrder(2);
        $consultationId = DB::table('orden_laboratorio')->where('id', $order['order'])->value('consulta_id');
        $diagnosisId = DB::table('diagnostico')->insertGetId(['consulta_id' => $consultationId, 'descripcion' => 'DIAGNOSTICO-VISIBLE']);
        DB::table('tratamiento')->insert(['diagnostico_id' => $diagnosisId, 'descripcion' => 'TRATAMIENTO-VISIBLE']);
        $resultId = $this->storeResult($order['items'][0], 'RESULTADO-PUBLICADO');
        $this->storeResult($order['items'][1], 'RESULTADO-BORRADOR-PRIVADO');
        $this->publishResultFixture($resultId);
        $otherConsultation = $this->historicalConsultation($this->otherPatient(), 10);
        DB::table('diagnostico')->insert(['consulta_id' => $otherConsultation, 'descripcion' => 'DIAGNOSTICO-AJENO']);

        $response = $this->actingAs($this->account('paciente'))->get(route('portal.index'));

        $response->assertOk()->assertSee('DIAGNOSTICO-VISIBLE')->assertSee('TRATAMIENTO-VISIBLE')->assertSee('RESULTADO-PUBLICADO')
            ->assertDontSee('NOTA-INTERNA-NO-PUBLICAR')->assertDontSee('RESULTADO-BORRADOR-PRIVADO')->assertDontSee('DIAGNOSTICO-AJENO');
    }

    public function test_patient_can_change_contact_information_but_cannot_relink_or_change_identity(): void
    {
        $this->seedVidaSalud();
        $patientId = $this->account('paciente')->paciente_id;
        $otherPatientId = $this->otherPatient();

        $response = $this->actingAs($this->account('paciente'))->post(route('portal.update'), [
            'telefono' => '71111111', 'correo' => 'ana@example.test', 'direccion' => 'Calle Central',
            'ci' => 'CI-ALTERADA', 'nombres' => 'Identidad falsa', 'paciente_id' => $otherPatientId, 'rol_id' => $this->account('admin')->rol_id,
        ]);

        $response->assertRedirectToRoute('portal.profile')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('paciente', ['id' => $patientId, 'ci' => 'TEST-001', 'nombres' => 'Ana', 'telefono' => '71111111']);
        $this->assertDatabaseHas('usuario', ['id' => $this->account('paciente')->getKey(), 'paciente_id' => $patientId]);
        $this->assertDatabaseHas('paciente', ['id' => $otherPatientId, 'telefono' => '70000002']);
    }

    private function storeResult(int $itemId, string $value): int
    {
        return (int) DB::table('resultado_laboratorio')->insertGetId([
            'orden_analisis_id' => $itemId, 'resultado' => $value, 'laboratorista_id' => $this->account('laboratorio')->getKey(),
        ]);
    }

    private function publishResultFixture(int $resultId): void
    {
        DB::transaction(function () use ($resultId): void {
            $previousActor = DB::selectOne("select current_setting('app.usuario_id', true) as actor")->actor;
            DB::selectOne("select set_config('app.usuario_id', ?, true)", [(string) $this->account('medico')->getKey()]);

            try {
                DB::table('resultado_laboratorio')->where('id', $resultId)->update([
                    'publicado_por' => $this->account('medico')->getKey(), 'publicado_en' => now(),
                ]);
            } finally {
                DB::selectOne("select set_config('app.usuario_id', ?, true)", [$previousActor ?? '']);
            }
        });
    }
}

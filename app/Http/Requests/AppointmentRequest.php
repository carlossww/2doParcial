<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('citas.gestionar') || $this->user()->hasPermission('citas.propias');
    }

    public function rules(): array
    {
        return ['paciente_id' => ['required', 'integer', 'exists:paciente,id'], 'medico_id' => ['required', 'integer', 'exists:medico,id'], 'especialidad_id' => ['required', 'integer', 'exists:especialidad,id'], 'inicio' => ['required', 'date'], 'motivo_consulta' => ['nullable', 'string', 'max:2000']];
    }
}

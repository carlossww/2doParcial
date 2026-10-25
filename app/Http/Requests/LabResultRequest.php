<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('laboratorio.procesar');
    }

    public function rules(): array
    {
        return ['resultado' => ['required', 'string', 'max:30000'], 'observaciones' => ['nullable', 'string', 'max:10000'], 'archivo' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120']];
    }
}

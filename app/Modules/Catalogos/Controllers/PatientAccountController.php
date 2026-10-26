<?php

namespace App\Modules\Catalogos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Services\PatientAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PatientAccountController extends Controller
{
    public function __construct(private PatientAccountService $accounts) {}

    public function edit(Request $request, int $id): View
    {
        $account = $this->accounts->account($request->user(), $id);
        $patients = $this->accounts->options($account);

        return view('catalog.patient-account', compact('account', 'patients'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->accounts->account($request->user(), $id);
        $data = $request->validate([
            'paciente_id' => ['present', 'nullable', 'integer', 'exists:paciente,id'],
            'confirmar_vinculo' => ['required', 'accepted'],
            'paciente_verificado_por' => ['missing'],
            'paciente_verificado_en' => ['missing'],
        ]);
        $this->accounts->update($request->user(), $id, isset($data['paciente_id']) ? (int) $data['paciente_id'] : null);

        return redirect()->route('catalog.users.patient.edit', $id)->with('success', 'Vínculo con el paciente guardado correctamente.');
    }
}

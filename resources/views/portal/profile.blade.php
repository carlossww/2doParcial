@extends('layouts.app')
@section('title', 'Mis datos')
@section('content')
    <x-page-heading title="Mis datos" description="Mantén actualizada tu información de contacto." :back="route('portal.index')" />
    <section class="panel form-panel"><h2>Información de contacto</h2><p class="text-muted text-small" style="margin-bottom: 24px">{{ auth()->user()->display_name }} · Los campos con asterisco son obligatorios.</p>
        <form method="POST" action="{{ route('portal.update') }}" class="form-stack" data-submit-form>@csrf
            <div class="form-grid"><x-field name="telefono" label="Teléfono" type="tel" :value="data_get($patient, 'telefono')" autocomplete="tel" maxlength="40" :required="true" /><x-field name="correo" label="Correo electrónico" type="email" :value="data_get($patient, 'correo')" autocomplete="email" maxlength="150" /><div class="grid-full"><x-field name="direccion" label="Dirección" type="textarea" :value="data_get($patient, 'direccion')" autocomplete="street-address" maxlength="3000" /></div><x-field name="contacto_emergencia" label="Contacto de emergencia" :value="data_get($patient, 'contacto_emergencia')" maxlength="150" /><x-field name="telefono_emergencia" label="Teléfono de emergencia" type="tel" :value="data_get($patient, 'telefono_emergencia')" maxlength="40" /></div>
            <div class="form-actions"><button class="button button-primary" type="submit"><x-icon name="check" />Guardar mis datos</button><a class="button" href="{{ route('portal.index') }}">Volver a mi salud</a></div>
        </form>
    </section>
@endsection

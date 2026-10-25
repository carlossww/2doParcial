@extends('layouts.app')
@section('title', 'Atención clínica')
@section('content')
    <x-page-heading title="Atención clínica" description="Consulta la historia del paciente y registra su atención médica." />
    <div class="table-panel">@include('partials.appointments', ['appointmentRoute' => 'clinica.show'])</div>
@endsection

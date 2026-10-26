<?php

use App\Modules\Acceso\Controllers\AuthController;
use App\Modules\Agenda\Controllers\AppointmentQueryController;
use App\Modules\Auditoria\Controllers\AuditController;
use App\Modules\Catalogos\Controllers\CatalogController;
use App\Modules\Catalogos\Controllers\PatientAccountController;
use App\Modules\Inicio\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', [AuthController::class, 'show'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/salir/confirmar', [AuthController::class, 'confirmLogout'])->name('logout.confirm');
    Route::post('/salir', [AuthController::class, 'logout'])->block(10, 10)->name('logout');

    Route::get('/agenda', [AppointmentQueryController::class, 'index'])->name('agenda.index');
    Route::get('/gestion/pacientes/{id}/citas', [AppointmentQueryController::class, 'patient'])->whereNumber('id')->name('appointments.patient');
    Route::get('/gestion/medicos/{id}/agenda', [AppointmentQueryController::class, 'doctor'])->whereNumber('id')->name('agenda.doctor');
    Route::get('/gestion/usuarios/{id}/paciente', [PatientAccountController::class, 'edit'])->whereNumber('id')->name('catalog.users.patient.edit');
    Route::put('/gestion/usuarios/{id}/paciente', [PatientAccountController::class, 'update'])->whereNumber('id')->name('catalog.users.patient.update');

    Route::get('/gestion/roles/{id}/eliminar', [CatalogController::class, 'confirmDeleteRole'])->whereNumber('id')->name('catalog.roles.confirm-destroy');
    Route::delete('/gestion/roles/{id}', [CatalogController::class, 'destroyRole'])->whereNumber('id')->name('catalog.roles.destroy');
    Route::get('/gestion/{module}', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/gestion/{module}/nuevo', [CatalogController::class, 'create'])->name('catalog.create');
    Route::post('/gestion/{module}', [CatalogController::class, 'store'])->name('catalog.store');
    Route::get('/gestion/{module}/{id}/editar', [CatalogController::class, 'edit'])->whereNumber('id')->name('catalog.edit');
    Route::match(['put', 'post'], '/gestion/{module}/{id}', [CatalogController::class, 'update'])->whereNumber('id')->name('catalog.update');

    Route::get('/bitacora', [AuditController::class, 'index'])->name('auditoria.index');
    Route::get('/bitacora/{id}', [AuditController::class, 'show'])->whereNumber('id')->name('auditoria.show');
});

<?php

use App\Modules\Acceso\Controllers\AuthController;
use App\Modules\Agenda\Controllers\AgendaController;
use App\Modules\Auditoria\Controllers\AuditController;
use App\Modules\Catalogos\Controllers\CatalogController;
use App\Modules\Clinica\Controllers\ClinicalController;
use App\Modules\Inicio\Controllers\HomeController;
use App\Modules\Laboratorio\Controllers\LabController;
use App\Modules\Portal\Controllers\PortalController;
use App\Modules\Portal\Controllers\PortalRegistrationController;
use App\Modules\Reportes\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/registro', [PortalRegistrationController::class, 'show'])->name('register');
    Route::post('/registro', [PortalRegistrationController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/ingresar', [AuthController::class, 'show'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/solicitudes-portal', [PortalRegistrationController::class, 'requests'])->name('portal.requests');
    Route::post('/solicitudes-portal/{solicitud}', [PortalRegistrationController::class, 'verify'])->whereNumber('solicitud')->name('portal.verify');
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');
    Route::get('/gestion/{module}', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/gestion/{module}/nuevo', [CatalogController::class, 'create'])->name('catalog.create');
    Route::post('/gestion/{module}', [CatalogController::class, 'store'])->name('catalog.store');
    Route::get('/gestion/{module}/{id}/editar', [CatalogController::class, 'edit'])->whereNumber('id')->name('catalog.edit');
    Route::match(['put', 'post'], '/gestion/{module}/{id}', [CatalogController::class, 'update'])->whereNumber('id')->name('catalog.update');
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('/agenda/nueva', [AgendaController::class, 'create'])->name('agenda.create');
    Route::get('/agenda/disponibilidad', [AgendaController::class, 'availability'])->name('agenda.availability');
    Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
    Route::get('/agenda/{cita}', [AgendaController::class, 'show'])->whereNumber('cita')->name('agenda.show');
    Route::post('/agenda/{cita}/accion', [AgendaController::class, 'action'])->whereNumber('cita')->name('agenda.action');
    Route::get('/atencion', [ClinicalController::class, 'index'])->name('clinica.index');
    Route::get('/atencion/{cita}', [ClinicalController::class, 'show'])->whereNumber('cita')->name('clinica.show');
    Route::post('/historias/{paciente}', [ClinicalController::class, 'history'])->whereNumber('paciente')->name('clinica.history');
    Route::post('/atencion/{cita}/consulta', [ClinicalController::class, 'consultation'])->whereNumber('cita')->name('clinica.consultation');
    Route::post('/consultas/{consulta}/diagnosticos', [ClinicalController::class, 'diagnosis'])->whereNumber('consulta')->name('clinica.diagnosis');
    Route::post('/diagnosticos/{diagnostico}/tratamientos', [ClinicalController::class, 'treatment'])->whereNumber('diagnostico')->name('clinica.treatment');
    Route::post('/consultas/{consulta}/ordenes', [ClinicalController::class, 'order'])->whereNumber('consulta')->name('clinica.order');
    Route::post('/ordenes/{orden}/publicar', [ClinicalController::class, 'publish'])->whereNumber('orden')->name('clinica.publish');
    Route::get('/laboratorio', [LabController::class, 'index'])->name('laboratorio.index');
    Route::get('/laboratorio/{orden}', [LabController::class, 'show'])->whereNumber('orden')->name('laboratorio.show');
    Route::post('/laboratorio/analisis/{ordenAnalisis}/resultado', [LabController::class, 'result'])->whereNumber('ordenAnalisis')->name('laboratorio.result');
    Route::post('/laboratorio/{orden}/estado', [LabController::class, 'action'])->whereNumber('orden')->name('laboratorio.action');
    Route::get('/informes/{resultado}', [LabController::class, 'download'])->whereNumber('resultado')->name('laboratorio.download');
    Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
    Route::get('/portal/perfil', [PortalController::class, 'profile'])->name('portal.profile');
    Route::post('/portal/perfil', [PortalController::class, 'update'])->name('portal.update');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reportes.index');
    Route::get('/bitacora', [AuditController::class, 'index'])->name('auditoria.index');
});

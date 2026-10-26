# Plan de implementación de los casos de uso pendientes

> Actualización posterior: se corrigieron CU3/CU5/CU6/CU18 y se habilitó la consulta de agenda de CU10 para completar CU6. El alcance y la evidencia están en [correccion_casos_parciales.md](correccion_casos_parciales.md). Este plan conserva su diseño original; las acciones de asistencia y cambios de estado incluidas en su apartado de Agenda siguen pendientes, junto con CU8, CU9, CU11-CU17 y CU19.

## 1. Objetivo y referencia

Implementar los once casos sin un flujo web operativo en la copia revisada: CU8, CU9, CU10, CU11, CU12, CU13, CU14, CU15, CU16, CU17 y CU19.

Referencia funcional: `C:/Users/USUARIO/Downloads/2doparcial/Vida Salud - Grupo 9 (3).pdf`, listado de páginas 19–20 y fichas de páginas 23–42. Las ubicaciones indicadas son relativas a la raíz `Sistema_Gestion_Clinica-main`.

Este archivo es un plan de trabajo pendiente. Las tablas, permisos y fragmentos de interfaz existentes no se consideran una implementación completa de los casos. No se ejecutaron pruebas para preparar este documento.

Dependencia: [Plan de corrección de casos implementados](plan_correccion_casos_implementados.md), especialmente CU18, que incorpora duración configurable.

## 2. Decisiones funcionales acordadas

- Administrador y recepción gestionarán médicos y especialidades.
- El paciente consultará su historial completo, incluidas notas y resultados registrados, exclusivamente propios.
- Los resultados y adjuntos propios serán visibles desde que se registren, sin publicación médica obligatoria. Los análisis sin resultados se mostrarán como pendientes.
- El administrador consultará los cinco tipos de reportes del PDF, incluidos atenciones y resultados de laboratorio.
- Las citas tendrán duración de 15, 30, 45 o 60 minutos según su franja; el servidor determinará su duración y final.
- Conservar las marcas de publicación existentes como metadatos compatibles, sin usarlas para ocultar resultados propios al paciente.
- Mantener la arquitectura y el almacenamiento actuales. No incorporar en esta etapa registro público ni exportaciones CSV/PDF, que no son necesarias para completar los flujos solicitados.

## 3. Preparación y arquitectura

### Entorno y datos

1. Instalar dependencias desde `composer.lock` y preparar configuración local. En la copia revisada no estaban presentes `vendor` ni `.env`.
2. Usar PostgreSQL, esquema `vida_salud` y zona horaria `America/La_Paz`.
3. Ejecutar pruebas solo en `Parcial2_test`, conservando las comprobaciones de entorno y conexión de `tests/Support/TestDatabaseSafety.php`.
4. Usar migraciones nuevas y probarlas sobre base vacía y copia del esquema existente. No modificar el dump original ni vaciar la base operativa.
5. Generar respaldo antes de aplicar cambios a la base operativa y conservar fechas, identificadores, autorías y relaciones.

### Estructura nueva

Usar el patrón actual de `app/Modules/<Modulo>/Controllers` y `Services`, con validadores en `app/Http/Requests` y vistas Blade en `resources/views`:

| Módulo | Controladores | Servicios | Vistas |
| --- | --- | --- | --- |
| Agenda | `AppointmentController`, `AvailabilityController` | `AppointmentService` | `agenda` |
| Atencion | `ClinicalController`, `HistoryController` | `ClinicalService`, `HistoryService` | `atencion`, `historia` |
| Laboratorio | `LaboratoryController` | `LaboratoryService` | `laboratorio` |
| Portal | `PatientPortalController` | Reutiliza agenda e historia | `portal` |
| Reportes | `ReportController` | `ReportService` | `reportes` |

Los nombres de este cuadro corresponden a archivos nuevos por crear. Las rutas se agregarán a `routes/web.php`, protegidas por autenticación y cuenta activa.

### Componentes existentes que se reutilizan

- `app/Modules/Shared/Clinic.php`: transacciones auditadas, identidad del paciente y perfil médico activo.
- `database/vida_salud_existing.sql` y las migraciones existentes: tablas, vistas, funciones, eventos y restricciones clínicas.
- `resources/views/components`: campos, estados, encabezados y mensajes.
- `public/assets/app.js`: consulta de disponibilidad, validaciones y estados de formularios.
- `database/seeders/RolePermissionSeeder.php`: permisos predeterminados, complementados por actualizaciones puntuales para instalaciones existentes.
- `tests/Support/SeedsVidaSalud.php` y `appointment-worker.php`: preparación de escenarios y proceso de reserva concurrente. El trabajador existente utiliza `POST /agenda`.

### Prerrequisitos funcionales

1. Completar el vínculo de cuentas Paciente desde la gestión de usuarios: selección de un paciente existente, confirmación de verificación y registro del verificador. No vincular automáticamente por CI.
2. Al editar otros campos de una cuenta, conservar el vínculo si no se solicita expresamente modificarlo. Actualmente el backend contempla `paciente_id`, pero falta exponer correctamente el vínculo en el formulario.
3. Mantener la creación y verificación de estas cuentas en administración; no es necesario abrir un registro público para CU17.
4. Incorporar el catálogo de análisis a `config/clinic.php`, sus validaciones y vistas compartidas, usando `analisis.gestionar` para administración. Las órdenes usarán análisis activos de ese catálogo.
5. Actualizar `HomeController` y la navegación para mostrar los módulos nuevos según permisos.
6. Obtener actor y propietario desde la sesión y relaciones guardadas, no desde identificadores de autor enviados por el navegador.

## 4. Agenda: CU8, CU9 y CU10

### CU8: agendar cita médica

**Ubicación propuesta:** `app/Modules/Agenda/Controllers/AppointmentController.php`, `AvailabilityController.php`, `Services/AppointmentService.php` y vistas `resources/views/agenda`.

**Base existente:** tablas `cita`, `horario`, `medico_especialidad` y `evento_cita`; código generado de cita; exclusiones por médico y paciente; validaciones de disponibilidad. Aplicar primero la corrección de duración de CU18.

**Implementación:**

1. Presentar búsqueda de paciente, especialidad, médico, fecha, bloque y motivo opcional.
2. Limitar nuevas reservas a pacientes activos, perfiles y cuentas médicas activas, rol médico activo y asociación activa con la especialidad.
3. Mostrar únicamente bloques futuros completos y alineados con su franja, descontando conflictos tanto del médico como del paciente.
4. Al guardar, revalidar dentro de `Clinic::transaction`, tomar los bloqueos correspondientes y calcular duración y final desde el horario.
5. Registrar estado `Programada`, autores desde la sesión y código generado por PostgreSQL. Dejar al trigger generar el evento de creación.
6. Rechazar conflictos con HTTP 422 y mensaje comprensible, sin registros parciales.

**Aceptación:** reserva persistente, bloque correcto para las cuatro duraciones, rechazo de fechas pasadas y conflictos, y un solo éxito ante dos reservas simultáneas incompatibles.

### CU9: cancelar o reprogramar cita

**Ubicación propuesta:** acciones específicas del mismo controlador y servicio de Agenda; formularios en el detalle de cita.

**Implementación:**

1. Buscar y mostrar una cita, sus eventos y las acciones permitidas.
2. Cancelar exige motivo, conserva la fila y libera el bloque para nuevas reservas.
3. Reprogramar conserva paciente, médico y especialidad; para cambiarlos se cancela y crea otra cita.
4. Revalidar el bloque de destino en transacción, guardar su duración y dejar la cita en `Programada`.
5. Rechazar cambios a citas `Atendida`, `Cancelada` o `Inasistencia`.
6. Conservar el estado e intervalo anteriores si ocurre un conflicto o un error.

**Aceptación:** cancelación sin borrado, bloque liberado, historial conservado y reversión completa de reprogramaciones fallidas.

### CU10: consultar agenda médica

**Ubicación propuesta:** listado y detalle de `AppointmentController`, vistas `agenda/index.blade.php` y `show.blade.php`.

**Implementación:**

1. Filtrar por médico, paciente, fecha o período y estado.
2. Mostrar código, paciente, médico, especialidad, intervalo y estado; incorporar historial de eventos al detalle.
3. El médico consulta su agenda; administración y recepción tienen alcance administrativo general.
4. El paciente consulta solo sus citas desde Portal, sin recibir acceso a la agenda general.
5. Mostrar el motivo de consulta únicamente cuando el usuario tenga el alcance clínico correspondiente; el resto de la agenda conserva información administrativa.
6. Agregar acciones para confirmar e indicar inasistencia. Administración, recepción y el médico responsable podrán realizarlas según sus permisos; inasistencia solo después del final del bloque.
7. Establecer `Atendida` al registrar la consulta, sin permitir una transición administrativa que invente atención clínica.
8. Incorporar el enlace desde el directorio médico y conservar citas de pacientes y médicos inactivos para consulta histórica.

**Aceptación:** filtros y totales correctos, aislamiento por médico y paciente, estados vacíos claros y rechazo de transiciones no permitidas.

## 5. Atención: CU11, CU12 y CU13

### CU11: consultar historial clínico

**Ubicación propuesta:** `app/Modules/Atencion/Controllers/HistoryController.php`, `Services/HistoryService.php`, vistas `resources/views/historia` y entrada propia desde Portal.

**Base existente:** `historia_clinica`, `consulta`, `diagnostico`, `tratamiento`, `historial_clinico`, órdenes y resultados.

**Implementación:**

1. Permitir al médico buscar pacientes por nombre, apellido o documento, limitando resultados a su alcance asistencial.
2. Considerar relación asistencial una cita propia con el paciente en estado `Programada`, `Confirmada` o `Atendida`. Exigir además cuenta, rol y perfil médico activos.
3. Mostrar antecedentes personales y familiares, alergias, consultas, notas, diagnósticos, tratamientos y resultados registrados con adjuntos.
4. Construir la consulta de modo que varios tratamientos o análisis no dupliquen consultas y diagnósticos en pantalla.
5. Para el paciente, obtener la identidad desde `Clinic::patientId`; no presentar búsqueda de otras personas. Mostrar su historial completo en modo de lectura.
6. Mostrar resultados propios desde su registro, identificando análisis pendientes sin ocultar el resto de la historia.
7. Mantener al administrador en el alcance de reportes acordado; no conceder por ello edición clínica ni acceso irrestricto a las rutas de historia.
8. Mostrar mensajes distintos para búsqueda vacía, historial sin contenido y criterios inválidos.

**Aceptación:** acceso médico autorizado, lectura propia completa, rechazo de identificadores ajenos y ausencia de duplicaciones en la presentación.

### CU12: registrar diagnóstico

**Ubicación propuesta:** `app/Modules/Atencion/Controllers/ClinicalController.php`, `Services/ClinicalService.php` y vistas `resources/views/atencion`.

**Base existente:** una consulta por cita, diagnóstico vinculado a consulta y trigger de integridad de atención.

**Implementación:**

1. Abrir una cita propia y recuperar o crear su consulta dentro de una transacción.
2. Permitir registrar notas y fecha de atención válida. El trigger conservará la autoría y marcará la cita como atendida cuando corresponda.
3. Ingresar descripción obligatoria y código de clasificación opcional del diagnóstico.
4. Mostrar revisión y confirmación antes de guardar, manteniendo el formulario si falla la validación.
5. Rechazar atención antes del inicio, citas canceladas o con inasistencia y consultas de otro profesional.
6. Conservar una única consulta por cita y no permitir trasladar consultas o autorías.

**Aceptación:** diagnóstico asociado correctamente y visible en historia, rechazo de registro no autorizado y ausencia de consultas duplicadas.

### CU13: registrar tratamiento

**Ubicación propuesta:** mismo controlador y servicio clínico, con formulario asociado a un diagnóstico.

**Base existente:** `tratamiento.diagnostico_id`, descripción, indicaciones y fechas.

**Implementación:**

1. Seleccionar un diagnóstico de una consulta del médico autenticado.
2. Registrar descripción obligatoria, indicaciones y fechas opcionales coherentes.
3. Revisar y confirmar el registro; obtener paciente y autor a través de las relaciones guardadas.
4. Rechazar ausencia de diagnóstico, fechas inválidas y diagnósticos de consultas ajenas.

**Aceptación:** tratamiento relacionado con el diagnóstico correcto y visible en el historial, sin asociaciones manipulables a pacientes ajenos.

## 6. Laboratorio: CU14, CU15 y CU16

### CU14: gestionar órdenes de laboratorio

**Ubicación propuesta:** `app/Modules/Laboratorio/Controllers/LaboratoryController.php`, `Services/LaboratoryService.php` y vistas `resources/views/laboratorio`.

**Base existente:** `orden_laboratorio`, `orden_analisis`, `analisis_laboratorio` y restricciones diferidas de orden no vacía.

**Implementación:**

1. El médico selecciona una consulta propia y uno o más análisis activos, con indicaciones opcionales.
2. Guardar orden `Pendiente` y detalles en una sola transacción. Rechazar listas vacías y análisis repetidos o inactivos.
3. Permitir modificar detalles solo mientras la orden esté `Pendiente`; no trasladar consulta o solicitante.
4. Permitir al laboratorista iniciar el procesamiento y pasar a `En proceso`.
5. Cancelar únicamente sin resultados y con motivo; conservar el registro.
6. Usar los estados existentes: Pendiente, En proceso, Completada y Cancelada.

**Aceptación:** orden no vacía y consistente, autor correcto, disponibilidad para laboratorio y rechazo de modificaciones incompatibles con su estado.

### CU15: registrar y cargar resultados de laboratorio

**Ubicación propuesta:** acciones de registro y corrección de `LaboratoryController`, formularios de resultados y servicio de almacenamiento privado.

**Base existente:** `resultado_laboratorio`, `resultado_version`, actualización de estado de orden y triggers de corrección.

**Implementación:**

1. El laboratorista selecciona una orden, consulta sus análisis e ingresa un resultado por detalle.
2. Rechazar duplicados y órdenes inexistentes o canceladas.
3. Aceptar adjunto opcional PDF, PNG o JPEG de hasta 5 MB, verificando contenido, tipo y tamaño en el servidor.
4. Guardar con nombre generado dentro de `storage/app/private`, sin rutas públicas ni nombres de archivo elegidos por el usuario.
5. Coordinar archivo y transacción: borrar solo un archivo nuevo si falla su registro; conservar archivos anteriores referenciados por versiones.
6. Aprovechar los triggers para actualizar el estado de la orden y conservar el contenido y autor anteriores al corregir.
7. Mostrar el resultado propio al paciente desde que se guarda, aunque otros análisis de la orden sigan pendientes.
8. Conservar las marcas de publicación como metadatos, sin exigir una acción de publicación adicional para cubrir el flujo acordado.

**Aceptación:** registro y corrección consistentes, versiones y adjuntos conservados, estado de orden correcto y archivos inválidos rechazados sin registros parciales.

### CU16: consultar resultados de laboratorio

**Ubicación propuesta:** listados, detalles y descarga autenticada del módulo Laboratorio.

**Implementación:**

1. Buscar por paciente u orden y mostrar fecha, estado, análisis, valores, observaciones y adjuntos.
2. Autorizar al laboratorista mediante `laboratorio.consultar`; retirar su permiso de procesamiento no debe retirar automáticamente la lectura.
3. Permitir al médico consultar órdenes propias y resultados de pacientes cuyo historial puede consultar.
4. Permitir al paciente descargar exclusivamente adjuntos de resultados propios desde su historial.
5. Comprobar propietario y relaciones en cada descarga. El navegador no enviará una ruta de almacenamiento para recuperar el archivo.
6. La consulta y descarga no modificarán resultados. La corrección tendrá una acción separada y exigirá `laboratorio.procesar`.

**Aceptación:** lectura y descarga autorizadas, resultados pendientes claramente identificados y rechazo de archivos o resultados ajenos.

## 7. Portal: CU17

### CU17: reservar cita médica

**Ubicación propuesta:** `app/Modules/Portal/Controllers/PatientPortalController.php`, vistas `resources/views/portal` y reutilización de `AppointmentService`.

**Implementación:**

1. Exigir cuenta habilitada y vínculo verificado con un paciente activo para nuevas reservas.
2. Obtener siempre `paciente_id` desde la cuenta, no desde el formulario.
3. Mostrar especialidades, médicos activos y bloques disponibles; no otorgar al paciente permisos de gestión de los catálogos.
4. Mostrar un resumen antes de confirmar la reserva. El resumen no retiene el horario: se revalidará al guardar.
5. Reutilizar el mismo servicio transaccional de CU8, conservando código y estado inicial `Programada`.
6. Mostrar detalle e historial propios, incluidas citas pasadas y canceladas.
7. Reutilizar cancelación y reprogramación con alcance propio y permitirlas al paciente solo antes del inicio de la cita.
8. Mantener acceso al historial existente cuando el registro del paciente esté inactivo, siempre que su cuenta permanezca habilitada; impedir nuevas reservas.

**Aceptación:** reserva propia persistente, resumen y confirmación, rechazo de paciente ajeno enviado por el navegador y protección frente a reserva simultánea del mismo bloque.

## 8. Reportes: CU19

### CU19: generar reportes del sistema

**Ubicación propuesta:** `app/Modules/Reportes/Controllers/ReportController.php`, `Services/ReportService.php` y vistas `resources/views/reportes`.

**Base existente:** vistas `reporte_citas`, `reporte_pacientes`, `reporte_atenciones`, `reporte_ordenes`, `reporte_resultados` y `resumen_citas_diarias`.

**Implementación:**

1. Presentar una selección de cinco tipos permitidos: citas, pacientes, atenciones, órdenes y resultados. No convertir el tipo recibido en un nombre SQL arbitrario.
2. Implementar filtros pertinentes por período, médico, especialidad, paciente, estado o análisis, según el reporte.
3. Aplicar límites de fecha con inicio incluido y límite superior del día siguiente para cubrir todo el último día.
4. Mostrar totales globales y por estado cuando correspondan, calculados sobre todos los datos filtrados y no solo sobre la página visible.
5. Evitar que uniones con diagnósticos, tratamientos o análisis multipliquen los conteos de citas y atenciones.
6. El administrador tendrá alcance global en los cinco tipos, con los permisos explícitos de reportes correspondientes.
7. Conservar reportes administrativos para recepción, reportes propios para médicos y reportes de órdenes y resultados autorizados para laboratorio.
8. El alcance de reportes no concede al administrador edición clínica ni acceso directo a archivos de laboratorio desde otras rutas.
9. Mostrar criterios inválidos y ausencia de datos de forma clara. La generación no modifica información.
10. Dejar las exportaciones CSV/PDF fuera de esta entrega inicial.

**Aceptación:** resultados y totales reproducibles contra PostgreSQL, sin duplicados ni exclusiones incorrectas por fechas, con permisos aplicados por tipo y alcance.

## 9. Rutas y contratos de interfaz

Mantener las rutas existentes. Las nuevas rutas tendrán autenticación, cuenta activa, permisos y comprobación del registro correspondiente.

| Área | Rutas nuevas |
| --- | --- |
| Agenda | `GET /agenda`, `GET /agenda/nueva`, `POST /agenda`, `GET /agenda/{id}` y `POST /agenda/{id}/cancelar`, `/reprogramar`, `/estado`. |
| Disponibilidad | `GET /disponibilidad`, con `medico_id`, `especialidad_id`, `fecha` y paciente autorizado; `cita_id` opcional autorizado para excluir la propia cita al reprogramar. |
| Historia | `GET /historia`, `GET /historia/{paciente}` y `GET /portal/historia`. |
| Atención | `GET /atencion/{cita}`, `POST /atencion/{cita}`, `POST /consultas/{consulta}/diagnosticos` y `POST /diagnosticos/{diagnostico}/tratamientos`. |
| Órdenes | `GET /laboratorio/ordenes`, `GET /laboratorio/ordenes/{id}`, `POST /consultas/{consulta}/ordenes`, `PUT /laboratorio/ordenes/{id}`, `POST /laboratorio/ordenes/{id}/iniciar` y `/cancelar`. |
| Resultados | `POST /laboratorio/analisis/{detalle}/resultados`, `PUT /laboratorio/resultados/{id}` y `GET /laboratorio/resultados/{id}/archivo`. |
| Portal | `GET /portal/reservar`, `GET /portal/citas`, `POST /portal/citas`, `GET /portal/citas/{id}` y `POST /portal/citas/{id}/cancelar`, `/reprogramar`. |
| Reportes | `GET /reportes` para selección y `GET /reportes/{tipo}` para consulta. |

Definir las rutas estáticas antes de las parametrizadas y restringir a números los identificadores. Mantener nombres de rutas coherentes por módulo, especialmente `agenda.*`, `historia.*`, `atencion.*`, `laboratorio.*`, `portal.*` y `reportes.*`.

El endpoint de disponibilidad conservará el contrato esperado por el JavaScript actual y agregará final y duración:

```json
{
  "slots": [
    {
      "inicio": "2030-01-07T09:00:00-04:00",
      "fin": "2030-01-07T09:30:00-04:00",
      "duracion_minutos": 30,
      "label": "09:00–09:30"
    }
  ]
}
```

La fecha es ilustrativa. Devolver bloques reales del día solicitado, excluyendo conflictos del paciente y del médico. Para el paciente se deriva la identidad de la sesión; para personal administrativo se valida el paciente elegido. Autorizar cualquier `cita_id` antes de usarlo para excluir un conflicto.

Los formularios recibirán mensajes y redirecciones de éxito. Las solicitudes JSON tendrán respuestas estructuradas; una creación de cita devolverá 201 y los errores de validación o conflicto 422. Usar 403 para ausencia de permiso y 404 para registros inexistentes o fuera del alcance autorizado.

## 10. Orden de ejecución

1. Preparar entorno, base de pruebas y resultado inicial de las pruebas presentes.
2. Completar las correcciones del primer plan, especialmente duración, permisos y protección de vínculos.
3. Completar vínculo de paciente y catálogo de análisis como prerrequisitos.
4. Implementar CU8, CU9 y CU10; verificar concurrencia antes de continuar.
5. Implementar CU17 reutilizando los servicios de Agenda.
6. Implementar CU12 y CU13 y la primera versión de CU11 con los datos clínicos disponibles.
7. Implementar CU14, CU15 y CU16; completar CU11 con resultados y adjuntos.
8. Implementar CU19 sobre los módulos ya integrados.
9. Ejecutar recorridos completos, comprobar rendimiento y actualizar documentación y matriz de cumplimiento de los 20 casos.

## 11. Plan de pruebas

### Automatizadas

- Agregar `AppointmentTest.php` y `AppointmentConcurrencyTest.php`; reutilizar y adaptar el proceso de reserva concurrente existente.
- Agregar `HistoryAccessTest.php`, `ClinicalAndLaboratoryTest.php`, `PatientPortalTest.php` y `ReportTest.php`.
- Ampliar `RolePagesTest.php` para los cinco roles y las páginas realmente habilitadas.
- Mantener `TestDatabaseSafetyTest.php` y las barreras antes de cualquier reconstrucción del esquema de pruebas.
- Actualizar las pruebas JavaScript relacionadas con duración y disponibilidad. Ejecutar `npm run test:js`.

### Escenarios de aceptación

1. Reserva válida para cada duración; rechazo de fechas pasadas, franja externa y bloque desalineado.
2. Dos reservas concurrentes para el mismo médico con pacientes distintos: una confirmación y un conflicto.
3. Dos reservas concurrentes del mismo paciente con médicos distintos: una confirmación y un conflicto.
4. Citas consecutivas permitidas; cancelación libera bloque y conserva historial.
5. Reprogramación conflictiva revierte todos los cambios y conserva los eventos previos.
6. Estados finales protegidos, atención futura rechazada e inasistencia prematura rechazada.
7. Paciente y médico inactivos conservan historia y no reciben nuevas reservas.
8. Médico sin relación asistencial no consulta historia; paciente no consulta datos ni adjuntos ajenos.
9. Diagnósticos y tratamientos vinculados correctamente; consultas de otro médico no se modifican.
10. Orden vacía o con análisis inactivo rechazada sin detalles parciales.
11. Resultados duplicados rechazados; corrección conserva versiones y archivos anteriores.
12. Archivos con formato o tamaño inválido rechazados; descargas autorizadas en cada petición.
13. Paciente ve resultados propios registrados sin publicación y distingue análisis pendientes.
14. Administrador ve los cinco reportes; otros roles respetan su alcance y no reciben edición clínica adicional.
15. Reportes con fechas límite, totales por estado y relaciones múltiples sin doble conteo.
16. Cambios de duración conservan citas antiguas y sus relaciones.

### Navegador y rendimiento

- Recorrer los flujos con los cinco roles, con y sin JavaScript cuando exista alternativa de formulario.
- Comprobar confirmaciones, errores de red, disponibilidad, carga y descarga de archivos y presentación móvil.
- Medir agenda y reportes con una carga reproducible de 10.000 citas ficticias y cinco usuarios concurrentes en el entorno de pruebas.
- Objetivos del PDF: agenda en un máximo de 3 segundos y reportes en un máximo de 5 segundos. Documentar máquina, volumen, concurrencia y mediciones; la duración de PHPUnit no demuestra estos objetivos.

## 12. Entrega, compatibilidad y evidencias

1. Verificar migraciones sobre base vacía y copia del esquema existente, comparando conteos, relaciones y fechas históricas.
2. Respaldar la base operativa antes de aplicar las migraciones verificadas. No ejecutar `migrate:fresh`, `db:wipe` ni restauraciones destructivas sobre ella.
3. Conservar credenciales y asignaciones personalizadas; las actualizaciones de permisos solo agregarán los accesos acordados a los roles predeterminados.
4. Actualizar README y `docs/decisiones.md`, `docs/schema.md`, `docs/etapas.md` y `docs/aceptacion.md` para reflejar las decisiones de acceso, duración y resultados aquí acordadas.
5. No considerar válidas cifras históricas de suites que faltan en esta copia. Adjuntar evidencia nueva de lo realmente ejecutado.

### Lista de aceptación

- [ ] CU8 implementado y verificado.
- [ ] CU9 implementado y verificado.
- [ ] CU10 implementado y verificado.
- [ ] CU11 implementado y verificado.
- [ ] CU12 implementado y verificado.
- [ ] CU13 implementado y verificado.
- [ ] CU14 implementado y verificado.
- [ ] CU15 implementado y verificado.
- [ ] CU16 implementado y verificado.
- [ ] CU17 implementado y verificado.
- [ ] CU19 implementado y verificado.
- [ ] Pruebas de concurrencia, aislamiento de registros y compatibilidad aprobadas.
- [ ] Navegador y rendimiento comprobados.
- [ ] Matriz de los 20 casos y documentación actualizadas.

### Registro de evidencia

| Caso o etapa | Comando o recorrido | Resultado | Evidencia | Fecha |
| --- | --- | --- | --- | --- |
| Pendiente | Por ejecutar | Sin verificar | Por registrar | — |

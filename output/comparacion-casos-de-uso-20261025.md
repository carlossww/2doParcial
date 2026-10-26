# Comparación de los casos de uso con la aplicación web

Fecha: 25 de octubre de 2026.

> Comparación histórica anterior a las correcciones solicitadas después de esta revisión. Las brechas de CU3, CU5, CU6 y CU18 se corrigieron y la consulta de agenda de CU10 está habilitada. El estado y la evidencia posteriores están en [correccion_casos_parciales.md](../docs/correccion_casos_parciales.md). Las reproducciones de este informe conservan evidencia del comportamiento anterior y no son pruebas de aceptación de la versión corregida.

La aplicación tiene una base web para **9 de los 20 casos de uso**: acceso, catálogos administrativos, horarios y bitácora. De esos nueve, cinco tienen su flujo principal implementado y cuatro presentan una brecha relevante. **Los otros once todavía no tienen un flujo web**, aunque existen tablas, funciones y vistas SQL para apoyarlos.

El hallazgo más importante en lo ya desarrollado es que **guardar una cuenta de paciente desde Usuarios elimina su vínculo verificado con el paciente**, incluso al reenviar sus datos actuales. Se reprodujo con datos ficticios en la base de pruebas.

## Fuente, alcance y criterio

- Documento: `C:/Users/USUARIO/Downloads/2doparcial/Vida Salud - Grupo 9 (3).pdf`, 93 páginas. Se revisaron los actores y la priorización de las pp. 19-22, las veinte fichas de las pp. 23-42, los requisitos de las pp. 6-11 y las reglas de agenda de las pp. 83-84. Se contrastaron también los diagramas pertinentes, incluidas las pp. 45, 48-54, 76 y 77.
- Aplicación: `C:/Users/USUARIO/Downloads/1/Sistema_Gestion_Clinica-main`. Se inspeccionaron rutas, controladores, servicios, permisos, formularios, migraciones, SQL y pruebas. La comparación corresponde a esta copia del código.
- La revisión previa `revision-casos-de-uso.md` y los planes existentes sirven de contexto; las conclusiones se contrastaron nuevamente con el PDF y la implementación.

**Implementado principal** significa que existe el recorrido web central y sus controles relevantes. No certifica que cada frase de la especificación o cada escenario de navegador esté verificado. **Parcial** identifica un recorrido existente con un defecto confirmado o una funcionalidad asociada exigida por los RF que falta. **Sin flujo web** significa que el actor todavía no puede realizar el caso desde esta aplicación; tener permisos o tablas preparados no cambia esa clasificación.

Para CU5 y CU6 se separa expresamente la ficha de su requisito relacionado: sus operaciones administrativas están implementadas, pero RF02/RF12 y RF03 exigen consultas adicionales todavía ausentes.

## Matriz de los veinte casos

| Caso y página del PDF | Estado | Comparación con la aplicación actual |
| --- | --- | --- |
| **CU1. Iniciar sesión**, p. 23 | Implementado principal | `/ingresar` valida usuario o correo, contraseña, cuenta y rol activos; regenera sesión y registra INGRESO. El inicio ofrece módulos según permisos. Las cinco cuentas de demostración autentican, incluido Paciente. La alternativa de expiración no está representada exactamente como en la ficha: existe una página 419 y la protección estándar de acceso. |
| **CU2. Cerrar sesión**, p. 24 | Implementado principal | Hay confirmación, cancelación, POST con CSRF, invalidación de sesión, renovación del token y registro SALIDA. JavaScript reintenta una vez ante fallo de red; la clave por sesión evita duplicar la auditoría. |
| **CU3. Gestionar usuarios**, p. 25 | **Parcial** | Existen listado, búsqueda, creación, edición, rol y activación/desactivación. Las contraseñas se guardan con hash y hay unicidad de usuario/correo. Sin embargo, una edición normal borra el vínculo de una cuenta de paciente; el formulario tampoco permite establecer ese vínculo. Véase H1. |
| **CU4. Gestionar roles y permisos**, p. 26 | Implementado principal | Se crean y editan roles con permisos, se activan/desactivan y se eliminan roles personalizados previa confirmación. Se rechaza eliminar roles asignados y se protegen los roles del sistema y la administración propia. La ficha debería integrar la eliminación en su flujo principal, porque la menciona solo como alternativa. |
| **CU5. Gestionar pacientes**, p. 27 | **Parcial frente a RF02/RF12** | Registro, listado, búsqueda, edición y desactivación están implementados; hay normalización y unicidad del CI. Se conserva la información relacionada. Falta consultar las citas anteriores de un paciente desde la web, exigido por RF02 y RF12. La ficha administrativa, considerada aisladamente, tiene cobertura principal. |
| **CU6. Gestionar médicos**, p. 28 | **Parcial frente a RF03** | Administración y recepción pueden registrar el perfil desde una cuenta médica existente, modificar datos y especialidades, buscar y desactivar. Se conservan relaciones históricas y se bloquean bajas incompatibles con citas futuras. Falta consultar la agenda del médico, exigido por RF03 y dependiente de CU10. |
| **CU7. Configurar especialidades**, p. 29 | Implementado principal | Registro, consulta, búsqueda, edición y desactivación por administración/recepción. Se evitan nombres normalizados duplicados. La aplicación usa desactivación y no ofrece borrado definitivo de especialidades. |
| **CU8. Agendar cita médica**, p. 30 | **Sin flujo web** | Existen `agendar_cita`, `horarios_disponibles`, validaciones y restricciones SQL. Faltan el formulario de recepción, la consulta HTTP de disponibilidad y la acción web que registra la cita. |
| **CU9. Cancelar/reprogramar cita**, p. 31 | **Sin flujo web** | Existen funciones SQL y eventos para conservar los cambios. Faltan búsqueda y detalle de cita, selección de nueva disponibilidad, confirmaciones y acciones HTTP de cancelación/reprogramación. |
| **CU10. Consultar agenda médica**, p. 32 | **Sin flujo web** | No hay pantalla ni controlador de agenda con filtros, permisos y listado de citas. El catálogo de horarios muestra franjas semanales; no sustituye la agenda de citas. |
| **CU11. Consultar historial clínico**, p. 33 | **Sin flujo web** | Existen historia, consultas y relaciones clínicas en SQL. Falta la consulta web por paciente y el alcance autorizado para médico/paciente. El catálogo administrativo de pacientes tampoco sustituye esta función. |
| **CU12. Registrar diagnóstico**, p. 34 | **Sin flujo web** | Existen `consulta` y `diagnostico`. Faltan búsqueda/selección autorizada, datos de atención, revisión, confirmación y persistencia desde la interfaz del médico. |
| **CU13. Registrar tratamiento**, p. 35 | **Sin flujo web** | Existe `tratamiento` relacionado con diagnóstico. Falta el formulario y la acción autorizada para registrar y consultar el tratamiento en el historial. |
| **CU14. Gestionar órdenes de laboratorio**, p. 36 | **Sin flujo web** | Existen órdenes, análisis, detalles y reglas de integridad. Faltan selección de consulta/análisis, confirmación y gestión de órdenes desde la web. El catálogo de análisis tampoco figura entre los catálogos web habilitados. |
| **CU15. Registrar/cargar resultados**, p. 37 | **Sin flujo web** | Existen tablas de resultados, versiones y actualización de estados. Faltan la bandeja de laboratorio, registro de resultados y carga validada de adjuntos. |
| **CU16. Consultar resultados**, p. 38 | **Sin flujo web** | Hay datos y vistas SQL de apoyo. Faltan búsqueda, detalle de resultados, autorización por registro y acceso autenticado a los adjuntos. |
| **CU17. Reservar cita médica**, p. 39 | **Sin flujo web** | El paciente puede autenticarse, pero no tiene portal de especialidades, disponibilidad, resumen y reserva. Además, el vínculo cuenta-paciente no puede administrarse desde Usuarios y se pierde al editar una cuenta ya vinculada. |
| **CU18. Gestionar horarios médicos**, p. 40 | **Parcial** | Se registran, listan, modifican y desactivan franjas; se configura duración de 15, 30, 45 o 60 minutos. Existen validación de bloques, prevención de solapamiento y protección frente a cambios que afecten citas pendientes. Falta seleccionar/filtrar por médico en el listado, como indican los pasos 1-3; buscar su nombre no produce coincidencias. |
| **CU19. Generar reportes**, p. 41 | **Sin flujo web** | Existen vistas `reporte_citas`, `reporte_pacientes`, `reporte_atenciones`, `reporte_ordenes`, `reporte_resultados` y `resumen_citas_diarias`. Faltan selección de reporte, filtros, permisos, totales y presentación web. La exportación CSV/PDF es opcional según RF13. |
| **CU20. Consultar bitácora**, p. 42 | Implementado principal | `/bitacora` ofrece filtros por usuario, acción, entidad y fechas; incluye actor Sistema, paginación y detalle individual con IP. Se comprueba autorización y la consulta no modifica registros. |

**Recuento:** cinco implementados en su flujo principal —CU1, CU2, CU4, CU7 y CU20—; cuatro parciales —CU3, CU5, CU6 y CU18—; once sin flujo web —CU8 a CU17 y CU19—. Este recuento no es una estimación del porcentaje de esfuerzo completado: los casos tienen tamaños distintos.

## Hallazgos de la implementación

### H1. Editar una cuenta elimina su asociación con el paciente — Alta

**Ubicación:** [CatalogService.php](../app/Modules/Catalogos/Services/CatalogService.php), método `save`, líneas 138-147; [clinic.php](../config/clinic.php), definición `usuarios`, línea 10; [form.blade.php](../resources/views/catalog/form.blade.php), construcción de campos desde la definición.

**Reproducción comprobada:** iniciar como administrador, abrir la edición de la cuenta ficticia `paciente` y guardar sus mismos datos. El formulario no envía `paciente_id` porque no contiene ese campo. El servicio interpreta su ausencia como una desvinculación y guarda `paciente_id`, `paciente_verificado_por` y `paciente_verificado_en` en NULL. La solicitud termina con redirección 302 y sin errores de validación.

**Consecuencia:** la cuenta pierde la identidad clínica verificada que necesitará para el portal y sus consultas propias. No se borran el paciente ni sus citas; se pierde la asociación de la cuenta con esa persona.

**Corrección propuesta:** conservar el vínculo en una edición ordinaria. Establecer o quitar la asociación debe ser una operación explícita, autorizada y auditable. Incorporar una interfaz para administrar el vínculo, conservando las validaciones existentes. La creación ordinaria de una cuenta con rol Paciente actualmente también termina sin vínculo, por lo que el prerrequisito de CU17 no puede completarse desde ese formulario.

**Evidencia:** escenarios de edición y creación en [reproducciones.ndjson](evidencias-casos-de-uso-20261025/reproducciones.ndjson) y código reproducible en [UseCaseReviewProbeTest.php](evidencias-casos-de-uso-20261025/UseCaseReviewProbeTest.php).

### H2. Falta la consulta de horarios por médico — Media

La ficha CU18, pasos 1-3, contempla seleccionar un médico y consultar sus horarios. El listado actual tiene un buscador genérico, pero excluye los campos `medico` y `dia`; solo busca sobre las columnas horarias restantes. No existe un selector específico en ese listado.

**Reproducción comprobada:** en datos ficticios con horarios del médico `Dr Uno`, la consulta sin filtro devuelve 200; buscar `09:00` también devuelve 200; buscar `Dr Uno` devuelve 200 con cero filas. Se descartó un posible error de tipos SQL: Laravel convierte las columnas a texto para `ILIKE`, por lo que la búsqueda por hora funciona.

**Corrección propuesta:** añadir un filtro por `medico_id` y, si se conserva la búsqueda libre, permitir nombres del médico y día. Ubicación: [CatalogController.php](../app/Modules/Catalogos/Controllers/CatalogController.php), `index`, líneas 19-40, y [index.blade.php](../resources/views/catalog/index.blade.php).

### H3. Los permisos preparados no habilitan los módulos pendientes — Alta por alcance

[RolePermissionSeeder.php](../database/seeders/RolePermissionSeeder.php) define permisos de agenda, atención, laboratorio, portal y reportes. Sin embargo, [HomeController.php](../app/Modules/Inicio/Controllers/HomeController.php) solo construye accesos a los catálogos y la bitácora. Con los permisos de demostración predeterminados, Médico, Laboratorista y Paciente no reciben módulos funcionales de su actividad.

La ausencia se confirma en [web.php](../routes/web.php) y mediante `php artisan route:list --json`: solo se registran rutas de acceso, inicio, catálogos y bitácora, además de dos rutas técnicas. No hay controladores alternativos ni rutas de API que completen esos flujos en esta copia. Las solicitudes autenticadas a `/agenda`, `/historia`, `/atencion/1`, `/laboratorio/ordenes`, `/portal/reservar` y `/reportes` devolvieron 404 en la reproducción.

## Ajustes necesarios en los casos de uso del PDF

| Hallazgo documental | Evidencia | Ajuste recomendado y relación con la app |
| --- | --- | --- |
| Responsables contradictorios de pacientes, médicos y especialidades | CU5-CU7 nombran Recepcionista, pero sus precondiciones dicen Administrador; pp. 27-29. Los modelos de análisis de médicos/especialidades usan Administrador; pp. 50-51. | Especificar las operaciones de cada rol. La app actualmente permite a administración y recepción gestionar esos tres catálogos; recepción solo consulta horarios. |
| Paciente omitido en el texto de autenticación | CU1/CU2 describen cuatro roles del personal, aunque sus diagramas en las mismas pp. 23-24 y el diagrama general de p. 45 incluyen Paciente. CU17 necesita su sesión. | Usar Usuario del sistema y enumerar los cinco roles en las fichas. El código ya autentica al Paciente. |
| Variante del paciente incompleta en historia clínica | CU11 incluye Médico y Paciente como actores, pero sus condiciones y ocho pasos solo desarrollan la consulta del médico; p. 33. | Definir un flujo propio para consultar exclusivamente la historia del paciente autenticado y otro con búsqueda/alcance médico. Ninguno está implementado en la web todavía. |
| Historial de citas confundible con historia clínica | RF02/RF12 exigen citas anteriores y canceladas; pp. 6 y 10. CU11 aborda registros clínicos; p. 33. | Asignar explícitamente la consulta de citas por paciente a CU5/Agenda/Portal. Implementar CU11 por sí solo no cubre RF12. |
| Control de asistencia sin flujo completo | RF10 define Programada, Confirmada, Atendida, Cancelada e Inasistencia; p. 9. CU10 solo consulta y CU9 cancela/reprograma. El análisis de CU12 introduce `EnAtencion`; p. 52. | Definir transiciones, actor y condiciones en una tabla común e incorporarlas en los flujos pertinentes. La base usa los cinco estados de RF10; no añadir `EnAtencion` sin resolver el modelo. |
| Desactivar y eliminar horarios se representan como la misma operación | Ficha CU18, p. 40, describe desactivar y proteger citas; la secuencia de p. 77 incluye DELETE. | Representar la desactivación y la validación de citas afectadas. La web actual usa el estado activo. |
| Reglas de duración y restricciones SQL desactualizadas | RF05 exige definir duración; p. 7. P. 83 fija duración básica de 30 minutos y p. 84 dice que falta una exclusión de intervalos. | Documentar el valor inicial 30 y las duraciones configurables implementadas. El SQL actual sí contiene exclusión por médico y una migración añade exclusión por paciente. Las pp. 83-84 no describen completamente esta versión. |
| Responsabilidad de laboratorio inconsistente | La descripción de Laboratorista en p. 19 y el análisis CU14 en p. 53 incluyen ordenar análisis; la ficha CU14 de p. 36 solo asigna Médico. | Distinguir quién solicita y quién procesa. Los permisos predeterminados del código reservan la solicitud al Médico y el procesamiento al Laboratorista; falta llevarlos a los flujos web. |
| Alternativas y trazabilidad incompletas | CU8/CU9 enumeran errores sin respuesta o punto de retorno; pp. 30-31. CU3/CU4 y los casos clínicos/laboratorio no tienen RF específicos equivalentes en RF01-RF13. | Completar respuesta del sistema, conservación de datos y retorno de cada alternativa; construir una matriz RF-CU verificable. |
| Errores de copia y prioridad | El pequeño diagrama de CU4 está rotulado CU3 en p. 26; el de CU7 dice Gestionar Médicos en p. 29; CU5/CU6 usan «cuenta del usuario» en su postcondición. CU19 es Media en p. 22, mientras RF13 es Alta en p. 10. | Corregir rótulos, postcondiciones y prioridad antes de usar el documento como criterio final de aceptación. |

Estas contradicciones requieren una decisión funcional consistente; no todas constituyen errores del código. En particular, habilitar administración y recepción para médicos/especialidades es una decisión de alcance que el PDF debe expresar de forma uniforme.

## Comprobaciones realizadas

Las pruebas PHP se ejecutaron sobre **PostgreSQL en `127.0.0.1:55432`, base `Parcial2_test`**, con las barreras existentes que comprueban entorno, driver y nombre real antes de reconstruir el esquema. Esa instancia ya estaba iniciada y se dejó en el mismo estado. No se ejecutaron migraciones ni se modificaron datos de la base operativa `Parcial2`.

| Verificación de esta revisión | Resultado | Evidencia |
| --- | --- | --- |
| Suite PHP existente | **158 pruebas, 692 aserciones; sin fallos** | [phpunit.xml](evidencias-casos-de-uso-20261025/phpunit.xml) |
| Suite JavaScript existente | **40 pruebas; sin fallos** | `node --test tests/JavaScript/*.test.mjs`; resumen en [resumen.json](evidencias-casos-de-uso-20261025/resumen.json) |
| Reproducciones adicionales de revisión | **4 escenarios, 24 aserciones** | [reproducciones.xml](evidencias-casos-de-uso-20261025/reproducciones.xml) y [reproducciones.ndjson](evidencias-casos-de-uso-20261025/reproducciones.ndjson) |
| Inventario efectivo de rutas | Sin rutas funcionales de agenda, atención, laboratorio, portal o reportes | `php artisan route:list --json` y [web.php](../routes/web.php) |

**Las reproducciones adicionales comprueban el comportamiento actual, incluidos los defectos; que pasen no significa que esos defectos estén corregidos.** La suite existente tampoco cubre todas las operaciones y alternativas del PDF, como demuestra H1.

Para repetir las comprobaciones PHP contra esa misma instancia de pruebas, cuando esté disponible:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '55432'
$env:DB_DATABASE = 'Parcial2_test'
$env:DB_USERNAME = 'postgres'
$env:DB_PASSWORD = ''
$env:DB_URL = ''
php vendor/bin/phpunit
php vendor/bin/phpunit output/evidencias-casos-de-uso-20261025/UseCaseReviewProbeTest.php
node --test tests/JavaScript/*.test.mjs
```

Las reproducciones usan datos ficticios y transacciones de prueba. La evidencia `.ndjson` se añade al repetirlas. No se hicieron recorridos visuales del navegador ni mediciones de rendimiento. Por tanto, esta revisión no acredita los límites de 3 segundos para agenda o 5 segundos para reportes de RNF13/RNF16, ni el despliegue de todas las migraciones en otra instalación.

## Orden recomendado

1. Corregir H1 y añadir una operación explícita para asociar/verificar cuenta y paciente. Mejorar la consulta de horarios por médico.
2. Implementar CU8, CU9 y CU10 sobre las funciones y restricciones existentes: disponibilidad, reserva, conflictos concurrentes, cancelación, reprogramación, estados y agenda.
3. Completar el historial de citas de CU5/RF12 y la consulta de agenda desde CU6/RF03.
4. Implementar CU17 reutilizando las mismas reglas de reserva y limitando la identidad a la cuenta autenticada.
5. Implementar CU12/CU13 y la consulta clínica CU11 con autorización por paciente/atención.
6. Implementar CU14-CU16, incluidos adjuntos y su acceso autorizado; integrar resultados en CU11.
7. Implementar CU19 con filtros y totales comprobables y después verificar los recorridos de los cinco roles y el rendimiento.

Los planes de [corrección](../docs/plan_correccion_casos_implementados.md) e [implementación pendiente](../docs/plan_implementacion_casos_pendientes.md) siguen siendo una base útil. H1 y la consulta de horarios por médico deben incorporarse como trabajo todavía abierto. Esta revisión genera el informe y sus evidencias; no modifica la lógica de la aplicación ni el PDF original.

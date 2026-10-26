# Corrección de CU3, CU5, CU6 y CU18

Fecha: 25 de octubre de 2026. Esta entrega resuelve las cuatro brechas identificadas en la [comparación anterior](../output/comparacion-casos-de-uso-20261025.md).

| Caso | Corrección | Cómo utilizarla |
| --- | --- | --- |
| CU3. Gestionar usuarios | La edición ordinaria conserva el paciente asociado y su verificación. La asociación y retirada tienen una operación propia con confirmación, autorización, transacción y auditoría. | En Usuarios, seleccionar **Vínculo con paciente**, comprobar identidad, seleccionar el paciente y confirmar. También hay un enlace desde Editar usuario para cuentas con rol Paciente. |
| CU5. Gestionar pacientes | Consulta de todas las citas de un paciente, incluidas las pasadas y canceladas, con médico, especialidad, estado, código, horario y fecha de registro. | En Pacientes, seleccionar **Historial de citas**. Se puede filtrar por fechas, médico y estado. |
| CU6. Gestionar médicos | Consulta de la agenda del médico desde su registro, con filtro de fechas y estado. Incluye la consulta de CU10 necesaria para cubrir RF03. | En Médicos, seleccionar **Ver agenda**. El módulo **Agenda médica** también aparece en inicio para los usuarios autorizados. |
| CU18. Gestionar horarios | Selección de médico y día de atención, más búsqueda por nombre completo u hora y conservación de filtros al paginar. | En Horarios médicos, utilizar los selectores Médico y Día de atención y el campo Buscar. |

## Reglas de acceso y conservación

La gestión del vínculo requiere `usuarios.gestionar`. Los campos de vínculo y verificación se rechazan si se envían en la edición general del usuario. Para cambiar el rol de una cuenta asociada a un paciente debe retirarse primero ese vínculo de forma explícita.

La pantalla de vínculo muestra pacientes activos disponibles y el vínculo actual, incluso si ese paciente está inactivo. No permite asignar una persona ya vinculada a otra cuenta ni crear una nueva asociación con un paciente inactivo. Confirmar el mismo vínculo conserva el verificador y la fecha originales; retirarlo conserva al paciente y sus citas. Una cuenta nueva se crea desde Usuarios y luego se vincula desde la acción del listado.

El historial administrativo requiere `pacientes.gestionar`. Administración y recepción tienen ese acceso en la configuración predeterminada. El identificador del paciente está fijado por la ruta; los filtros no permiten consultar otro paciente en su lugar.

La agenda requiere `agenda.consultar`. Administración y recepción pueden consultar agendas mediante sus permisos administrativos. Cada médico se limita a su propio perfil, incluso si recibe permisos administrativos adicionales. El motivo de consulta se recupera únicamente para el médico propio con `atencion.registrar`; el resto de las consultas muestra datos administrativos.

Las consultas conservan personas y citas inactivas o canceladas y no modifican estados ni eventos. Se paginan con un orden estable. Los límites de fecha utilizan America/La_Paz e incluyen todo el último día seleccionado. La agenda abre en el día actual; se puede ampliar el intervalo o dejar ambas fechas vacías para consultar todas las fechas.

## Estado de los casos de uso después de esta entrega

| Estado del flujo principal | Casos |
| --- | --- |
| Implementado, incluidas las brechas corregidas | CU1, CU2, CU3, CU4, CU5, CU6, CU7, CU10, CU18 y CU20 |
| Sin flujo web operativo todavía | CU8, CU9, CU11, CU12, CU13, CU14, CU15, CU16, CU17 y CU19 |

CU10 corresponde a consulta de agenda. Las acciones web de reservar, cancelar, reprogramar, confirmar asistencia o registrar atención continúan en el plan pendiente. La consulta del historial de citas de CU5 es distinta de la historia clínica de CU11.

## Cambios técnicos

- `PatientAccountController` y `PatientAccountService`: pantalla y operación explícita de vínculo; se conserva el modelo de auditoría mediante los triggers existentes.
- `AppointmentQueryController` y `AppointmentQueryService`: consultas de agenda e historial con alcance por usuario y filtros validados.
- Catálogos: filtros de horarios y enlaces a historial, agenda y vínculo; acceso a Agenda desde inicio.
- Se utilizan las tablas, columnas y permisos existentes. **No se añade ninguna migración.** Las comprobaciones se ejecutan sobre `Parcial2_test` en la instancia independiente `127.0.0.1:55432`; no se usan datos de la base operativa como datos de prueba.

## Verificación

Se incorporan tres suites Feature permanentes: `PatientAccountManagementTest`, `AppointmentQueriesTest` y `ScheduleQueriesTest`. Cubren conservación de identidad y contraseña, confirmación, metadatos protegidos, duplicados, pacientes inactivos, permisos, aislamiento por médico/paciente, historial cancelado, fechas locales, paginación, escape de contenido y selección de horarios.

El resultado consolidado es **207 pruebas PHP y 913 aserciones**, incluidas **49 pruebas nuevas**, sin fallos ni omisiones; también aprobaron las **40 pruebas JavaScript** existentes, Pint y la compilación de vistas Blade. Los comandos están en [aceptacion.md](aceptacion.md) y la evidencia automatizada en [phpunit-casos-parciales-all.xml](../storage/logs/phpunit-casos-parciales-all.xml).

**Límite de interfaz:** Browser Use rechazó observar `http://127.0.0.1:8000` porque no se concedió permiso para ese origen. No se acredita una revisión visual ni móvil en navegador. Los formularios y listados sí se renderizaron y sus operaciones se comprobaron mediante las pruebas Feature. Tampoco se realizaron mediciones de rendimiento de agenda o reportes.

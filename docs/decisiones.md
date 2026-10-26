# Decisiones funcionales y técnicas

## Alcance ejecutado

Se ejecutó [el plan de corrección](plan_correccion_casos_implementados.md) y después la [corrección de CU3, CU5, CU6 y CU18](correccion_casos_parciales.md). Esta última incluye la consulta de agenda de CU10 necesaria para completar CU6. El resto del [plan de implementación](plan_implementacion_casos_pendientes.md) permanece pendiente.

La agenda web y el historial de citas son de solo lectura. Las tablas, funciones y permisos de reserva, atención, laboratorio y portal no equivalen a flujos web operativos. Las afirmaciones anteriores de implementación integral y sus cantidades históricas de pruebas no acreditan el estado de esta copia. La evidencia vigente se registra en [aceptacion.md](aceptacion.md).

## Entorno y conservación de datos

Se mantienen PHP >= 8.3 con `pdo_pgsql`, Laravel 13, PostgreSQL 18, esquema `vida_salud` y zona `America/La_Paz`. La interfaz usa Blade, CSS y JavaScript estáticos.

Las migraciones nuevas conservan el snapshot y las migraciones anteriores. No se vacía la base operativa ni se reemplazan cuentas, contraseñas o asignaciones personalizadas. Las pruebas se ejecutan en una instancia temporal independiente, en `Parcial2_test`; la base operativa `Parcial2` no se utiliza como destino de pruebas.

## Acceso y salida (CU2)

La salida comienza con una pantalla de confirmación que también funciona sin JavaScript. El POST registra `SALIDA` con actor e IP antes de invalidar la sesión. Si la transacción de auditoría falla, la sesión no se finaliza.

Un identificador derivado en el servidor de la sesión se guarda como `bitacora.clave_operacion`, con unicidad PostgreSQL. Dos solicitudes del mismo cierre no generan eventos duplicados, incluso si vence el bloqueo temporal de la sesión. Las filas antiguas conservan NULL en esa columna.

El cliente reintenta una sola vez exclusivamente cuando falla la red. No reintenta respuestas HTTP de error; un 419 conduce al GET de confirmación para comprobar la sesión y obtener un token vigente. La ruta también usa el bloqueo de sesión de Laravel para serializar envíos ordinarios.

## Roles y directorio (CU4, CU6, CU7)

| Rol | Funciones disponibles en esta entrega |
| --- | --- |
| Administrador | Usuarios y vínculos verificados con pacientes, roles, permisos, pacientes, médicos, especialidades, horarios, historial de citas, agenda y bitácora. |
| Recepcionista | Pacientes, médicos y especialidades; lectura de horarios, historial de citas y agenda. No administra cuentas ni roles. |
| Médico | Consulta de su propia agenda; ve el motivo de consulta si conserva `atencion.registrar`. |
| Laboratorista y paciente | Inicio, salida y panel de módulos. Sus flujos especializados siguen pendientes. |

Los accesos se comprueban en el servidor mediante permisos; la desactivación de una cuenta o rol bloquea la sesión. Los roles personalizados pueden configurarse desde administración.

Se permite eliminar solamente roles personalizados sin usuarios asociados, incluidos usuarios inactivos. Los cinco roles del sistema y el rol del actor permanecen protegidos. La eliminación de asignaciones y rol es transaccional y auditada por los triggers existentes.

La migración de permisos añade únicamente `medicos.gestionar` y `especialidades.gestionar` a recepción en instalaciones existentes. Los seeders inicializan esos accesos en instalaciones nuevas; repetirlos no reemplaza configuraciones posteriores. Revertir la migración de permisos no retira automáticamente accesos que administración haya confirmado.

Los datos médicos de nombres, apellidos y correo se guardan en la cuenta vinculada; registro profesional, teléfono y estado pertenecen al perfil. Ambas escrituras forman una transacción. El formulario de médico rechaza campos de privilegios y no cambia contraseña, rol, usuario o vínculo de paciente.

Una cuenta médica existente no se traslada a otro perfil. Recepción selecciona cuentas médicas previamente creadas por administración. Se puede dar de baja un perfil cuya cuenta ya esté inactiva, pero no reactivarlo desde el directorio mientras su cuenta siga inactiva.

Al editar o desactivar un médico se pueden conservar especialidades inactivas cuyo vínculo `medico_especialidad.activo` siga vigente. El formulario las muestra seleccionadas como «(inactiva)». No se pueden asignar especialidades inactivas ajenas ni recuperar vínculos retirados con ellas. Crear o reactivar un perfil exige al menos una especialidad activa. Se mantiene la selección mínima de una especialidad; durante una edición o baja puede ser una inactiva ya vinculada.

La sincronización conserva las filas y estados de los vínculos seleccionados sin reescribirlos, desactiva los retirados y crea o reactiva únicamente asignaciones con especialidades activas. La baja del perfil no elimina ni desactiva automáticamente sus vínculos. Los triggers existentes conservan la protección de citas futuras y la auditoría de los cambios efectivos.

El directorio permite buscar por nombre completo en orden nombres/apellidos y por las especialidades que muestra, incluidas las inactivas con vínculo vigente. Se normalizan espacios y se mantienen las coincidencias parciales sin distinguir mayúsculas, con las reglas actuales de acentos. Los vínculos retirados no producen coincidencias por especialidad y varias especialidades coincidentes no duplican médicos en la paginación.

Pacientes, médicos y especialidades se desactivan, sin borrar su historia. Se mantienen las restricciones que impiden dejar citas futuras pendientes sin resolver.

## Horarios y duración (CU18/RF05)

Cada horario configura bloques de 15, 30, 45 o 60 minutos, alineados desde el inicio de la franja. La franja debe contener bloques completos. Las citas anteriores conservan su duración original de 30 minutos.

El servidor PostgreSQL deriva duración y final de cada nueva cita o reprogramación desde su horario. No confía en esos valores proporcionados por el cliente. La duración se conserva en cada cita y los eventos nuevos guardan finales anteriores y nuevos; los eventos anteriores siguen intactos.

Las exclusiones de intervalos `[inicio, fin)` por médico y paciente se conservan: bloques contiguos son válidos y superpuestos se rechazan. Cambiar una duración que afecte citas futuras Programada o Confirmada exige resolverlas antes. No se reinterpretan ni desplazan citas automáticamente.

Las funciones SQL heredadas conservan sus firmas. La consulta de agenda está disponible desde el directorio y desde inicio; la reserva, cancelación y reprogramación web siguen pendientes. El listado de horarios permite seleccionar médico y día y buscar por nombre completo u hora, conservando los filtros al paginar e incluyendo profesionales inactivos para consulta.

## Cuentas y vínculo con paciente (CU3)

La edición ordinaria conserva `paciente_id` y su verificación. Rechaza esos campos en el formulario general. Una cuenta vinculada no cambia a otro rol hasta retirar explícitamente el vínculo.

La pantalla Vínculo con paciente exige `usuarios.gestionar`, verificación de identidad y confirmación. Permite asociar un paciente activo disponible, conservar el vínculo actual aunque esté inactivo o retirarlo sin borrar datos clínicos. Un paciente solo puede tener una cuenta; los controles de PostgreSQL y la transacción conservan esa unicidad. Guardar el mismo vínculo conserva fecha y verificador y no produce una actualización adicional.

## Historial de citas y agenda (CU5, CU6 y consulta CU10)

El historial se abre desde Pacientes con `pacientes.gestionar`. Incluye citas pasadas, futuras y canceladas, incluso con paciente o médico inactivos. El identificador del paciente lo fija la ruta y no puede sustituirse mediante filtros.

La agenda requiere `agenda.consultar`. Los usuarios con gestión administrativa de pacientes o médicos consultan las agendas; el rol Médico siempre queda limitado a su propio perfil, aunque reciba permisos administrativos adicionales. Paciente y Laboratorista no reciben acceso por sus permisos predeterminados. El motivo clínico se selecciona únicamente para el médico propio con `atencion.registrar`; administración y recepción reciben datos administrativos.

Ambas consultas admiten fechas y estado; la agenda administrativa y el historial permiten seleccionar médico. El límite final incluye todo el día en America/La_Paz. La agenda abre en el día actual y se puede ampliar el período, incluso dejando ambas fechas vacías. Los datos se paginan y las consultas no cambian estados ni generan eventos de cita.

## Bitácora (CU20)

Consulta de solo lectura con filtros por fechas, usuario, acción y entidad, paginación de 30 registros y detalle con IP. Se incluye la opción de actor Sistema y se conservan filtros al regresar desde un evento.

Las fechas deben ser fechas válidas en formato ISO; no se admite el año cero. El límite final incluye todo el día seleccionado. Las URLs de regreso se construyen desde rutas internas y filtros permitidos, no desde una URL enviada por el cliente.

## Decisiones del segundo plan que aún no se implementan

El paciente tendrá acceso a su historial propio completo y a los resultados desde su registro. El administrador tendrá todos los reportes descritos, incluidos los clínicos. Estas decisiones están registradas en el plan pendiente y no amplían permisos ni crean flujos clínicos en esta entrega.

## Verificación

Se conservan las barreras de pruebas antes de limpiar el esquema. Las verificaciones de compatibilidad usan datos ficticios y migraciones reales; no se deshabilitan controles durante las acciones bajo prueba.

Las pruebas de duración, perfiles, roles, salida y bitácora se complementan con validación JavaScript y recorridos de navegador. Las cifras definitivas, comandos y limitaciones corresponden a [aceptacion.md](aceptacion.md).

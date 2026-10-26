
# Sistema de Gestión - Clínica Vida Salud

## Descripción del proyecto

Este proyecto fue realizado como parte de un examen de la materia. Consiste en desarrollar un sistema web para la gestión de la Clínica Vida Salud, con el objetivo de facilitar la organización de sus actividades y mejorar el manejo de la información.

La copia actual permite administrar pacientes, médicos, especialidades, horarios, usuarios, roles y permisos, además de consultar la bitácora. La [corrección de los casos parciales](docs/correccion_casos_parciales.md) añade la administración explícita del vínculo cuenta-paciente, el historial de citas por paciente, la consulta de agenda y los filtros de horarios por médico y día.

La consulta de citas está disponible en la web. La creación, cancelación y reprogramación de citas, atención, laboratorio, portal del paciente y reportes todavía corresponden al [plan de implementación pendiente](docs/plan_implementacion_casos_pendientes.md).

## Tecnologías utilizadas

- PHP 8.3 o superior
- Laravel 13
- PostgreSQL
- Blade
- HTML, CSS y JavaScript

## Requisitos

Antes de instalar el proyecto, es necesario tener instalado:

- PHP 8.3 o superior, con la extensión `pdo_pgsql`.
- Composer 2.
- PostgreSQL 18, requerido por el snapshot SQL suministrado.
- PowerShell 5.1 o superior (Windows).

## Instalación

### 1. Descargar el proyecto

Clonar el repositorio desde la terminal:

```bash
git clone https://github.com/carlossww/2doParcial.git
cd 2doParcial
```

Si el proyecto ya está descargado, abrir una terminal en su carpeta principal.

### 2. Crear la base de datos

En PostgreSQL, crear una base de datos llamada `Parcial2`.

El sistema utiliza el esquema `vida_salud`. Si la base de datos ya contiene información, se recomienda realizar un respaldo antes de continuar.

### 3. Instalar y configurar

Desde PowerShell, ejecutar:

```powershell
.\scripts\install.ps1
```

El instalador prepara la configuración del proyecto, solicita los datos de conexión a PostgreSQL cuando corresponde, instala las dependencias y ejecuta las migraciones con los datos iniciales.

Si se necesita volver a configurar la conexión con la base de datos:

```powershell
.\scripts\install.ps1 -ConfigureDatabase
```

### 4. Iniciar el sistema

Ejecutar el siguiente comando:

```powershell
.\scripts\start.ps1
```

Abrir en el navegador la dirección local que aparece en la terminal. Por defecto:

http://127.0.0.1:8000

Para detener el servidor, presionar `Ctrl + C` en la terminal.

## Usuarios de prueba

El sistema cuenta con usuarios de demostración para probar las funciones de cada rol.

| Usuario | Función |
|---|---|
| `admin` | Administración general del sistema |
| `recepcion` | Gestión de pacientes, médicos y especialidades; consulta de horarios |
| `medico` | Consulta de su propia agenda; registro de atención web pendiente |
| `laboratorio` | Cuenta disponible; módulo de laboratorio web pendiente |
| `paciente` | Cuenta disponible; portal web pendiente |

**Contraseña predeterminada para las cuentas nuevas de demostración:**

`VidaSalud2026!`

La contraseña efectiva puede variar si las cuentas ya existían o fueron modificadas anteriormente.

## Funcionalidades principales

- Inicio de sesión y cierre con confirmación, auditoría e idempotencia por sesión.
- Gestión de usuarios, roles y permisos.
- Eliminación de roles personalizados sin usuarios, con protección de los roles del sistema.
- Registro y administración de pacientes.
- Gestión de médicos y especialidades por administrador y recepción.
- Edición de datos profesionales y de contacto sin conceder gestión de cuentas a recepción.
- Horarios con duración de 15, 30, 45 o 60 minutos y conservación de citas anteriores de 30 minutos.
- Bitácora con filtros por usuario, acción, entidad y fechas, más vista individual de detalle.
- Edición de usuarios que conserva la identidad clínica; vínculo con paciente en una pantalla propia con confirmación y verificación por administración.
- Historial de citas desde Pacientes, incluidas citas pasadas, canceladas y personas inactivas.
- Agenda desde Médicos y el inicio, con filtros de fecha, médico y estado; cada médico consulta únicamente su agenda.
- Filtros de horarios por médico y día, con búsqueda por nombre completo u hora.

Los resultados de pruebas y los límites de la verificación están en [docs/aceptacion.md](docs/aceptacion.md). Las consultas de citas son de solo lectura y conservan el historial.

## Consideraciones

- El archivo `.env` contiene datos de configuración y no debe subirse al repositorio.
- Las pruebas automatizadas deben ejecutarse sobre la base de datos independiente `Parcial2_test`.
- No ejecutar `migrate:fresh` sobre la base de datos operativa `Parcial2`.
- El servidor incluido está destinado al desarrollo y las pruebas locales.

---

**Proyecto para el 2do Parcial de Ingeniería de Requisitos y Modelado — Sistema de Gestión de la Clínica Vida Salud.**


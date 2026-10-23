# Módulo: Solicitudes de Proveedores

Los proveedores (laboratorios) envían solicitudes a un proceso (mercadeo) y siguen su estado. Mercadeo las asigna, las gestiona y conversa con el proveedor; el gerente ve un dashboard.

Compatible con **PHP 5.4** y `mssql_*` (SQL Server 2008+). Sin Composer ni dependencias de servidor.

---

## 1. Instalación

1. Ejecute `adg/sql/solicitudes_proveedores.sql` en la base de datos del ADG.
   - Si ya lo había ejecutado antes de la tabla de lecturas, ejecute solo `adg/sql/solicitudes_proveedores_v2_lecturas.sql`.
   - Crea las 7 tablas `T_MER_*`.
   - Crea los tipos *Descuentos*, *Bonificados* y *Otras solicitudes* con el primer proceso que contenga "MERCADEO" en `T_CAL_PROCESOS`. Si su proceso tiene otro nombre, ajuste el `SELECT` del bloque 8.
2. Revise `adg/models/SolicitudesProveedores/config.php`:
   - `db_charset`: use `ISO-8859-1` si FreeTDS entrega Latin-1 (lo normal con `Modern_Spanish_CI_AS`). Use `UTF-8` si `freetds.conf` tiene `client charset = UTF-8`.
   - `adjuntos.ruta_ftp`: carpeta remota donde `ServiceUploadFile.php` deja los archivos. **La carpeta debe existir en el FTP.**
   - `roles`: IDs de `T_ROLES` (1 administrador, 118 gerente, 6 auxiliar, 9 proveedor).
3. Registre el módulo en el menú (`T_MODULOS`) con **`ARCHIVO = 'solicitudes-proveedores/index'`**. El script `adg/sql/solicitudes_proveedores_menu.sql` trae el `INSERT` de ejemplo. Dé acceso a los roles 1, 6, 9 y 118.

   El `index.php` muestra la vista según el rol de la sesión:

| Rol | Vista |
|---|---|
| 9 proveedor | Mis solicitudes (`sp_proveedor.php`) |
| 1, 118, 6 | Bandeja / Historial / Dashboard (`sp_mercadeo.php`) |
| 1 + `?vista=tipos` | Tipos de solicitud (`sp_tipos_solicitud.php`); el administrador tiene un botón en mercadeo |

   Funciona igual si su menú **navega** a la URL, la **incluye** (`include`) dentro de su plantilla, o la **carga por AJAX** en un contenedor:
   - Si la vista se pide directamente, devuelve la página completa.
   - Si se incluye o se carga por AJAX, devuelve solo el bloque del módulo.
   - Las rutas de CSS, JS, controlador y `ServiceUploadFile.php` son absolutas (por ejemplo `/calidad/...`) y se calculan solas.
   - Si su servidor usa alias y no se calculan bien, defina `url_app` en `config.php`.
   - jQuery, SheetJS y `servicios.js` solo se cargan si la página aún no los tiene.

Puede abrir una solicitud directamente con `?id=123` (útil para enlaces en correos).

> Las vistas cargan jQuery 3.7.1 (cdnjs) y SheetJS 0.20.3 (`cdn.sheetjs.com`). Si el ADG ya incluye jQuery, o si la red bloquea esos CDN, descárguelos a `lib/js/` y cambie las rutas en `views/solicitudes-proveedores/sp_layout.php`.

---

## 2. Arquitectura (hexagonal)

```
adg/
├── controllers/solicitudes-proveedores/SolicitudesProveedoresController.php   ← único endpoint (POST accion=...)
├── models/SolicitudesProveedores/
│   ├── config.php                ← reglas parametrizables
│   ├── bootstrap.php             ← autoload
│   ├── Dominio/                  ← reglas de negocio puras (no conoce BD ni HTTP)
│   │   ├── Actor.php             ← usuario de la sesión
│   │   ├── Solicitud.php         ← entidad
│   │   ├── EstadoSolicitud.php   ← máquina de estados PENDIENTE → EN_CURSO → FINALIZADO
│   │   ├── PoliticaAcceso.php    ← quién puede qué (única fuente de permisos)
│   │   ├── Alcance.php           ← qué puede listar cada rol
│   │   ├── ReglasAdjunto.php     ← validación de archivos subidos
│   │   ├── Validacion/           ← Validador + Reglas (Requerido, Entero, Decimal, ...)
│   │   ├── Plantilla/            ← estrategia por tipo: Libre, Descuentos, Bonificados
│   │   ├── Puerto/               ← interfaces (repositorios, transacción, notificador)
│   │   └── Excepcion/
│   ├── Aplicacion/               ← casos de uso (uno por acción)
│   └── Infraestructura/          ← adaptadores
│       ├── Persistencia/         ← ConexionMssql + repositorios Mssql*
│       ├── Http/                 ← ControladorHttp (acción → caso de uso), sesión → Actor
│       ├── Notificacion/         ← NotificadorNulo (reemplazable por correo)
│       └── Contenedor.php        ← composición de dependencias
├── views/solicitudes-proveedores/       ← index (entrada por rol), sp_layout, sp_proveedor, sp_mercadeo, sp_tipos_solicitud
├── lib/js/solicitudes-proveedores/
│   ├── sp-core.js      ← API (usa enviarPeticion), drawer, toasts, cargador de archivos (Ctrl+V)
│   ├── sp-excel.js     ← lectura/plantillas/exportación con SheetJS
│   ├── sp-detalle.js   ← detalle compartido (proveedor y mercadeo)
│   ├── sp-proveedor.js / sp-mercadeo.js / sp-tipos.js
├── lib/css/solicitudes-proveedores/solicitudes_proveedores.css   ← prefijo .sp-, tema por organización
├── sql/solicitudes_proveedores.sql        ← tablas
├── sql/solicitudes_proveedores_menu.sql   ← registro en T_MODULOS
└── tests/SolicitudesProveedores/DominioTest.php
```

Las dependencias van hacia adentro: `Infraestructura → Aplicacion → Dominio`. El dominio nunca lee `$_SESSION` ni ejecuta SQL.

### Flujo de una petición

```
JS: SP.api('cambiar_estado', {...})  →  enviarPeticion()  →  controllers/solicitudes-proveedores/SolicitudesProveedoresController.php
  → ControladorHttp: valida POST + X-Requested-With, arma Actor desde $_SESSION
  → Contenedor->obtener('solicitudes.estado')->ejecutar($actor, $_POST)
       → PoliticaAcceso (permiso) → Validador (datos) → Solicitud->cambiarEstado() (regla)
       → Transaccion: repositorio + historial → Notificador
  ← { ok: true, datos } | { ok: false, mensaje, errores } con HTTP 4xx/5xx
```

### Acciones disponibles

| accion | Caso de uso | Quién |
|---|---|---|
| `catalogos` | Datos iniciales de la vista | todos |
| `tipos_listar`, `tipos_guardar`, `tipos_estado` | Administración de tipos | admin (listar: todos) |
| `analizar_detalle` | Prevalida el Excel contra `T_MATERIALES` | proveedor |
| `crear` | Crea la solicitud + detalle + adjuntos | proveedor |
| `listar` | Bandeja / historial con filtros y paginación | todos (según alcance) |
| `ver` | Detalle + comentarios + historial + permisos | todos (según alcance) |
| `asignar` | Gerente asigna a cualquiera; auxiliar solo a sí mismo | gestores |
| `cambiar_estado` | Pendiente → En curso → Finalizado | asignado o gerente |
| `comentar` | Comentario + adjuntos (nota interna opcional) | gestores y proveedor |
| `dashboard` | Indicadores por rango de fechas y filtros | gestores |

---

## 3. Reglas de negocio implementadas

- **Proveedor (9)**: crea solicitudes y ve las de **su laboratorio** (`ses_CodSap` → `T_TERCEROS`). Puede comentar mientras la solicitud no esté finalizada. No ve las notas internas.
- **Gerente (118) y administrador (1)**: asignan a cualquier gerente o auxiliar, cambian cualquier estado y ven el dashboard.
- **Auxiliar (6)**: solo **se asigna a sí mismo** solicitudes sin asignar. Solo cambia el estado de las suyas. Si pasa a "En curso" una solicitud sin asignar, queda asignada a él.
- Mercadeo ve las solicitudes de su `ORGANIZACION_VENTA`. Configurable: `gestor_filtra_por_organizacion`, y `gestor_filtra_por_proceso_rol` para filtrar por `T_ROLES.PROCESO_ID`.
- **Finalizar exige un comentario de cierre** (lo ve el proveedor). No se puede reabrir.
- **Descuentos**: `CODIGO_MATERIAL`, `DESCUENTO` (0 < d ≤ 100, acepta "10%", "10,5"). Códigos sin repetir. Avisa si el descuento es < 1 % (posible 0,1 en lugar de 10 %).
- **Bonificados**: `CODIGO_MATERIAL_REGULAR`, `CANTIDAD_REGULAR`, `CODIGO_MATERIAL_BONIFICADO`, `CANTIDAD_BONIFICADA` (enteros > 0). Combinación sin repetir. Avisa si lo bonificado supera lo regular.
- Las descripciones **no se guardan**: siempre se leen de `T_MATERIALES`. Si Excel borró los ceros a la izquierda, el código también se busca con relleno a 18 dígitos.
- Material anulado o inexistente → error de la fila. Opcional: `validar_material_del_laboratorio`.
- Todo cambio queda en `T_MER_SOLICITUDES_PROV_HISTORIAL` (creación, asignación, estado, comentario).
- **Respuestas nuevas**: se guarda cuándo abrió cada usuario cada solicitud (`T_MER_SOLICITUDES_PROV_LECTURAS`).
  - El proveedor ve lo que hizo mercadeo después de su última visita (asignación, cambio de estado, mensajes; nunca las notas internas):
    - un aviso arriba con un botón "Ver respuestas" que filtra;
    - un punto en la pestaña de cada estado;
    - la fila resaltada con la última novedad;
    - dentro del detalle, lo nuevo marcado.
  - Mercadeo ve igual lo que hizo el proveedor: solicitudes nuevas y mensajes.
  - Las listas se actualizan solas cada 60 segundos y avisan cuando llega algo nuevo.
- Si el gerente asigna la solicitud a otra persona, el detalle se cierra. El detalle se abre a pantalla completa.

---

## 4. Cómo extender

**Nueva regla de validación**: cree `Dominio/Validacion/Reglas/MiRegla.php` implementando `Regla`. Luego úsela: `$v->campo('x', 'X', array(new Reglas\MiRegla()))`. Para algo puntual use `new Reglas\Callback(function ($valor, $datos) { ... }, 'mensaje')`.

**Nueva exigencia al cambiar de estado**: `CambiarEstadoSolicitud::validadorPara()`.

**Nuevo estado** (p. ej. `RECHAZADO`): agregue constante, etiqueta y transiciones en `EstadoSolicitud`, el valor en el `CHECK` de la tabla y la etiqueta en `SP.ESTADOS` (sp-core.js).

**Nueva plantilla Excel** (p. ej. "Cambio de precio"):
1. Clase en `Dominio/Plantilla/` que extienda `PlantillaExcelBase` (columnas + `analizar`).
2. Regístrela en `RegistroPlantillas::predeterminado()`.
3. Tabla de detalle + `guardarX/obtenerX` en `DetalleRepositorio` y `MssqlDetalleRepositorio`.
4. Agregue el código al `CHECK` de `T_MER_TIPOS_SOLICITUD_PROV` y sus columnas en `SP.excel.columnasDetalle`.

El formulario del proveedor, la plantilla descargable y la prevalidación salen de `columnas()` automáticamente.

**Nuevo permiso**: método en `PoliticaAcceso` y, si la UI lo necesita, inclúyalo en `permisos()`.

**Notificaciones por correo**: implemente `Dominio\Puerto\Notificador` (p. ej. con su función de correo del ADG) y cámbielo en `Contenedor::registrarServicios()`. Los eventos ya se emiten.

**Nueva acción HTTP**: caso de uso en `Aplicacion/`, registro en `Contenedor` y ruta en `ControladorHttp::$rutas`.

---

## 5. Notas técnicas

- `filas` y `adjuntos` viajan como **JSON en un solo campo**. Así se evita el límite `max_input_vars` (1000) de PHP con Excel grandes.
- Las fechas se envían a SQL Server como `YYYYMMDD` y se leen con `CONVERT(varchar(19), x, 120)`. Así no dependen del idioma ni del `mssql.datetimeconvert`.
- Paginación con `ROW_NUMBER()` (compatible 2008). Inserción del detalle por lotes con `INSERT ... SELECT ... UNION ALL` (compatible 2005+).
- `ConexionMssql` escapa todo parámetro (`:nombre`) según su tipo. No concatene valores del usuario en SQL.
- Protección CSRF básica: el endpoint exige `X-Requested-With: XMLHttpRequest` (jQuery lo envía).
- Se validan la URL y la ruta de cada adjunto contra `ruta_ftp`: no se puede registrar un enlace externo.

- Rutas: `sp_layout.php` inyecta `window.SP_CONFIG` con las URLs absolutas del controlador, de `ServiceUploadFile.php` y de las vistas. Como `subirArchivos()` de `servicios.js` tiene la ruta fija `../models/ServiceUploadFile.php`, `sp-core.js` la corrige con un `$.ajaxPrefilter` solo para esa URL, sin modificar `servicios.js`.

### Observaciones sobre código existente (no modificado)

- `servicios.js → validateFileUpload`: la validación de tamaño está invertida (lanza error cuando el archivo es *menor* que el máximo). Por eso el módulo llama `subirArchivos` con `validateSize: false` y valida tamaño y extensión antes. También usa variables globales implícitas (`keys`, `valid`, `tamanio`).
- `ServiceUploadFile.php` no valida extensión, tamaño ni la `ruta` recibida. Conviene restringir la ruta a una lista blanca y rechazar extensiones ejecutables (`.php`, `.phtml`, etc.).

### Pruebas

```
php adg/tests/SolicitudesProveedores/DominioTest.php
```

Cubren escapado SQL, máquina de estados, permisos por rol, plantillas Excel, validador y adjuntos. No requieren base de datos.

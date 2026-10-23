/* =====================================================================
   MODULO: SOLICITUDES DE PROVEEDORES (MERCADEO)
   Motor : SQL Server (2008+). No usa OFFSET/FETCH ni TRY_CAST.
   Ejecutar una sola vez en la base de datos del ADG.
   ===================================================================== */

/* ---------------------------------------------------------------------
   1. TIPOS DE SOLICITUD (los administra el rol 1)
   PLANTILLA define el comportamiento del detalle:
     LIBRE       -> adjuntos libres (pdf, word, imagen, ...)
     DESCUENTOS  -> Excel obligatorio: CODIGO_MATERIAL, DESCUENTO
     BONIFICADOS -> Excel obligatorio: COD. REGULAR, CANT. REGULAR,
                    COD. BONIFICADO, CANT. BONIFICADA
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_TIPOS_SOLICITUD_PROV] (
  [ID]                 int IDENTITY(1, 1) NOT NULL,
  [NOMBRE]             varchar(100) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [DESCRIPCION]        varchar(500) COLLATE Modern_Spanish_CI_AS NULL,
  [PROCESO_ID]         int NOT NULL,
  [PLANTILLA]          varchar(20) COLLATE Modern_Spanish_CI_AS DEFAULT 'LIBRE' NOT NULL,
  [REQUIERE_ADJUNTO]   bit DEFAULT 0 NOT NULL,
  [EXTENSIONES]        varchar(200) COLLATE Modern_Spanish_CI_AS NULL,
  [ACTIVO]             bit DEFAULT 1 NOT NULL,
  [ORDEN]              int DEFAULT 0 NOT NULL,
  [USUARIO_CREA]       int NULL,
  [FECHA_CREACION]     datetime DEFAULT getdate() NOT NULL,
  [USUARIO_MODIFICA]   int NULL,
  [FECHA_MODIFICACION] datetime NULL,
  CONSTRAINT [T_MER_TIPOS_SOLICITUD_PROV_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_TIPOS_SOLICITUD_PROV_fk_proceso] FOREIGN KEY ([PROCESO_ID]) REFERENCES [dbo].[T_CAL_PROCESOS] ([ID]),
  CONSTRAINT [T_MER_TIPOS_SOLICITUD_PROV_ck_plantilla] CHECK ([PLANTILLA] IN ('LIBRE', 'DESCUENTOS', 'BONIFICADOS'))
)
ON [PRIMARY]
GO

/* ---------------------------------------------------------------------
   2. SOLICITUDES
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROVEEDORES] (
  [ID]                   int IDENTITY(1, 1) NOT NULL,
  [TIPO_ID]              int NOT NULL,
  [ASUNTO]               varchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [OBSERVACION]          varchar(max) COLLATE Modern_Spanish_CI_AS NULL,
  [ESTADO]               varchar(20) COLLATE Modern_Spanish_CI_AS DEFAULT 'PENDIENTE' NOT NULL,
  [CODIGO_SAP_PROVEEDOR] int NOT NULL,
  [USUARIO_ID]           int NOT NULL,
  [ORGANIZACION_VENTA]   char(4) COLLATE Modern_Spanish_CI_AS NULL,
  [OFICINA_VENTAS]       char(4) COLLATE Modern_Spanish_CI_AS NULL,
  [ASIGNADO_A]           int NULL,
  [FECHA_ASIGNACION]     datetime NULL,
  [FECHA_CREACION]       datetime DEFAULT getdate() NOT NULL,
  [FECHA_INICIO_GESTION] datetime NULL,
  [FECHA_FINALIZACION]   datetime NULL,
  [FECHA_MODIFICACION]   datetime NULL,
  CONSTRAINT [T_MER_SOLICITUDES_PROVEEDORES_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_SOLICITUDES_PROVEEDORES_fk_tipo] FOREIGN KEY ([TIPO_ID]) REFERENCES [dbo].[T_MER_TIPOS_SOLICITUD_PROV] ([ID]),
  CONSTRAINT [T_MER_SOLICITUDES_PROVEEDORES_fk_usuario] FOREIGN KEY ([USUARIO_ID]) REFERENCES [dbo].[T_USUARIOS] ([ID]),
  CONSTRAINT [T_MER_SOLICITUDES_PROVEEDORES_fk_asignado] FOREIGN KEY ([ASIGNADO_A]) REFERENCES [dbo].[T_USUARIOS] ([ID]),
  CONSTRAINT [T_MER_SOLICITUDES_PROVEEDORES_ck_estado] CHECK ([ESTADO] IN ('PENDIENTE', 'EN_CURSO', 'FINALIZADO'))
)
ON [PRIMARY]
GO

CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_ESTADO] ON [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ESTADO], [FECHA_CREACION]) INCLUDE ([ASIGNADO_A], [CODIGO_SAP_PROVEEDOR], [TIPO_ID])
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_PROVEEDOR] ON [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([CODIGO_SAP_PROVEEDOR], [ESTADO])
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_ASIGNADO] ON [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ASIGNADO_A], [ESTADO])
GO

/* ---------------------------------------------------------------------
   3. DETALLE: DESCUENTOS (la descripcion se toma de T_MATERIALES)
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_DESCUENTOS] (
  [ID]              int IDENTITY(1, 1) NOT NULL,
  [SOLICITUD_ID]    int NOT NULL,
  [FILA]            int NOT NULL,
  [CODIGO_MATERIAL] varchar(18) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [DESCUENTO]       numeric(5, 2) NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_DESCUENTOS_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_DESCUENTOS_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_DESCUENTOS_fk_mat] FOREIGN KEY ([CODIGO_MATERIAL]) REFERENCES [dbo].[T_MATERIALES] ([CODIGO_MATERIAL]),
  CONSTRAINT [T_MER_SOL_PROV_DESCUENTOS_ck] CHECK ([DESCUENTO] > 0 AND [DESCUENTO] <= 100)
)
ON [PRIMARY]
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_DESC_SOL] ON [dbo].[T_MER_SOLICITUDES_PROV_DESCUENTOS] ([SOLICITUD_ID])
GO

/* ---------------------------------------------------------------------
   4. DETALLE: BONIFICADOS
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_BONIFICADOS] (
  [ID]                         int IDENTITY(1, 1) NOT NULL,
  [SOLICITUD_ID]               int NOT NULL,
  [FILA]                       int NOT NULL,
  [CODIGO_MATERIAL_REGULAR]    varchar(18) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [CANTIDAD_REGULAR]           int NOT NULL,
  [CODIGO_MATERIAL_BONIFICADO] varchar(18) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [CANTIDAD_BONIFICADA]        int NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_BONIF_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_BONIF_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_BONIF_fk_reg] FOREIGN KEY ([CODIGO_MATERIAL_REGULAR]) REFERENCES [dbo].[T_MATERIALES] ([CODIGO_MATERIAL]),
  CONSTRAINT [T_MER_SOL_PROV_BONIF_fk_bon] FOREIGN KEY ([CODIGO_MATERIAL_BONIFICADO]) REFERENCES [dbo].[T_MATERIALES] ([CODIGO_MATERIAL]),
  CONSTRAINT [T_MER_SOL_PROV_BONIF_ck] CHECK ([CANTIDAD_REGULAR] > 0 AND [CANTIDAD_BONIFICADA] > 0)
)
ON [PRIMARY]
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_BONIF_SOL] ON [dbo].[T_MER_SOLICITUDES_PROV_BONIFICADOS] ([SOLICITUD_ID])
GO

/* ---------------------------------------------------------------------
   5. COMENTARIOS (INTERNO = 1 no lo ve el proveedor)
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_COMENTARIOS] (
  [ID]             int IDENTITY(1, 1) NOT NULL,
  [SOLICITUD_ID]   int NOT NULL,
  [USUARIO_ID]     int NOT NULL,
  [COMENTARIO]     varchar(max) COLLATE Modern_Spanish_CI_AS NULL,
  [INTERNO]        bit DEFAULT 0 NOT NULL,
  [FECHA_CREACION] datetime DEFAULT getdate() NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_COMENT_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_COMENT_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID])
)
ON [PRIMARY]
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_COMENT_SOL] ON [dbo].[T_MER_SOLICITUDES_PROV_COMENTARIOS] ([SOLICITUD_ID])
GO

/* ---------------------------------------------------------------------
   6. ADJUNTOS (archivo fisico en FTP via ServiceUploadFile.php)
   ES_PLANTILLA = 1 -> Excel de descuentos / bonificados
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_ADJUNTOS] (
  [ID]              int IDENTITY(1, 1) NOT NULL,
  [SOLICITUD_ID]    int NOT NULL,
  [COMENTARIO_ID]   int NULL,
  [NOMBRE_ORIGINAL] varchar(255) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [RUTA]            varchar(400) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [URL]             varchar(500) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [EXTENSION]       varchar(10) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [TAMANO_KB]       int DEFAULT 0 NOT NULL,
  [ES_PLANTILLA]    bit DEFAULT 0 NOT NULL,
  [USUARIO_ID]      int NOT NULL,
  [FECHA_CREACION]  datetime DEFAULT getdate() NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_ADJ_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_ADJ_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_ADJ_fk_com] FOREIGN KEY ([COMENTARIO_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROV_COMENTARIOS] ([ID])
)
ON [PRIMARY]
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_ADJ_SOL] ON [dbo].[T_MER_SOLICITUDES_PROV_ADJUNTOS] ([SOLICITUD_ID])
GO

/* ---------------------------------------------------------------------
   7. HISTORIAL / TRAZABILIDAD (cada evento queda registrado)
   EVENTO: CREADA, ASIGNADA, ESTADO, COMENTARIO
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_HISTORIAL] (
  [ID]                int IDENTITY(1, 1) NOT NULL,
  [SOLICITUD_ID]      int NOT NULL,
  [USUARIO_ID]        int NOT NULL,
  [EVENTO]            varchar(30) COLLATE Modern_Spanish_CI_AS NOT NULL,
  [ESTADO_ANTERIOR]   varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
  [ESTADO_NUEVO]      varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
  [ASIGNADO_ANTERIOR] int NULL,
  [ASIGNADO_NUEVO]    int NULL,
  [DETALLE]           varchar(500) COLLATE Modern_Spanish_CI_AS NULL,
  [FECHA_CREACION]    datetime DEFAULT getdate() NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_HIST_pk] PRIMARY KEY CLUSTERED ([ID]),
  CONSTRAINT [T_MER_SOL_PROV_HIST_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID])
)
ON [PRIMARY]
GO
CREATE NONCLUSTERED INDEX [IX_MER_SOL_PROV_HIST_SOL] ON [dbo].[T_MER_SOLICITUDES_PROV_HISTORIAL] ([SOLICITUD_ID], [FECHA_CREACION])
GO

/* ---------------------------------------------------------------------
   7b. LECTURAS: ultima vez que cada usuario abrio la solicitud.
   Permite mostrar "respuesta nueva de mercadeo" al proveedor (y viceversa).
   --------------------------------------------------------------------- */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_LECTURAS] (
  [SOLICITUD_ID]  int NOT NULL,
  [USUARIO_ID]    int NOT NULL,
  [FECHA_LECTURA] datetime DEFAULT getdate() NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_LECT_pk] PRIMARY KEY CLUSTERED ([SOLICITUD_ID], [USUARIO_ID]),
  CONSTRAINT [T_MER_SOL_PROV_LECT_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID])
)
ON [PRIMARY]
GO

/* ---------------------------------------------------------------------
   8. DATOS INICIALES
   Reemplace @PROCESO_MERCADEO por el ID real de T_CAL_PROCESOS.
   --------------------------------------------------------------------- */
DECLARE @PROCESO_MERCADEO int
SELECT TOP 1 @PROCESO_MERCADEO = ID FROM [dbo].[T_CAL_PROCESOS] WHERE PROCESO LIKE '%MERCADEO%' ORDER BY ID

IF @PROCESO_MERCADEO IS NOT NULL
BEGIN
  INSERT INTO [dbo].[T_MER_TIPOS_SOLICITUD_PROV] (NOMBRE, DESCRIPCION, PROCESO_ID, PLANTILLA, REQUIERE_ADJUNTO, EXTENSIONES, ORDEN)
  VALUES ('Descuentos', 'Solicitud de descuentos por material. Requiere el Excel con CODIGO_MATERIAL y DESCUENTO (%).', @PROCESO_MERCADEO, 'DESCUENTOS', 1, 'xlsx,xls', 1)

  INSERT INTO [dbo].[T_MER_TIPOS_SOLICITUD_PROV] (NOMBRE, DESCRIPCION, PROCESO_ID, PLANTILLA, REQUIERE_ADJUNTO, EXTENSIONES, ORDEN)
  VALUES ('Bonificados', 'Solicitud de bonificados. Requiere el Excel con material regular, cantidad regular, material bonificado y cantidad bonificada.', @PROCESO_MERCADEO, 'BONIFICADOS', 1, 'xlsx,xls', 2)

  INSERT INTO [dbo].[T_MER_TIPOS_SOLICITUD_PROV] (NOMBRE, DESCRIPCION, PROCESO_ID, PLANTILLA, REQUIERE_ADJUNTO, EXTENSIONES, ORDEN)
  VALUES ('Otras solicitudes', 'Cualquier otra solicitud a mercadeo. Puede adjuntar PDF, Word, Excel o imagenes.', @PROCESO_MERCADEO, 'LIBRE', 0, 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,png,jpg,jpeg', 3)
END
GO

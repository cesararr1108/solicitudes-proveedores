/* =====================================================================
   MIGRACION v2: lecturas por usuario (indicador de "respuesta nueva").
   Ejecutar UNA vez si ya habia ejecutado solicitudes_proveedores.sql.
   ===================================================================== */
CREATE TABLE [dbo].[T_MER_SOLICITUDES_PROV_LECTURAS] (
  [SOLICITUD_ID]  int NOT NULL,
  [USUARIO_ID]    int NOT NULL,
  [FECHA_LECTURA] datetime DEFAULT getdate() NOT NULL,
  CONSTRAINT [T_MER_SOL_PROV_LECT_pk] PRIMARY KEY CLUSTERED ([SOLICITUD_ID], [USUARIO_ID]),
  CONSTRAINT [T_MER_SOL_PROV_LECT_fk_sol] FOREIGN KEY ([SOLICITUD_ID]) REFERENCES [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ([ID])
)
ON [PRIMARY]
GO

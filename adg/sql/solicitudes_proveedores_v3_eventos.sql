/* =====================================================================
   MIGRACION v3: asociar descuentos / bonificados a un evento comercial
   (T_EVENTOS_CONVOCATORIA). El campo es opcional.
   Ejecutar UNA vez en la base de datos del ADG.
   ===================================================================== */
ALTER TABLE [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ADD [EVENTO_ID] int NULL
GO
ALTER TABLE [dbo].[T_MER_SOLICITUDES_PROVEEDORES] ADD CONSTRAINT [T_MER_SOLICITUDES_PROVEEDORES_fk_evento]
  FOREIGN KEY ([EVENTO_ID]) REFERENCES [dbo].[T_EVENTOS_CONVOCATORIA] ([ID])
GO

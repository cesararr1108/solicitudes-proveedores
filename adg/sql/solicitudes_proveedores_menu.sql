/* =====================================================================
   REGISTRO DEL MODULO EN EL MENU (T_MODULOS)
   ARCHIVO = carpeta/archivo dentro de views/, sin extension (igual que 'humana/SolicitudesHumana').
   El index.php del modulo muestra la vista segun el rol:
     9 proveedor -> Mis solicitudes | 1, 118, 6 -> Bandeja / Historial / Dashboard
   El administrador entra a "Tipos de solicitud" con el boton de la vista de mercadeo.
   Ajuste NUMERO, GRUPO, NOMBRE_GRUPO e ICON a como los maneja su menu.
   ===================================================================== */
INSERT INTO [dbo].[T_MODULOS] (TITULO, DESCRIPCION, NUMERO, ARCHIVO, GRUPO, NOMBRE_GRUPO, ICON)
VALUES ('Solicitudes proveedores',
        'Solicitudes de proveedores a mercadeo: descuentos, bonificados y otras',
        '1003',                                  -- NUMERO: siguiente consecutivo de su menu
        'solicitudes-proveedores/index',
        NULL,                                    -- GRUPO
        NULL,                                    -- NOMBRE_GRUPO
        NULL)                                    -- ICON
GO

/* Opcional: entrada separada solo para el administrador (Tipos de solicitud). */
-- INSERT INTO [dbo].[T_MODULOS] (TITULO, DESCRIPCION, NUMERO, ARCHIVO)
-- VALUES ('Tipos solicitud proveedores', 'Administracion de tipos de solicitud', '1004', 'solicitudes-proveedores/sp_tipos_solicitud')
-- GO

/* Recuerde dar acceso al modulo a los roles 1, 6, 9 y 118 en la tabla de permisos de su menu. */

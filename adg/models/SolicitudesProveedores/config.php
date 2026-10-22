<?php
/**
 * Configuracion del modulo Solicitudes de Proveedores.
 * Todo lo que es "regla de negocio parametrizable" vive aqui.
 */
return array(

    // Roles de T_ROLES que participan en el modulo
    'roles' => array(
        'administrador' => 1,
        'gerente'       => 118,
        'auxiliar'      => 6,
        'proveedor'     => 9,
    ),

    // URL web de la carpeta de la aplicacion, por ejemplo '/calidad/'.
    // Vacio = se detecta sola. Definala si el servidor usa alias o enlaces simbolicos y las rutas fallan.
    'url_app' => '',

    // Charset con el que la BD entrega/recibe texto via mssql_* (FreeTDS).
    // Si su freetds.conf tiene "client charset = UTF-8", cambie a 'UTF-8'.
    'db_charset' => 'ISO-8859-1',

    // Visibilidad para mercadeo (gerente / auxiliar)
    'gestor_filtra_por_organizacion' => true,  // solo ve solicitudes de su ORGANIZACION_VENTA
    'gestor_filtra_por_proceso_rol'  => false, // solo ve tipos cuyo PROCESO_ID = T_ROLES.PROCESO_ID

    // El proveedor solo ve tipos de procesos de su organizacion (o sin organizacion)
    'tipos_filtra_por_organizacion' => true,

    // El proveedor ve todas las solicitudes de su laboratorio (CODIGO_SAP), no solo las suyas
    'proveedor_ve_todo_el_laboratorio' => true,

    // Si es true, en descuentos/bonificados el material debe pertenecer al laboratorio
    // del proveedor (T_MATERIALES.CODIGO_SAP = codigo SAP del proveedor)
    'validar_material_del_laboratorio' => false,

    // Adjuntos
    'adjuntos' => array(
        'url_base'        => 'https://app.pwmultiroma.com',
        'ruta_ftp'        => '/adg/archivos/solicitudes_proveedores',
        'max_mb'          => 10,
        'max_por_envio'   => 10,
        'ext_gestion'     => array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip', 'msg'),
        'ext_plantilla'   => array('xlsx', 'xls'),
    ),

    // Plantillas Excel
    'max_filas_excel' => 3000,

    // Semaforo de antiguedad (horas) para solicitudes abiertas en la bandeja/dashboard
    'sla_horas' => array('alerta' => 24, 'critico' => 72),

    'paginacion' => array('por_pagina' => 25, 'maximo' => 200),
);

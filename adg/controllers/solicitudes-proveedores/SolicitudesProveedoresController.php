<?php
/**
 * Endpoint unico del modulo Solicitudes de Proveedores.
 * Todas las peticiones llegan por POST con el parametro "accion" (ver ControladorHttp::$rutas).
 */
ob_start();
if (session_id() === '') {
    session_start();
}
// Libera el bloqueo de sesion: el modulo solo lee $_SESSION
$sesion = $_SESSION;
session_write_close();

require_once dirname(__FILE__) . '/../../models/funciones.php';
require_once dirname(__FILE__) . '/../../models/SolicitudesProveedores/bootstrap.php';

use SolicitudesProveedores\Infraestructura\Contenedor;
use SolicitudesProveedores\Infraestructura\Http\ControladorHttp;
use SolicitudesProveedores\Infraestructura\Persistencia\ConexionMssql;

$config = require SP_MODULO_DIR . '/config.php';

try {
    $link       = conectar();
    $contenedor = new Contenedor($config, new ConexionMssql($link, $config['db_charset']));
} catch (Exception $e) {
    error_log('[SolicitudesProveedores] ' . $e->getMessage());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'mensaje' => 'No fue posible conectar con la base de datos'));
    exit;
}

$controlador = new ControladorHttp($contenedor);
$controlador->atender($_POST, $sesion, $_SERVER);

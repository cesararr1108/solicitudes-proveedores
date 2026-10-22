<?php
/**
 * Entrada del modulo: redirige a la vista que corresponde al rol de la sesion.
 * Permite enlazar el menu a la carpeta: .../views/solicitudes-proveedores/
 */
if (session_id() === '') {
    session_start();
}
$rol = isset($_SESSION['ses_RolesId']) ? (int) $_SESSION['ses_RolesId'] : 0;

$destinos = array(
    9   => 'sp_proveedor.php',
    118 => 'sp_mercadeo.php',
    6   => 'sp_mercadeo.php',
    1   => 'sp_mercadeo.php',
);
$destino = isset($destinos[$rol]) ? $destinos[$rol] : 'sp_mercadeo.php';

// Conserva parametros como ?id=123
if (!empty($_SERVER['QUERY_STRING'])) {
    $destino .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $destino);
exit;

<?php
/**
 * Entrada unica del modulo (T_MODULOS.ARCHIVO = 'solicitudes-proveedores/index').
 * Muestra la vista que corresponde al rol de la sesion. No redirige: asi funciona
 * igual si el menu navega a esta URL, la incluye con include/require o la carga por AJAX.
 *
 *   9            -> sp_proveedor.php        (proveedor)
 *   1, 118, 6    -> sp_mercadeo.php         (administrador, gerente, auxiliar)
 *   1 + ?vista=tipos -> sp_tipos_solicitud.php
 */
if (session_id() === '' && !headers_sent()) {
    session_start();
}
$spRol   = isset($_SESSION['ses_RolesId']) ? (int) $_SESSION['ses_RolesId'] : 0;
$spVista = 'sp_mercadeo.php';

if ($spRol === 9) {
    $spVista = 'sp_proveedor.php';
} elseif ($spRol === 1 && isset($_GET['vista']) && $_GET['vista'] === 'tipos') {
    $spVista = 'sp_tipos_solicitud.php';
}
require dirname(__FILE__) . '/' . $spVista;

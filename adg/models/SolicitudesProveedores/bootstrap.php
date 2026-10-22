<?php
/**
 * Punto de arranque del modulo (autoload PSR-0 simple, compatible PHP 5.4).
 */
if (!defined('SP_MODULO_DIR')) {
    define('SP_MODULO_DIR', dirname(__FILE__));

    spl_autoload_register(function ($clase) {
        $prefijo = 'SolicitudesProveedores\\';
        if (strpos($clase, $prefijo) !== 0) {
            return;
        }
        $relativo = substr($clase, strlen($prefijo));
        $archivo  = SP_MODULO_DIR . '/' . str_replace('\\', '/', $relativo) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    });
}

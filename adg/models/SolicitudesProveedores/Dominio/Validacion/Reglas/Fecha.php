<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

/** Fecha en formato YYYY-MM-DD. */
class Fecha implements Regla
{
    public function validar($valor, $etiqueta, array $datos)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $etiqueta . ' no es una fecha valida (AAAA-MM-DD)';
        }
        return null;
    }
}

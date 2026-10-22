<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

class Requerido implements Regla
{
    public function validar($valor, $etiqueta, array $datos)
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '') || (is_array($valor) && empty($valor))) {
            return $etiqueta . ' es obligatorio';
        }
        return null;
    }
}

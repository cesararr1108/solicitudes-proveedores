<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

/**
 * Regla ad-hoc sin crear una clase:
 *   new Callback(function ($valor, $datos) { return $valor !== 'X'; }, 'no puede ser X')
 */
class Callback implements Regla
{
    private $fn;
    private $mensaje;

    public function __construct($fn, $mensaje)
    {
        $this->fn      = $fn;
        $this->mensaje = $mensaje;
    }

    public function validar($valor, $etiqueta, array $datos)
    {
        $fn = $this->fn;
        return $fn($valor, $datos) ? null : $etiqueta . ' ' . $this->mensaje;
    }
}

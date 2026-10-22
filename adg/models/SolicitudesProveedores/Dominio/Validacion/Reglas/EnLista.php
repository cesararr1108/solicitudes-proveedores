<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

class EnLista implements Regla
{
    private $permitidos;

    public function __construct(array $permitidos)
    {
        $this->permitidos = $permitidos;
    }

    public function validar($valor, $etiqueta, array $datos)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return in_array($valor, $this->permitidos, true) ? null : $etiqueta . ' no es un valor permitido';
    }
}

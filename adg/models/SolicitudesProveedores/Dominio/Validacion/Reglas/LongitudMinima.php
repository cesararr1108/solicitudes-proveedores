<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

class LongitudMinima implements Regla
{
    private $minimo;

    public function __construct($minimo)
    {
        $this->minimo = (int) $minimo;
    }

    public function validar($valor, $etiqueta, array $datos)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $largo = function_exists('mb_strlen') ? mb_strlen(trim((string) $valor), 'UTF-8') : strlen(trim((string) $valor));
        return $largo < $this->minimo ? $etiqueta . ' debe tener al menos ' . $this->minimo . ' caracteres' : null;
    }
}

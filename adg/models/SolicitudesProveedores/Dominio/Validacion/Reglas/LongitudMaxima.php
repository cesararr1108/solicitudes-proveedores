<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

class LongitudMaxima implements Regla
{
    private $maximo;

    public function __construct($maximo)
    {
        $this->maximo = (int) $maximo;
    }

    public function validar($valor, $etiqueta, array $datos)
    {
        $largo = function_exists('mb_strlen') ? mb_strlen((string) $valor, 'UTF-8') : strlen((string) $valor);
        return $largo > $this->maximo ? $etiqueta . ' admite maximo ' . $this->maximo . ' caracteres' : null;
    }
}

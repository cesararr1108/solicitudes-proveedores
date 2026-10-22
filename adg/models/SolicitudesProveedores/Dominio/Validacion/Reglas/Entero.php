<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

class Entero implements Regla
{
    private $minimo;
    private $maximo;

    public function __construct($minimo = null, $maximo = null)
    {
        $this->minimo = $minimo;
        $this->maximo = $maximo;
    }

    public function validar($valor, $etiqueta, array $datos)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!preg_match('/^-?\d+$/', trim((string) $valor))) {
            return $etiqueta . ' debe ser un numero entero';
        }
        $n = (int) $valor;
        if ($this->minimo !== null && $n < $this->minimo) {
            return $etiqueta . ' debe ser mayor o igual a ' . $this->minimo;
        }
        if ($this->maximo !== null && $n > $this->maximo) {
            return $etiqueta . ' debe ser menor o igual a ' . $this->maximo;
        }
        return null;
    }
}

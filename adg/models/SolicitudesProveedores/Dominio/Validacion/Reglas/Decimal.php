<?php
namespace SolicitudesProveedores\Dominio\Validacion\Reglas;

use SolicitudesProveedores\Dominio\Validacion\Regla;

/** Decimal en rango. $minimoExclusivo permite reglas como "mayor que 0". */
class Decimal implements Regla
{
    private $minimo;
    private $maximo;
    private $minimoExclusivo;

    public function __construct($minimo = null, $maximo = null, $minimoExclusivo = false)
    {
        $this->minimo          = $minimo;
        $this->maximo          = $maximo;
        $this->minimoExclusivo = $minimoExclusivo;
    }

    public function validar($valor, $etiqueta, array $datos)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_numeric($valor)) {
            return $etiqueta . ' debe ser numerico';
        }
        $n = (float) $valor;
        if ($this->minimo !== null) {
            if ($this->minimoExclusivo && $n <= $this->minimo) {
                return $etiqueta . ' debe ser mayor que ' . $this->minimo;
            }
            if (!$this->minimoExclusivo && $n < $this->minimo) {
                return $etiqueta . ' debe ser mayor o igual a ' . $this->minimo;
            }
        }
        if ($this->maximo !== null && $n > $this->maximo) {
            return $etiqueta . ' debe ser menor o igual a ' . $this->maximo;
        }
        return null;
    }
}

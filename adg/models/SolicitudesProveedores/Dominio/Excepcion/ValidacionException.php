<?php
namespace SolicitudesProveedores\Dominio\Excepcion;

/**
 * Errores de validacion. $errores es un arreglo campo => mensaje,
 * o una lista de {fila, campo, mensaje} para el detalle de plantillas.
 */
class ValidacionException extends DominioException
{
    private $errores;

    public function __construct($mensaje, array $errores = array())
    {
        parent::__construct($mensaje, 422);
        $this->errores = $errores;
    }

    public function errores()
    {
        return $this->errores;
    }
}

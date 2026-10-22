<?php
namespace SolicitudesProveedores\Dominio\Excepcion;

/**
 * Excepcion base del modulo. El codigo HTTP viaja en getCode().
 */
class DominioException extends \Exception
{
    public function __construct($mensaje, $codigoHttp = 400)
    {
        parent::__construct($mensaje, $codigoHttp);
    }

    public function errores()
    {
        return array();
    }
}

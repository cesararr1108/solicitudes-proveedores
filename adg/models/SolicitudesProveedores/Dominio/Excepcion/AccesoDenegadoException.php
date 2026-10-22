<?php
namespace SolicitudesProveedores\Dominio\Excepcion;

class AccesoDenegadoException extends DominioException
{
    public function __construct($mensaje = 'No tiene permisos para realizar esta accion')
    {
        parent::__construct($mensaje, 403);
    }
}

<?php
namespace SolicitudesProveedores\Dominio\Excepcion;

class NoEncontradoException extends DominioException
{
    public function __construct($mensaje = 'El registro no existe')
    {
        parent::__construct($mensaje, 404);
    }
}

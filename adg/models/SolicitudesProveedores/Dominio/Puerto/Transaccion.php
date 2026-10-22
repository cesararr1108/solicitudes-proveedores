<?php
namespace SolicitudesProveedores\Dominio\Puerto;

interface Transaccion
{
    /**
     * Ejecuta $fn dentro de una transaccion; confirma si termina bien, revierte si lanza excepcion.
     * @return mixed lo que retorne $fn
     */
    public function ejecutar($fn);
}

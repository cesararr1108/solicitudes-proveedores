<?php
namespace SolicitudesProveedores\Dominio\Puerto;

/**
 * Persistencia del detalle de las plantillas Excel.
 * Una nueva plantilla agrega aqui su par guardarX/obtenerX.
 */
interface DetalleRepositorio
{
    public function guardarDescuentos($solicitudId, array $filas);

    public function obtenerDescuentos($solicitudId);

    public function guardarBonificados($solicitudId, array $filas);

    public function obtenerBonificados($solicitudId);
}

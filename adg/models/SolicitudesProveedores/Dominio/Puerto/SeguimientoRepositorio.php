<?php
namespace SolicitudesProveedores\Dominio\Puerto;

/** Comentarios, adjuntos e historial de una solicitud. */
interface SeguimientoRepositorio
{
    /** @return int id */
    public function agregarComentario($solicitudId, $usuarioId, $texto, $interno);

    public function listarComentarios($solicitudId, $incluirInternos);

    /** @return int id */
    public function agregarAdjunto($solicitudId, $comentarioId, array $adjunto, $usuarioId, $esPlantilla);

    public function listarAdjuntos($solicitudId, $incluirInternos);

    /**
     * @param array $datos estado_anterior, estado_nuevo, asignado_anterior, asignado_nuevo, detalle
     */
    public function registrarEvento($solicitudId, $usuarioId, $evento, array $datos = array());

    public function listarHistorial($solicitudId, $incluirInternos);
}

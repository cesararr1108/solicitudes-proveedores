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

    /** @return string|null fecha (AAAA-MM-DD HH:MM:SS) en que el usuario abrio por ultima vez la solicitud */
    public function ultimaLectura($solicitudId, $usuarioId);

    /** Registra que el usuario acaba de abrir la solicitud (para el indicador de novedades). */
    public function marcarLectura($solicitudId, $usuarioId);
}

<?php
namespace SolicitudesProveedores\Dominio\Puerto;

interface TipoSolicitudRepositorio
{
    /** @param array $filtros solo_activos(bool), organizacion(string) */
    public function listar(array $filtros = array());

    /** @return array|null */
    public function obtener($id);

    /** Inserta si no trae id, actualiza si lo trae. @return int id */
    public function guardar(array $datos, $usuarioId);

    public function cambiarActivo($id, $activo, $usuarioId);

    public function existeNombreEnProceso($nombre, $procesoId, $excluirId = 0);
}

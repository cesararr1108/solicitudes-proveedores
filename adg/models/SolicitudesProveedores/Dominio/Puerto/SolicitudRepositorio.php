<?php
namespace SolicitudesProveedores\Dominio\Puerto;

use SolicitudesProveedores\Dominio\Solicitud;

interface SolicitudRepositorio
{
    /** @return int id generado */
    public function crear(Solicitud $s);

    /** @return Solicitud|null */
    public function obtener($id);

    /** Solicitud con datos de tipo, proveedor, usuarios. @return array|null */
    public function obtenerVista($id);

    public function actualizarAsignacion(Solicitud $s);

    public function actualizarEstado(Solicitud $s);

    /**
     * @param array $filtros estado, asignacion(sin_asignar|mias|todas), proveedor, tipo_id, texto,
     *                       desde, hasta, pagina, por_pagina
     * @param array $alcance restricciones de visibilidad (ver Dominio\Alcance)
     * @return array array('filas' => array, 'total' => int)
     */
    public function buscar(array $filtros, array $alcance, $actorId);

    /** @return array estado => cantidad */
    public function contarPorEstado(array $filtros, array $alcance, $actorId);
}

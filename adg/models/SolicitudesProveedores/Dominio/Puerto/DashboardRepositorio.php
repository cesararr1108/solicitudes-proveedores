<?php
namespace SolicitudesProveedores\Dominio\Puerto;

interface DashboardRepositorio
{
    /**
     * @param array $filtros desde, hasta, proveedor, tipo_id, asignado_a
     * @param array $alcance
     * @param array $sla     alerta, critico (horas)
     */
    public function resumen(array $filtros, array $alcance, array $sla);
}

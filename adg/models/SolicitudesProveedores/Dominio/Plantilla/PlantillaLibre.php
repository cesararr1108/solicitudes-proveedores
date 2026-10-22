<?php
namespace SolicitudesProveedores\Dominio\Plantilla;

use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;

/** Solicitud sin detalle estructurado: solo observacion y adjuntos. */
class PlantillaLibre implements PlantillaDetalle
{
    public function codigo()        { return 'LIBRE'; }
    public function nombre()        { return 'Libre (adjuntos)'; }
    public function requiereExcel() { return false; }
    public function columnas()      { return array(); }

    public function analizar(array $filas, CatalogoRepositorio $catalogo, $codigoSapLaboratorio, $maxFilas)
    {
        return array('filas' => array(), 'errores' => array(), 'advertencias' => array());
    }

    public function guardar(DetalleRepositorio $repo, $solicitudId, array $filas)
    {
    }

    public function obtener(DetalleRepositorio $repo, $solicitudId)
    {
        return array();
    }
}

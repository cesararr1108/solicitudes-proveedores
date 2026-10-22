<?php
namespace SolicitudesProveedores\Dominio\Plantilla;

use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;

/**
 * Estrategia de detalle de un tipo de solicitud (T_MER_TIPOS_SOLICITUD_PROV.PLANTILLA).
 * Para crear una nueva plantilla:
 *   1. Clase que implemente esta interfaz (o extienda PlantillaExcelBase).
 *   2. Registrarla en RegistroPlantillas.
 *   3. Tabla + metodos en DetalleRepositorio, y el valor en el CHECK de la tabla de tipos.
 */
interface PlantillaDetalle
{
    public function codigo();

    public function nombre();

    public function requiereExcel();

    /** @return array lista de array(clave, titulo, tipo, ejemplo) */
    public function columnas();

    /**
     * Normaliza y valida las filas recibidas del Excel.
     * @return array array('filas' => array, 'errores' => array, 'advertencias' => array)
     */
    public function analizar(array $filas, CatalogoRepositorio $catalogo, $codigoSapLaboratorio, $maxFilas);

    public function guardar(DetalleRepositorio $repo, $solicitudId, array $filas);

    public function obtener(DetalleRepositorio $repo, $solicitudId);
}

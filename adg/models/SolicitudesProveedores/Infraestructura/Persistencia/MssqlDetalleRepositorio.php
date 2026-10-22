<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;

/**
 * Las descripciones NO se guardan: siempre se leen de T_MATERIALES.
 * Inserta por lotes con INSERT ... SELECT ... UNION ALL (compatible con SQL Server 2005+).
 */
class MssqlDetalleRepositorio implements DetalleRepositorio
{
    const LOTE = 200;

    private $db;

    public function __construct(ConexionMssql $db)
    {
        $this->db = $db;
    }

    public function guardarDescuentos($solicitudId, array $filas)
    {
        $db = $this->db;
        $this->insertarPorLotes(
            'INSERT INTO T_MER_SOLICITUDES_PROV_DESCUENTOS (SOLICITUD_ID, FILA, CODIGO_MATERIAL, DESCUENTO)',
            $filas,
            function ($f) use ($db, $solicitudId) {
                return 'SELECT ' . (int) $solicitudId . ', ' . (int) $f['fila'] . ', '
                    . $db->escapar((string) $f['codigo_material']) . ', ' . $db->escapar((float) $f['descuento']);
            }
        );
    }

    public function obtenerDescuentos($solicitudId)
    {
        return $this->db->consultar(
            'SELECT D.FILA AS fila, D.CODIGO_MATERIAL AS codigo_material, M.DESCRIPCION AS descripcion,
                    CAST(D.DESCUENTO AS FLOAT) AS descuento
               FROM T_MER_SOLICITUDES_PROV_DESCUENTOS D
               LEFT JOIN T_MATERIALES M ON M.CODIGO_MATERIAL = D.CODIGO_MATERIAL
              WHERE D.SOLICITUD_ID = :id ORDER BY D.FILA',
            array('id' => (int) $solicitudId)
        );
    }

    public function guardarBonificados($solicitudId, array $filas)
    {
        $db = $this->db;
        $this->insertarPorLotes(
            'INSERT INTO T_MER_SOLICITUDES_PROV_BONIFICADOS
                (SOLICITUD_ID, FILA, CODIGO_MATERIAL_REGULAR, CANTIDAD_REGULAR, CODIGO_MATERIAL_BONIFICADO, CANTIDAD_BONIFICADA)',
            $filas,
            function ($f) use ($db, $solicitudId) {
                return 'SELECT ' . (int) $solicitudId . ', ' . (int) $f['fila'] . ', '
                    . $db->escapar((string) $f['codigo_regular']) . ', ' . (int) $f['cantidad_regular'] . ', '
                    . $db->escapar((string) $f['codigo_bonificado']) . ', ' . (int) $f['cantidad_bonificada'];
            }
        );
    }

    public function obtenerBonificados($solicitudId)
    {
        return $this->db->consultar(
            'SELECT B.FILA AS fila,
                    B.CODIGO_MATERIAL_REGULAR AS codigo_regular, MR.DESCRIPCION AS descripcion_regular,
                    B.CANTIDAD_REGULAR AS cantidad_regular,
                    B.CODIGO_MATERIAL_BONIFICADO AS codigo_bonificado, MB.DESCRIPCION AS descripcion_bonificado,
                    B.CANTIDAD_BONIFICADA AS cantidad_bonificada
               FROM T_MER_SOLICITUDES_PROV_BONIFICADOS B
               LEFT JOIN T_MATERIALES MR ON MR.CODIGO_MATERIAL = B.CODIGO_MATERIAL_REGULAR
               LEFT JOIN T_MATERIALES MB ON MB.CODIGO_MATERIAL = B.CODIGO_MATERIAL_BONIFICADO
              WHERE B.SOLICITUD_ID = :id ORDER BY B.FILA',
            array('id' => (int) $solicitudId)
        );
    }

    private function insertarPorLotes($insert, array $filas, $fnSelect)
    {
        foreach (array_chunk($filas, self::LOTE) as $lote) {
            $selects = array();
            foreach ($lote as $f) {
                $selects[] = call_user_func($fnSelect, $f);
            }
            $this->db->ejecutar($insert . "\n" . implode("\nUNION ALL ", $selects));
        }
    }
}

<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;

class MssqlCatalogoRepositorio implements CatalogoRepositorio
{
    const LARGO_CODIGO_SAP = 18;

    private $db;

    public function __construct(ConexionMssql $db)
    {
        $this->db = $db;
    }

    /**
     * Busca cada codigo tal cual y, si es numerico, tambien con ceros a la izquierda
     * (Excel suele borrar los ceros de los codigos SAP).
     */
    public function buscarMateriales(array $codigos, $codigoSapLaboratorio = null)
    {
        $candidatos = array();
        foreach ($codigos as $c) {
            $candidatos[$c] = $c;
            if (ctype_digit($c) && strlen($c) < self::LARGO_CODIGO_SAP) {
                $relleno = str_pad($c, self::LARGO_CODIGO_SAP, '0', STR_PAD_LEFT);
                $candidatos[$relleno] = $relleno;
            }
        }

        $encontrados = array();
        foreach (array_chunk(array_values($candidatos), 500) as $lote) {
            $params = array('codigos' => $lote);
            $sql = 'SELECT CODIGO_MATERIAL, DESCRIPCION, ISNULL(ANULADA, 0) AS ANULADA, ISNULL(BLOQUEO_VENTA, 0) AS BLOQUEO_VENTA
                      FROM T_MATERIALES WHERE CODIGO_MATERIAL IN (:codigos)';
            if ($codigoSapLaboratorio !== null) {
                $sql .= ' AND CASE WHEN ISNUMERIC(CODIGO_SAP) = 1 THEN CAST(CODIGO_SAP AS BIGINT) END = :lab';
                $params['lab'] = (int) $codigoSapLaboratorio;
            }
            foreach ($this->db->consultar($sql, $params) as $m) {
                $encontrados[strtoupper(rtrim($m['CODIGO_MATERIAL']))] = $m;
            }
        }

        $mapa = array();
        foreach ($codigos as $c) {
            $relleno = ctype_digit($c) ? str_pad($c, self::LARGO_CODIGO_SAP, '0', STR_PAD_LEFT) : $c;
            if (isset($encontrados[$c])) {
                $mapa[$c] = $encontrados[$c];
            } elseif (isset($encontrados[$relleno])) {
                $mapa[$c] = $encontrados[$relleno];
            }
        }
        return $mapa;
    }

    public function listarProcesos()
    {
        return $this->db->consultar('SELECT ID, PROCESO, COD, ORGANIZACION FROM T_CAL_PROCESOS ORDER BY PROCESO');
    }

    public function obtenerProceso($id)
    {
        return $this->db->fila('SELECT ID, PROCESO, COD, ORGANIZACION FROM T_CAL_PROCESOS WHERE ID = :id', array('id' => (int) $id));
    }

    public function listarProveedoresConSolicitudes(array $alcance)
    {
        $params = array();
        $where  = SqlComun::condiciones(array(), $alcance, 0, $params, $this->db);
        return $this->db->consultar(
            'SELECT S.CODIGO_SAP_PROVEEDOR AS CODIGO_SAP, MAX(TE.NOMBRES) AS NOMBRE, COUNT(*) AS TOTAL
             ' . SqlComun::FROM_SOLICITUDES . ' WHERE ' . $where . '
             GROUP BY S.CODIGO_SAP_PROVEEDOR ORDER BY MAX(TE.NOMBRES)',
            $params
        );
    }

    public function nombreProveedor($codigoSap)
    {
        return $this->db->valor('SELECT TOP 1 NOMBRES FROM T_TERCEROS WHERE CODIGO_SAP = :c', array('c' => (int) $codigoSap));
    }

    public function listarGestores(array $rolesIds, $organizacion = '')
    {
        $params = array('roles' => array_map('intval', $rolesIds));
        $sql = "SELECT U.ID, U.NOMBRES + ' ' + U.APELLIDOS AS NOMBRE, U.EMAIL, U.ROLES_ID, R.TITULO AS ROL
                  FROM T_USUARIOS U
                  LEFT JOIN T_ROLES R ON R.ID = U.ROLES_ID
                 WHERE U.ESTADO = 'A' AND U.ROLES_ID IN (:roles)";
        if ($organizacion !== '') {
            $sql .= ' AND U.ORGANIZACION_VENTA = :org';
            $params['org'] = $organizacion;
        }
        return $this->db->consultar($sql . ' ORDER BY U.NOMBRES, U.APELLIDOS', $params);
    }

    public function obtenerUsuario($id)
    {
        return $this->db->fila(
            "SELECT ID, NOMBRES + ' ' + APELLIDOS AS NOMBRE, EMAIL, ROLES_ID, ESTADO, ORGANIZACION_VENTA
               FROM T_USUARIOS WHERE ID = :id",
            array('id' => (int) $id)
        );
    }

    public function procesoDeRol($rolId)
    {
        return (int) $this->db->valor('SELECT PROCESO_ID FROM T_ROLES WHERE ID = :id', array('id' => (int) $rolId));
    }

    public function listarEventosActivos($organizacion)
    {
        return $this->db->consultar(
            "SELECT ID, NOMBRE, " . SqlComun::fecha('FECHA_INICIO') . " AS FECHA_INICIO, " . SqlComun::fecha('FECHA_FIN') . " AS FECHA_FIN
               FROM T_EVENTOS_CONVOCATORIA
              WHERE ESTADO = 'A' AND ORGANIZACION_VENTAS = :org
              ORDER BY FECHA_INICIO DESC, NOMBRE",
            array('org' => (string) $organizacion)
        );
    }

    public function obtenerEventoActivo($id, $organizacion)
    {
        return $this->db->fila(
            "SELECT ID, NOMBRE FROM T_EVENTOS_CONVOCATORIA
              WHERE ID = :id AND ESTADO = 'A' AND ORGANIZACION_VENTAS = :org",
            array('id' => (int) $id, 'org' => (string) $organizacion)
        );
    }
}

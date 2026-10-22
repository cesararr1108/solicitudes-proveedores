<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\Puerto\DashboardRepositorio;

/** Modelo de lectura del dashboard de mercadeo. */
class MssqlDashboardRepositorio implements DashboardRepositorio
{
    private $db;

    public function __construct(ConexionMssql $db)
    {
        $this->db = $db;
    }

    public function resumen(array $filtros, array $alcance, array $sla)
    {
        $params = array('sla_critico' => (int) $sla['critico'] * 60, 'sla_alerta' => (int) $sla['alerta'] * 60);
        $where  = SqlComun::condiciones($filtros, $alcance, 0, $params, $this->db);
        $from   = SqlComun::FROM_SOLICITUDES . ' LEFT JOIN T_USUARIOS A ON A.ID = S.ASIGNADO_A WHERE ' . $where;
        $abiertaMin = 'DATEDIFF(MINUTE, S.FECHA_CREACION, GETDATE())';

        $kpi = $this->db->fila(
            "SELECT COUNT(*) AS TOTAL,
                    SUM(CASE WHEN S.ESTADO = 'PENDIENTE' THEN 1 ELSE 0 END) AS PENDIENTE,
                    SUM(CASE WHEN S.ESTADO = 'EN_CURSO' THEN 1 ELSE 0 END) AS EN_CURSO,
                    SUM(CASE WHEN S.ESTADO = 'FINALIZADO' THEN 1 ELSE 0 END) AS FINALIZADO,
                    SUM(CASE WHEN S.ESTADO <> 'FINALIZADO' AND S.ASIGNADO_A IS NULL THEN 1 ELSE 0 END) AS SIN_ASIGNAR,
                    SUM(CASE WHEN S.ESTADO <> 'FINALIZADO' AND $abiertaMin >= :sla_critico THEN 1 ELSE 0 END) AS CRITICAS,
                    SUM(CASE WHEN S.ESTADO <> 'FINALIZADO' AND $abiertaMin >= :sla_alerta AND $abiertaMin < :sla_critico THEN 1 ELSE 0 END) AS EN_ALERTA,
                    AVG(CASE WHEN S.ESTADO = 'FINALIZADO' THEN CAST(DATEDIFF(MINUTE, S.FECHA_CREACION, S.FECHA_FINALIZACION) AS FLOAT) END) AS MIN_PROM_RESOLUCION,
                    AVG(CASE WHEN S.FECHA_INICIO_GESTION IS NOT NULL THEN CAST(DATEDIFF(MINUTE, S.FECHA_CREACION, S.FECHA_INICIO_GESTION) AS FLOAT) END) AS MIN_PROM_ATENCION
             $from",
            $params
        );

        $listaAbiertas = "SELECT TOP 100 S.ID, S.ASUNTO, S.ESTADO, T.NOMBRE AS TIPO, TE.NOMBRES AS PROVEEDOR,
                    S.ASIGNADO_A, A.NOMBRES + ' ' + A.APELLIDOS AS ASIGNADO,
                    " . SqlComun::fecha('S.FECHA_CREACION') . " AS FECHA_CREACION,
                    $abiertaMin AS MINUTOS,
                    CASE WHEN S.FECHA_ASIGNACION IS NULL THEN NULL ELSE DATEDIFF(MINUTE, S.FECHA_ASIGNACION, GETDATE()) END AS MINUTOS_ASIGNADA
             $from AND S.ESTADO <> 'FINALIZADO'";

        $sinAsignar = $this->db->consultar($listaAbiertas . ' AND S.ASIGNADO_A IS NULL ORDER BY S.FECHA_CREACION', $params);
        $enProceso  = $this->db->consultar($listaAbiertas . ' AND S.ASIGNADO_A IS NOT NULL ORDER BY S.FECHA_CREACION', $params);

        $porGestor = $this->db->consultar(
            "SELECT S.ASIGNADO_A AS ID, MAX(A.NOMBRES + ' ' + A.APELLIDOS) AS NOMBRE,
                    SUM(CASE WHEN S.ESTADO = 'PENDIENTE' THEN 1 ELSE 0 END) AS PENDIENTE,
                    SUM(CASE WHEN S.ESTADO = 'EN_CURSO' THEN 1 ELSE 0 END) AS EN_CURSO,
                    SUM(CASE WHEN S.ESTADO = 'FINALIZADO' THEN 1 ELSE 0 END) AS FINALIZADO,
                    AVG(CASE WHEN S.ESTADO = 'FINALIZADO' THEN CAST(DATEDIFF(MINUTE, S.FECHA_CREACION, S.FECHA_FINALIZACION) AS FLOAT) END) AS MIN_PROM_RESOLUCION
             $from AND S.ASIGNADO_A IS NOT NULL
             GROUP BY S.ASIGNADO_A ORDER BY MAX(A.NOMBRES)",
            $params
        );

        $porTipo = $this->db->consultar(
            "SELECT S.TIPO_ID AS ID, MAX(T.NOMBRE) AS NOMBRE, COUNT(*) AS TOTAL,
                    SUM(CASE WHEN S.ESTADO <> 'FINALIZADO' THEN 1 ELSE 0 END) AS ABIERTAS
             $from GROUP BY S.TIPO_ID ORDER BY COUNT(*) DESC",
            $params
        );

        $porProveedor = $this->db->consultar(
            "SELECT TOP 10 S.CODIGO_SAP_PROVEEDOR AS CODIGO_SAP, MAX(TE.NOMBRES) AS NOMBRE, COUNT(*) AS TOTAL,
                    SUM(CASE WHEN S.ESTADO <> 'FINALIZADO' THEN 1 ELSE 0 END) AS ABIERTAS
             $from GROUP BY S.CODIGO_SAP_PROVEEDOR ORDER BY COUNT(*) DESC",
            $params
        );

        $kpi = $kpi ? $kpi : array();
        foreach ($kpi as $k => $v) {
            $kpi[$k] = $v === null ? 0 : (float) $v;
        }

        return array(
            'kpi'           => $kpi,
            'sin_asignar'   => $sinAsignar,
            'en_proceso'    => $enProceso,
            'por_gestor'    => $porGestor,
            'por_tipo'      => $porTipo,
            'por_proveedor' => $porProveedor,
        );
    }
}

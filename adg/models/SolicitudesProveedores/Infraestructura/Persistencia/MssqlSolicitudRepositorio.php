<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\EstadoSolicitud;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;
use SolicitudesProveedores\Dominio\Solicitud;

class MssqlSolicitudRepositorio implements SolicitudRepositorio
{
    private $db;

    public function __construct(ConexionMssql $db)
    {
        $this->db = $db;
    }

    public function crear(Solicitud $s)
    {
        return $this->db->insertar(
            'INSERT INTO T_MER_SOLICITUDES_PROVEEDORES
                (TIPO_ID, ASUNTO, OBSERVACION, ESTADO, CODIGO_SAP_PROVEEDOR, USUARIO_ID, ORGANIZACION_VENTA, OFICINA_VENTAS)
             VALUES (:tipo, :asunto, :obs, :estado, :sap, :usuario, :org, :ofi)',
            array(
                'tipo'    => $s->tipoId(),
                'asunto'  => $s->asunto(),
                'obs'     => $s->observacion(),
                'estado'  => $s->estado(),
                'sap'     => $s->codigoSapProveedor(),
                'usuario' => $s->usuarioId(),
                'org'     => $s->organizacion() === '' ? null : $s->organizacion(),
                'ofi'     => $s->oficina() === '' ? null : $s->oficina(),
            )
        );
    }

    public function obtener($id)
    {
        $f = $this->db->fila(
            'SELECT S.ID, S.TIPO_ID, S.ASUNTO, S.ESTADO, S.CODIGO_SAP_PROVEEDOR, S.USUARIO_ID,
                    S.ORGANIZACION_VENTA, S.OFICINA_VENTAS, S.ASIGNADO_A, T.PROCESO_ID
               FROM T_MER_SOLICITUDES_PROVEEDORES S
              INNER JOIN T_MER_TIPOS_SOLICITUD_PROV T ON T.ID = S.TIPO_ID
              WHERE S.ID = :id',
            array('id' => (int) $id)
        );
        return $f === null ? null : Solicitud::desdeFila($f);
    }

    public function obtenerVista($id)
    {
        return $this->db->fila(
            "SELECT S.ID, S.TIPO_ID, T.NOMBRE AS TIPO, T.PLANTILLA, P.PROCESO, S.ASUNTO,
                    CAST(S.OBSERVACION AS TEXT) AS OBSERVACION, S.ESTADO,
                    S.CODIGO_SAP_PROVEEDOR, TE.NOMBRES AS PROVEEDOR,
                    S.USUARIO_ID, U.NOMBRES + ' ' + U.APELLIDOS AS USUARIO, U.EMAIL AS USUARIO_EMAIL,
                    S.ORGANIZACION_VENTA, S.OFICINA_VENTAS, OV.DESCRIPCION AS OFICINA,
                    S.ASIGNADO_A, A.NOMBRES + ' ' + A.APELLIDOS AS ASIGNADO,
                    " . SqlComun::fecha('S.FECHA_CREACION') . " AS FECHA_CREACION,
                    " . SqlComun::fecha('S.FECHA_ASIGNACION') . " AS FECHA_ASIGNACION,
                    " . SqlComun::fecha('S.FECHA_INICIO_GESTION') . " AS FECHA_INICIO_GESTION,
                    " . SqlComun::fecha('S.FECHA_FINALIZACION') . " AS FECHA_FINALIZACION,
                    DATEDIFF(MINUTE, S.FECHA_CREACION, ISNULL(S.FECHA_FINALIZACION, GETDATE())) AS MINUTOS
             " . SqlComun::FROM_SOLICITUDES . "
             INNER JOIN T_CAL_PROCESOS P ON P.ID = T.PROCESO_ID
             INNER JOIN T_USUARIOS U ON U.ID = S.USUARIO_ID
             LEFT JOIN T_USUARIOS A ON A.ID = S.ASIGNADO_A
             OUTER APPLY (SELECT TOP 1 O.DESCRIPCION FROM T_OFICINAS_VENTAS O
                           WHERE O.ORGANIZACION_VENTAS = S.ORGANIZACION_VENTA AND O.OFICINA_VENTAS = S.OFICINA_VENTAS) OV
             WHERE S.ID = :id",
            array('id' => (int) $id)
        );
    }

    public function actualizarAsignacion(Solicitud $s)
    {
        $this->db->ejecutar(
            'UPDATE T_MER_SOLICITUDES_PROVEEDORES
                SET ASIGNADO_A = :asignado, FECHA_ASIGNACION = GETDATE(), FECHA_MODIFICACION = GETDATE()
              WHERE ID = :id',
            array('asignado' => $s->asignadoA(), 'id' => $s->id())
        );
    }

    public function actualizarEstado(Solicitud $s)
    {
        $extra = '';
        if ($s->estado() === EstadoSolicitud::EN_CURSO) {
            $extra = ', FECHA_INICIO_GESTION = ISNULL(FECHA_INICIO_GESTION, GETDATE())';
        } elseif ($s->estado() === EstadoSolicitud::FINALIZADO) {
            $extra = ', FECHA_FINALIZACION = GETDATE()';
        }
        $this->db->ejecutar(
            'UPDATE T_MER_SOLICITUDES_PROVEEDORES SET ESTADO = :estado, FECHA_MODIFICACION = GETDATE()' . $extra . ' WHERE ID = :id',
            array('estado' => $s->estado(), 'id' => $s->id())
        );
    }

    public function buscar(array $filtros, array $alcance, $actorId)
    {
        $params = array();
        $where  = SqlComun::condiciones($filtros, $alcance, $actorId, $params, $this->db);
        $orden  = $filtros['orden'] === 'antiguas' ? 'S.FECHA_CREACION ASC' : 'S.FECHA_CREACION DESC';
        $desde  = ($filtros['pagina'] - 1) * $filtros['por_pagina'] + 1;
        $hasta  = $desde + $filtros['por_pagina'] - 1;

        $total = (int) $this->db->valor('SELECT COUNT(*) ' . SqlComun::FROM_SOLICITUDES . ' WHERE ' . $where, $params);

        $filas = $this->db->consultar(
            "SELECT * FROM (
                SELECT ROW_NUMBER() OVER (ORDER BY $orden) AS RN,
                       S.ID, S.TIPO_ID, T.NOMBRE AS TIPO, T.PLANTILLA, S.ASUNTO, S.ESTADO,
                       S.CODIGO_SAP_PROVEEDOR, TE.NOMBRES AS PROVEEDOR,
                       U.NOMBRES + ' ' + U.APELLIDOS AS USUARIO,
                       S.ASIGNADO_A, A.NOMBRES + ' ' + A.APELLIDOS AS ASIGNADO,
                       " . SqlComun::fecha('S.FECHA_CREACION') . " AS FECHA_CREACION,
                       " . SqlComun::fecha('S.FECHA_FINALIZACION') . " AS FECHA_FINALIZACION,
                       DATEDIFF(MINUTE, S.FECHA_CREACION, ISNULL(S.FECHA_FINALIZACION, GETDATE())) AS MINUTOS,
                       (SELECT " . SqlComun::fecha('MAX(H.FECHA_CREACION)') . " FROM T_MER_SOLICITUDES_PROV_HISTORIAL H
                         WHERE H.SOLICITUD_ID = S.ID) AS ULTIMA_ACTIVIDAD
                " . SqlComun::FROM_SOLICITUDES . "
                INNER JOIN T_USUARIOS U ON U.ID = S.USUARIO_ID
                LEFT JOIN T_USUARIOS A ON A.ID = S.ASIGNADO_A
                WHERE $where
             ) X WHERE X.RN BETWEEN :rn_desde AND :rn_hasta ORDER BY X.RN",
            array_merge($params, array('rn_desde' => (int) $desde, 'rn_hasta' => (int) $hasta))
        );
        return array('filas' => $filas, 'total' => $total);
    }

    public function contarPorEstado(array $filtros, array $alcance, $actorId)
    {
        $conteos = array();
        foreach (EstadoSolicitud::todos() as $e) {
            $conteos[$e] = 0;
        }

        $params = array();
        $where  = SqlComun::condiciones($filtros, $alcance, $actorId, $params, $this->db);
        foreach ($this->db->consultar('SELECT S.ESTADO, COUNT(*) AS N ' . SqlComun::FROM_SOLICITUDES . ' WHERE ' . $where . ' GROUP BY S.ESTADO', $params) as $f) {
            $conteos[$f['ESTADO']] = (int) $f['N'];
        }

        // Contadores de bandeja (independientes del filtro de asignacion)
        $filtros['asignacion'] = 'todas';
        $params = array('yo' => (int) $actorId);
        $where  = SqlComun::condiciones($filtros, $alcance, $actorId, $params, $this->db);
        $f = $this->db->fila(
            "SELECT SUM(CASE WHEN S.ASIGNADO_A IS NULL AND S.ESTADO <> 'FINALIZADO' THEN 1 ELSE 0 END) AS SIN_ASIGNAR,
                    SUM(CASE WHEN S.ASIGNADO_A = :yo AND S.ESTADO <> 'FINALIZADO' THEN 1 ELSE 0 END) AS MIAS
             " . SqlComun::FROM_SOLICITUDES . ' WHERE ' . $where,
            $params
        );
        $conteos['SIN_ASIGNAR'] = $f ? (int) $f['SIN_ASIGNAR'] : 0;
        $conteos['MIAS']        = $f ? (int) $f['MIAS'] : 0;
        return $conteos;
    }
}

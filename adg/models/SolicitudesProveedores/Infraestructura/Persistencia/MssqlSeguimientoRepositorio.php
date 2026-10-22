<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\Puerto\SeguimientoRepositorio;

class MssqlSeguimientoRepositorio implements SeguimientoRepositorio
{
    private $db;
    private $rolProveedor;

    public function __construct(ConexionMssql $db, $rolProveedor)
    {
        $this->db           = $db;
        $this->rolProveedor = (int) $rolProveedor;
    }

    public function agregarComentario($solicitudId, $usuarioId, $texto, $interno)
    {
        return $this->db->insertar(
            'INSERT INTO T_MER_SOLICITUDES_PROV_COMENTARIOS (SOLICITUD_ID, USUARIO_ID, COMENTARIO, INTERNO)
             VALUES (:sol, :usuario, :texto, :interno)',
            array('sol' => (int) $solicitudId, 'usuario' => (int) $usuarioId, 'texto' => $texto === '' ? null : $texto, 'interno' => (bool) $interno)
        );
    }

    public function listarComentarios($solicitudId, $incluirInternos)
    {
        return $this->db->consultar(
            "SELECT C.ID, CAST(C.COMENTARIO AS TEXT) AS COMENTARIO, C.INTERNO, C.USUARIO_ID,
                    U.NOMBRES + ' ' + U.APELLIDOS AS USUARIO,
                    CASE WHEN U.ROLES_ID = :rol_prov THEN 1 ELSE 0 END AS ES_PROVEEDOR,
                    " . SqlComun::fecha('C.FECHA_CREACION') . " AS FECHA
               FROM T_MER_SOLICITUDES_PROV_COMENTARIOS C
              INNER JOIN T_USUARIOS U ON U.ID = C.USUARIO_ID
              WHERE C.SOLICITUD_ID = :id" . ($incluirInternos ? '' : ' AND C.INTERNO = 0') . '
              ORDER BY C.FECHA_CREACION, C.ID',
            array('id' => (int) $solicitudId, 'rol_prov' => $this->rolProveedor)
        );
    }

    public function agregarAdjunto($solicitudId, $comentarioId, array $a, $usuarioId, $esPlantilla)
    {
        return $this->db->insertar(
            'INSERT INTO T_MER_SOLICITUDES_PROV_ADJUNTOS
                (SOLICITUD_ID, COMENTARIO_ID, NOMBRE_ORIGINAL, RUTA, URL, EXTENSION, TAMANO_KB, ES_PLANTILLA, USUARIO_ID)
             VALUES (:sol, :com, :nombre, :ruta, :url, :ext, :kb, :plantilla, :usuario)',
            array(
                'sol'       => (int) $solicitudId,
                'com'       => $comentarioId ? (int) $comentarioId : null,
                'nombre'    => $a['nombre'],
                'ruta'      => $a['ruta'],
                'url'       => $a['url'],
                'ext'       => $a['extension'],
                'kb'        => (int) $a['tamano_kb'],
                'plantilla' => (bool) $esPlantilla,
                'usuario'   => (int) $usuarioId,
            )
        );
    }

    public function listarAdjuntos($solicitudId, $incluirInternos)
    {
        return $this->db->consultar(
            "SELECT A.ID, A.COMENTARIO_ID, A.NOMBRE_ORIGINAL, A.URL, A.EXTENSION, A.TAMANO_KB, A.ES_PLANTILLA,
                    U.NOMBRES + ' ' + U.APELLIDOS AS USUARIO,
                    CASE WHEN U.ROLES_ID = :rol_prov THEN 1 ELSE 0 END AS ES_PROVEEDOR,
                    " . SqlComun::fecha('A.FECHA_CREACION') . " AS FECHA
               FROM T_MER_SOLICITUDES_PROV_ADJUNTOS A
              INNER JOIN T_USUARIOS U ON U.ID = A.USUARIO_ID
               LEFT JOIN T_MER_SOLICITUDES_PROV_COMENTARIOS C ON C.ID = A.COMENTARIO_ID
              WHERE A.SOLICITUD_ID = :id" . ($incluirInternos ? '' : ' AND (C.ID IS NULL OR C.INTERNO = 0)') . '
              ORDER BY A.FECHA_CREACION, A.ID',
            array('id' => (int) $solicitudId, 'rol_prov' => $this->rolProveedor)
        );
    }

    public function registrarEvento($solicitudId, $usuarioId, $evento, array $d = array())
    {
        $this->db->ejecutar(
            'INSERT INTO T_MER_SOLICITUDES_PROV_HISTORIAL
                (SOLICITUD_ID, USUARIO_ID, EVENTO, ESTADO_ANTERIOR, ESTADO_NUEVO, ASIGNADO_ANTERIOR, ASIGNADO_NUEVO, DETALLE)
             VALUES (:sol, :usuario, :evento, :ea, :en, :aa, :an, :detalle)',
            array(
                'sol'     => (int) $solicitudId,
                'usuario' => (int) $usuarioId,
                'evento'  => $evento,
                'ea'      => isset($d['estado_anterior']) ? $d['estado_anterior'] : null,
                'en'      => isset($d['estado_nuevo']) ? $d['estado_nuevo'] : null,
                'aa'      => isset($d['asignado_anterior']) ? (int) $d['asignado_anterior'] : null,
                'an'      => isset($d['asignado_nuevo']) ? (int) $d['asignado_nuevo'] : null,
                'detalle' => isset($d['detalle']) ? substr($d['detalle'], 0, 500) : null,
            )
        );
    }

    public function listarHistorial($solicitudId, $incluirInternos)
    {
        return $this->db->consultar(
            "SELECT H.ID, H.EVENTO, H.ESTADO_ANTERIOR, H.ESTADO_NUEVO, H.DETALLE,
                    U.NOMBRES + ' ' + U.APELLIDOS AS USUARIO,
                    AA.NOMBRES + ' ' + AA.APELLIDOS AS ASIGNADO_ANTERIOR,
                    AN.NOMBRES + ' ' + AN.APELLIDOS AS ASIGNADO_NUEVO,
                    " . SqlComun::fecha('H.FECHA_CREACION') . " AS FECHA
               FROM T_MER_SOLICITUDES_PROV_HISTORIAL H
              INNER JOIN T_USUARIOS U ON U.ID = H.USUARIO_ID
               LEFT JOIN T_USUARIOS AA ON AA.ID = H.ASIGNADO_ANTERIOR
               LEFT JOIN T_USUARIOS AN ON AN.ID = H.ASIGNADO_NUEVO
              WHERE H.SOLICITUD_ID = :id" . ($incluirInternos ? '' : " AND H.EVENTO <> 'COMENTARIO_INTERNO'") . '
              ORDER BY H.FECHA_CREACION, H.ID',
            array('id' => (int) $solicitudId)
        );
    }
}

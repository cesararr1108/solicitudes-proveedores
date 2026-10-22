<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\Puerto\TipoSolicitudRepositorio;

class MssqlTipoSolicitudRepositorio implements TipoSolicitudRepositorio
{
    private $db;

    public function __construct(ConexionMssql $db)
    {
        $this->db = $db;
    }

    private function select()
    {
        return "SELECT T.ID, T.NOMBRE, T.DESCRIPCION, T.PROCESO_ID, P.PROCESO, P.ORGANIZACION,
                       T.PLANTILLA, T.REQUIERE_ADJUNTO, T.EXTENSIONES, T.ACTIVO, T.ORDEN,
                       " . SqlComun::fecha('T.FECHA_CREACION') . " AS FECHA_CREACION,
                       " . SqlComun::fecha('T.FECHA_MODIFICACION') . " AS FECHA_MODIFICACION,
                       (SELECT COUNT(*) FROM T_MER_SOLICITUDES_PROVEEDORES S WHERE S.TIPO_ID = T.ID) AS TOTAL_SOLICITUDES
                FROM T_MER_TIPOS_SOLICITUD_PROV T
                INNER JOIN T_CAL_PROCESOS P ON P.ID = T.PROCESO_ID";
    }

    public function listar(array $filtros = array())
    {
        $w = array('1 = 1');
        $p = array();
        if (!empty($filtros['solo_activos'])) {
            $w[] = 'T.ACTIVO = 1';
        }
        if (isset($filtros['organizacion'])) {
            $w[] = "(ISNULL(LTRIM(RTRIM(P.ORGANIZACION)), '') = '' OR P.ORGANIZACION = :org)";
            $p['org'] = (string) $filtros['organizacion'];
        }
        return $this->db->consultar($this->select() . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY T.ORDEN, T.NOMBRE', $p);
    }

    public function obtener($id)
    {
        return $this->db->fila($this->select() . ' WHERE T.ID = :id', array('id' => (int) $id));
    }

    public function guardar(array $d, $usuarioId)
    {
        $p = array(
            'nombre'      => $d['nombre'],
            'descripcion' => $d['descripcion'] === '' ? null : $d['descripcion'],
            'proceso'     => (int) $d['proceso_id'],
            'plantilla'   => $d['plantilla'],
            'requiere'    => (bool) $d['requiere_adjunto'],
            'ext'         => $d['extensiones'] === '' ? null : $d['extensiones'],
            'activo'      => (bool) $d['activo'],
            'orden'       => (int) $d['orden'],
            'usuario'     => (int) $usuarioId,
        );
        if (!empty($d['id'])) {
            $p['id'] = (int) $d['id'];
            $this->db->ejecutar(
                'UPDATE T_MER_TIPOS_SOLICITUD_PROV
                    SET NOMBRE = :nombre, DESCRIPCION = :descripcion, PROCESO_ID = :proceso, PLANTILLA = :plantilla,
                        REQUIERE_ADJUNTO = :requiere, EXTENSIONES = :ext, ACTIVO = :activo, ORDEN = :orden,
                        USUARIO_MODIFICA = :usuario, FECHA_MODIFICACION = GETDATE()
                  WHERE ID = :id',
                $p
            );
            return (int) $d['id'];
        }
        return $this->db->insertar(
            'INSERT INTO T_MER_TIPOS_SOLICITUD_PROV
                (NOMBRE, DESCRIPCION, PROCESO_ID, PLANTILLA, REQUIERE_ADJUNTO, EXTENSIONES, ACTIVO, ORDEN, USUARIO_CREA)
             VALUES (:nombre, :descripcion, :proceso, :plantilla, :requiere, :ext, :activo, :orden, :usuario)',
            $p
        );
    }

    public function cambiarActivo($id, $activo, $usuarioId)
    {
        $this->db->ejecutar(
            'UPDATE T_MER_TIPOS_SOLICITUD_PROV SET ACTIVO = :activo, USUARIO_MODIFICA = :usuario, FECHA_MODIFICACION = GETDATE() WHERE ID = :id',
            array('activo' => (bool) $activo, 'usuario' => (int) $usuarioId, 'id' => (int) $id)
        );
    }

    public function existeNombreEnProceso($nombre, $procesoId, $excluirId = 0)
    {
        return (int) $this->db->valor(
            'SELECT COUNT(*) FROM T_MER_TIPOS_SOLICITUD_PROV WHERE NOMBRE = :nombre AND PROCESO_ID = :proceso AND ID <> :id',
            array('nombre' => $nombre, 'proceso' => (int) $procesoId, 'id' => (int) $excluirId)
        ) > 0;
    }
}

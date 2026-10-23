<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

/**
 * Fragmentos SQL compartidos por los repositorios de solicitudes, catalogo y dashboard.
 * Alias usados: S = T_MER_SOLICITUDES_PROVEEDORES, T = T_MER_TIPOS_SOLICITUD_PROV, TE = tercero.
 */
class SqlComun
{
    const FROM_SOLICITUDES = "
        FROM T_MER_SOLICITUDES_PROVEEDORES S
        INNER JOIN T_MER_TIPOS_SOLICITUD_PROV T ON T.ID = S.TIPO_ID
        OUTER APPLY (SELECT TOP 1 X.NOMBRES FROM T_TERCEROS X WHERE X.CODIGO_SAP = S.CODIGO_SAP_PROVEEDOR) TE";

    /**
     * FROM de solicitudes. Si los filtros traen "nov_actor", agrega NV (novedades no leidas):
     *   NV.N         eventos de la contraparte posteriores a la ultima lectura del actor
     *   NV.ULTIMO_ID ultimo de esos eventos (T_MER_SOLICITUDES_PROV_HISTORIAL.ID)
     * Contraparte: para el proveedor, mercadeo (sin notas internas); para mercadeo, el proveedor.
     */
    public static function desde(array $filtros, array &$params)
    {
        if (empty($filtros['nov_actor'])) {
            return self::FROM_SOLICITUDES;
        }
        $esProveedor       = !empty($filtros['nov_es_proveedor']);
        $params['nov_yo']  = (int) $filtros['nov_actor'];
        $params['nov_rol'] = (int) $filtros['nov_rol_proveedor'];
        return self::FROM_SOLICITUDES . "
        OUTER APPLY (
            SELECT COUNT(*) AS N, MAX(H.ID) AS ULTIMO_ID
              FROM T_MER_SOLICITUDES_PROV_HISTORIAL H
             INNER JOIN T_USUARIOS UH ON UH.ID = H.USUARIO_ID
             WHERE H.SOLICITUD_ID = S.ID AND H.USUARIO_ID <> :nov_yo
               AND UH.ROLES_ID " . ($esProveedor ? "<> :nov_rol AND H.EVENTO <> 'COMENTARIO_INTERNO'" : '= :nov_rol') . "
               AND H.FECHA_CREACION > ISNULL((SELECT L.FECHA_LECTURA FROM T_MER_SOLICITUDES_PROV_LECTURAS L
                                               WHERE L.SOLICITUD_ID = S.ID AND L.USUARIO_ID = :nov_yo), '19000101')
        ) NV";
    }

    /** 'YYYY-MM-DD' -> 'YYYYMMDD' (formato que SQL Server interpreta igual con cualquier idioma). */
    public static function fechaSql($fecha)
    {
        return str_replace('-', '', $fecha);
    }

    public static function fecha($columna)
    {
        return 'CONVERT(varchar(19), ' . $columna . ', 120)';
    }

    /**
     * Construye las condiciones WHERE de solicitudes a partir de filtros + alcance.
     * @return string condiciones unidas con AND (siempre inicia con 1 = 1)
     */
    public static function condiciones(array $filtros, array $alcance, $actorId, array &$params, ConexionMssql $db)
    {
        $w = array('1 = 1');

        if (isset($alcance['codigo_sap_proveedor'])) { $w[] = 'S.CODIGO_SAP_PROVEEDOR = :alc_sap'; $params['alc_sap']  = (int) $alcance['codigo_sap_proveedor']; }
        if (isset($alcance['usuario_id']))           { $w[] = 'S.USUARIO_ID = :alc_usr';           $params['alc_usr']  = (int) $alcance['usuario_id']; }
        if (isset($alcance['organizacion']))         { $w[] = 'S.ORGANIZACION_VENTA = :alc_org';   $params['alc_org']  = (string) $alcance['organizacion']; }
        if (isset($alcance['proceso_id']))           { $w[] = 'T.PROCESO_ID = :alc_proc';          $params['alc_proc'] = (int) $alcance['proceso_id']; }

        if (!empty($filtros['estado'])) {
            $w[] = 'S.ESTADO = :f_estado';
            $params['f_estado'] = $filtros['estado'];
        }
        if (!empty($filtros['novedades'])) {
            $w[] = 'NV.N > 0'; // requiere FROM con desde() y nov_actor
        }
        if (!empty($filtros['abiertas'])) {
            $w[] = "S.ESTADO IN ('PENDIENTE', 'EN_CURSO')";
        }
        if (isset($filtros['asignacion']) && $filtros['asignacion'] === 'sin_asignar') {
            $w[] = 'S.ASIGNADO_A IS NULL';
        } elseif (isset($filtros['asignacion']) && $filtros['asignacion'] === 'mias') {
            $w[] = 'S.ASIGNADO_A = :f_actor';
            $params['f_actor'] = (int) $actorId;
        }
        if (!empty($filtros['proveedor']))  { $w[] = 'S.CODIGO_SAP_PROVEEDOR = :f_prov'; $params['f_prov'] = (int) $filtros['proveedor']; }
        if (!empty($filtros['tipo_id']))    { $w[] = 'S.TIPO_ID = :f_tipo';              $params['f_tipo'] = (int) $filtros['tipo_id']; }
        if (!empty($filtros['asignado_a'])) { $w[] = 'S.ASIGNADO_A = :f_asig';           $params['f_asig'] = (int) $filtros['asignado_a']; }
        if (!empty($filtros['desde'])) {
            $w[] = 'S.FECHA_CREACION >= :f_desde';
            $params['f_desde'] = self::fechaSql($filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $w[] = 'S.FECHA_CREACION < DATEADD(DAY, 1, :f_hasta)';
            $params['f_hasta'] = self::fechaSql($filtros['hasta']);
        }
        if (!empty($filtros['texto'])) {
            $txt = ltrim($filtros['texto'], '#');
            $cond = '(S.ASUNTO LIKE :f_txt OR TE.NOMBRES LIKE :f_txt OR T.NOMBRE LIKE :f_txt';
            if (ctype_digit($txt)) {
                $cond .= ' OR S.ID = :f_id';
                $params['f_id'] = (int) $txt;
            }
            $w[] = $cond . ')';
            $params['f_txt'] = $db->like($txt);
        }
        return implode(' AND ', $w);
    }
}

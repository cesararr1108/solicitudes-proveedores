<?php
namespace SolicitudesProveedores\Dominio;

/**
 * Traduce la PoliticaAcceso a restricciones de consulta (lo que el actor puede listar).
 * Debe mantenerse coherente con PoliticaAcceso::puedeVer().
 */
class Alcance
{
    public static function para(Actor $actor, array $config)
    {
        if ($actor->esAdministrador()) {
            return array();
        }
        if ($actor->esProveedor()) {
            $alcance = array('codigo_sap_proveedor' => $actor->codigoSap());
            if (!$config['proveedor_ve_todo_el_laboratorio']) {
                $alcance['usuario_id'] = $actor->id();
            }
            return $alcance;
        }
        if ($actor->esGestor()) {
            $alcance = array();
            if ($config['gestor_filtra_por_organizacion'] && $actor->organizacion() !== '') {
                $alcance['organizacion'] = $actor->organizacion();
            }
            if ($config['gestor_filtra_por_proceso_rol'] && $actor->procesoRolId() > 0) {
                $alcance['proceso_id'] = $actor->procesoRolId();
            }
            return $alcance;
        }
        // Sin rol valido: nada visible
        return array('usuario_id' => -1);
    }
}

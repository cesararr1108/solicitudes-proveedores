<?php
namespace SolicitudesProveedores\Infraestructura\Http;

use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\DominioException;
use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;

/** Traduce las variables de sesion del ADG ($_SESSION["ses_*"]) a un Actor de dominio. */
class ActorDesdeSesion
{
    public static function crear(array $sesion, array $config, CatalogoRepositorio $catalogo)
    {
        if (empty($sesion['ses_Id']) || empty($sesion['ses_RolesId'])) {
            throw new DominioException('Su sesion ha expirado. Ingrese nuevamente', 401);
        }
        $rolId = (int) $sesion['ses_RolesId'];
        $procesoRol = $config['gestor_filtra_por_proceso_rol'] ? $catalogo->procesoDeRol($rolId) : 0;

        return new Actor(array(
            'id'             => $sesion['ses_Id'],
            'nombre'         => isset($sesion['ses_Usuario']) ? self::utf8($sesion['ses_Usuario']) : '',
            'email'          => isset($sesion['ses_Email']) ? $sesion['ses_Email'] : '',
            'rol_id'         => $rolId,
            'codigo_sap'     => isset($sesion['ses_CodSap']) ? $sesion['ses_CodSap'] : 0,
            'organizacion'   => isset($sesion['ses_NumOrg']) ? $sesion['ses_NumOrg'] : '',
            'oficina'        => isset($sesion['ses_OfcVentas']) ? $sesion['ses_OfcVentas'] : '',
            'proceso_rol_id' => $procesoRol,
        ), $config['roles']);
    }

    /** La sesion guarda el nombre tal como vino de la BD (normalmente Latin-1). */
    private static function utf8($texto)
    {
        if (preg_match('//u', $texto)) {
            return $texto;
        }
        return function_exists('mb_convert_encoding') ? mb_convert_encoding($texto, 'UTF-8', 'Windows-1252') : utf8_encode($texto);
    }
}

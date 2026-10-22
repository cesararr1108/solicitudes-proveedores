<?php
namespace SolicitudesProveedores\Aplicacion\Catalogos;

use SolicitudesProveedores\Aplicacion\Tipos\ListarTipos;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Alcance;
use SolicitudesProveedores\Dominio\EstadoSolicitud;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;

/** Todo lo que una vista necesita para arrancar, en una sola peticion. */
class ObtenerCatalogos
{
    private $catalogo;
    private $listarTipos;
    private $plantillas;
    private $config;

    public function __construct(CatalogoRepositorio $catalogo, ListarTipos $listarTipos,
                                RegistroPlantillas $plantillas, array $config)
    {
        $this->catalogo    = $catalogo;
        $this->listarTipos = $listarTipos;
        $this->plantillas  = $plantillas;
        $this->config      = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $adj = $this->config['adjuntos'];
        $datos = array(
            'actor' => array(
                'id'           => $actor->id(),
                'nombre'       => $actor->nombre(),
                'perfil'       => $actor->perfil(),
                'organizacion' => $actor->organizacion(),
                'proveedor'    => $actor->esProveedor() ? $this->catalogo->nombreProveedor($actor->codigoSap()) : null,
            ),
            'estados'    => EstadoSolicitud::catalogo(),
            'plantillas' => $this->plantillas->catalogo(),
            'tipos'      => $this->listarTipos->ejecutar($actor, array('incluir_inactivos' => $actor->esAdministrador())),
            'adjuntos'   => array(
                'ruta_ftp'      => $adj['ruta_ftp'],
                'max_mb'        => $adj['max_mb'],
                'max_por_envio' => $adj['max_por_envio'],
                'ext_gestion'   => $adj['ext_gestion'],
                'ext_plantilla' => $adj['ext_plantilla'],
            ),
            'max_filas_excel' => $this->config['max_filas_excel'],
            'sla'             => $this->config['sla_horas'],
        );

        if ($actor->esGestor()) {
            $roles = $this->config['roles'];
            $org   = $this->config['gestor_filtra_por_organizacion'] ? $actor->organizacion() : '';
            $datos['proveedores'] = $this->catalogo->listarProveedoresConSolicitudes(Alcance::para($actor, $this->config));
            $datos['gestores']    = $this->catalogo->listarGestores(array($roles['gerente'], $roles['auxiliar']), $org);
        }
        if ($actor->esAdministrador()) {
            $datos['procesos'] = $this->catalogo->listarProcesos();
        }
        return $datos;
    }
}

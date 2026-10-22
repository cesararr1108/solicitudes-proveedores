<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;
use SolicitudesProveedores\Dominio\Puerto\SeguimientoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;
use SolicitudesProveedores\Dominio\Solicitud;

class VerSolicitud
{
    private $solicitudes;
    private $detalle;
    private $seguimiento;
    private $catalogo;
    private $plantillas;
    private $politica;
    private $config;

    public function __construct(SolicitudRepositorio $solicitudes, DetalleRepositorio $detalle,
                                SeguimientoRepositorio $seguimiento, CatalogoRepositorio $catalogo,
                                RegistroPlantillas $plantillas, PoliticaAcceso $politica, array $config)
    {
        $this->solicitudes = $solicitudes;
        $this->detalle     = $detalle;
        $this->seguimiento = $seguimiento;
        $this->catalogo    = $catalogo;
        $this->plantillas  = $plantillas;
        $this->politica    = $politica;
        $this->config      = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $id = Entrada::entero($entrada, 'id');
        $s  = $this->cargar($actor, $id);

        $vista     = $this->solicitudes->obtenerVista($id);
        $plantilla = $this->plantillas->obtener($vista['PLANTILLA']);
        $internos  = $this->politica->puedeVerComentariosInternos($actor);
        $permisos  = $this->politica->permisos($actor, $s);

        return array(
            'solicitud'   => $vista,
            'plantilla'   => array('codigo' => $plantilla->codigo(), 'columnas' => $plantilla->columnas()),
            'detalle'     => $plantilla->obtener($this->detalle, $id),
            'adjuntos'    => $this->seguimiento->listarAdjuntos($id, $internos),
            'comentarios' => $this->seguimiento->listarComentarios($id, $internos),
            'historial'   => $this->seguimiento->listarHistorial($id, $internos),
            'permisos'    => $permisos,
            'asignables'  => $permisos['asignar_a_otros'] ? $this->asignables($actor) : array(),
        );
    }

    /** @return Solicitud */
    private function cargar(Actor $actor, $id)
    {
        $s = $this->solicitudes->obtener($id);
        if ($s === null || !$this->politica->puedeVer($actor, $s)) {
            // Mismo mensaje en ambos casos: no revelar la existencia de solicitudes ajenas
            throw new NoEncontradoException('La solicitud no existe o no tiene acceso a ella');
        }
        return $s;
    }

    private function asignables(Actor $actor)
    {
        $roles = $this->config['roles'];
        $org   = $this->config['gestor_filtra_por_organizacion'] ? $actor->organizacion() : '';
        return $this->catalogo->listarGestores(array($roles['gerente'], $roles['auxiliar']), $org);
    }
}

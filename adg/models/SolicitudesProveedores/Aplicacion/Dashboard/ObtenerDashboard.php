<?php
namespace SolicitudesProveedores\Aplicacion\Dashboard;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Alcance;
use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\DashboardRepositorio;

class ObtenerDashboard
{
    private $dashboard;
    private $politica;
    private $config;

    public function __construct(DashboardRepositorio $dashboard, PoliticaAcceso $politica, array $config)
    {
        $this->dashboard = $dashboard;
        $this->politica  = $politica;
        $this->config    = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $this->politica->asegurar($this->politica->puedeVerDashboard($actor));

        $filtros = array(
            'desde'      => Entrada::fecha($entrada, 'desde', date('Y-m-d', strtotime('-30 days'))),
            'hasta'      => Entrada::fecha($entrada, 'hasta', date('Y-m-d')),
            'proveedor'  => Entrada::entero($entrada, 'proveedor'),
            'tipo_id'    => Entrada::entero($entrada, 'tipo_id'),
            'asignado_a' => Entrada::entero($entrada, 'asignado_a'),
        );
        if ($filtros['desde'] > $filtros['hasta']) {
            throw new ValidacionException('La fecha inicial no puede ser mayor que la final', array('desde' => 'Rango invalido'));
        }

        $datos = $this->dashboard->resumen($filtros, Alcance::para($actor, $this->config), $this->config['sla_horas']);
        $datos['filtros'] = $filtros;
        $datos['sla']     = $this->config['sla_horas'];
        return $datos;
    }
}

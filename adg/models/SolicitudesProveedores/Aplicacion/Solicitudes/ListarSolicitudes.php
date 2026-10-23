<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Alcance;
use SolicitudesProveedores\Dominio\EstadoSolicitud;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;

class ListarSolicitudes
{
    private $solicitudes;
    private $config;

    public function __construct(SolicitudRepositorio $solicitudes, array $config)
    {
        $this->solicitudes = $solicitudes;
        $this->config      = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $pag      = $this->config['paginacion'];
        $estado   = Entrada::texto($entrada, 'estado');
        $asignac  = Entrada::texto($entrada, 'asignacion', 'todas');
        $porPag   = Entrada::entero($entrada, 'por_pagina', $pag['por_pagina']);

        $filtros = array(
            'estado'     => EstadoSolicitud::existe($estado) ? $estado : '',
            'abiertas'   => Entrada::booleano($entrada, 'abiertas'),
            'asignacion' => in_array($asignac, array('sin_asignar', 'mias', 'todas'), true) && $actor->esGestor() ? $asignac : 'todas',
            'proveedor'  => $actor->esGestor() ? Entrada::entero($entrada, 'proveedor') : 0,
            'tipo_id'    => Entrada::entero($entrada, 'tipo_id'),
            'asignado_a' => $actor->esGestor() ? Entrada::entero($entrada, 'asignado_a') : 0,
            'texto'      => substr(Entrada::texto($entrada, 'texto'), 0, 100),
            'desde'      => Entrada::fecha($entrada, 'desde'),
            'hasta'      => Entrada::fecha($entrada, 'hasta'),
            'pagina'     => max(1, Entrada::entero($entrada, 'pagina', 1)),
            'por_pagina' => max(1, min($pag['maximo'], $porPag)),
            'orden'      => Entrada::texto($entrada, 'orden') === 'antiguas' ? 'antiguas' : 'recientes',
            // Novedades: eventos de la contraparte que el actor aun no ha visto
            'novedades'         => Entrada::booleano($entrada, 'novedades'),
            'nov_actor'         => $actor->id(),
            'nov_es_proveedor'  => $actor->esProveedor(),
            'nov_rol_proveedor' => (int) $this->config['roles']['proveedor'],
        );

        $alcance   = Alcance::para($actor, $this->config);
        $resultado = $this->solicitudes->buscar($filtros, $alcance, $actor->id());

        // Conteos por estado ignorando el filtro de estado (para las pestanas)
        $sinEstado = $filtros;
        $sinEstado['estado'] = '';
        $sinEstado['abiertas'] = false;
        $sinEstado['novedades'] = false;

        return array(
            'filas'      => $resultado['filas'],
            'total'      => (int) $resultado['total'],
            'pagina'     => $filtros['pagina'],
            'por_pagina' => $filtros['por_pagina'],
            'conteos'    => $this->solicitudes->contarPorEstado($sinEstado, $alcance, $actor->id()),
            'sla'        => $this->config['sla_horas'],
        );
    }
}

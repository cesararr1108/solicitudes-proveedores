<?php
namespace SolicitudesProveedores\Aplicacion\Tipos;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\Puerto\TipoSolicitudRepositorio;

class ListarTipos
{
    private $tipos;
    private $plantillas;
    private $config;

    public function __construct(TipoSolicitudRepositorio $tipos, RegistroPlantillas $plantillas, array $config)
    {
        $this->tipos      = $tipos;
        $this->plantillas = $plantillas;
        $this->config     = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $filtros = array('solo_activos' => true);
        if ($actor->esAdministrador() && Entrada::booleano($entrada, 'incluir_inactivos')) {
            $filtros['solo_activos'] = false;
        }
        if ($actor->esProveedor() && $this->config['tipos_filtra_por_organizacion']) {
            $filtros['organizacion'] = $actor->organizacion();
        }

        $lista = array();
        foreach ($this->tipos->listar($filtros) as $t) {
            $plantilla = $this->plantillas->obtener($t['PLANTILLA']);
            $t['REQUIERE_EXCEL'] = $plantilla->requiereExcel();
            $t['COLUMNAS']       = $plantilla->columnas();
            $t['EXTENSIONES']    = $this->extensiones($t['EXTENSIONES']);
            $lista[] = $t;
        }
        return $lista;
    }

    private function extensiones($csv)
    {
        $csv = trim((string) $csv);
        return $csv === '' ? $this->config['adjuntos']['ext_gestion'] : explode(',', $csv);
    }
}

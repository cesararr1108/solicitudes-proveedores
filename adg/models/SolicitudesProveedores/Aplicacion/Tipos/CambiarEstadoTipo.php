<?php
namespace SolicitudesProveedores\Aplicacion\Tipos;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\TipoSolicitudRepositorio;

class CambiarEstadoTipo
{
    private $tipos;
    private $politica;

    public function __construct(TipoSolicitudRepositorio $tipos, PoliticaAcceso $politica)
    {
        $this->tipos    = $tipos;
        $this->politica = $politica;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $this->politica->asegurar($this->politica->puedeAdministrarTipos($actor));
        $id = Entrada::entero($entrada, 'id');
        if ($this->tipos->obtener($id) === null) {
            throw new NoEncontradoException('El tipo de solicitud no existe');
        }
        $this->tipos->cambiarActivo($id, Entrada::booleano($entrada, 'activo'), $actor->id());
        return $this->tipos->obtener($id);
    }
}

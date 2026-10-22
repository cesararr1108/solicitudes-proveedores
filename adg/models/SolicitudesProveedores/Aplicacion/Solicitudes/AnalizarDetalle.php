<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\TipoSolicitudRepositorio;

/**
 * Pre-validacion del Excel (antes de enviar): devuelve las filas con la
 * descripcion de T_MATERIALES y los errores/advertencias por fila.
 */
class AnalizarDetalle
{
    private $tipos;
    private $catalogo;
    private $plantillas;
    private $politica;
    private $config;

    public function __construct(TipoSolicitudRepositorio $tipos, CatalogoRepositorio $catalogo,
                                RegistroPlantillas $plantillas, PoliticaAcceso $politica, array $config)
    {
        $this->tipos      = $tipos;
        $this->catalogo   = $catalogo;
        $this->plantillas = $plantillas;
        $this->politica   = $politica;
        $this->config     = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $this->politica->asegurar($this->politica->puedeCrearSolicitud($actor) || $actor->esGestor());

        $tipo = $this->tipos->obtener(Entrada::entero($entrada, 'tipo_id'));
        if ($tipo === null) {
            throw new NoEncontradoException('El tipo de solicitud no existe');
        }
        return $this->analizar($actor, $tipo, Entrada::lista($entrada, 'filas'));
    }

    /** Reutilizado por CrearSolicitud para no duplicar la validacion. */
    public function analizar(Actor $actor, array $tipo, array $filas)
    {
        $plantilla = $this->plantillas->obtener($tipo['PLANTILLA']);
        $labSap    = $this->config['validar_material_del_laboratorio'] && $actor->esProveedor() ? $actor->codigoSap() : null;
        $resultado = $plantilla->analizar($filas, $this->catalogo, $labSap, (int) $this->config['max_filas_excel']);
        $resultado['valido']   = empty($resultado['errores']);
        $resultado['total']    = count($resultado['filas']);
        $resultado['plantilla'] = $plantilla->codigo();
        return $resultado;
    }
}

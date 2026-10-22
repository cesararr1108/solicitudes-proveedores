<?php
namespace SolicitudesProveedores\Aplicacion\Tipos;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\TipoSolicitudRepositorio;
use SolicitudesProveedores\Dominio\Validacion\Reglas;
use SolicitudesProveedores\Dominio\Validacion\Validador;

class GuardarTipo
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
        $this->politica->asegurar($this->politica->puedeAdministrarTipos($actor));

        $datos = array(
            'id'               => Entrada::entero($entrada, 'id'),
            'nombre'           => Entrada::texto($entrada, 'nombre'),
            'descripcion'      => Entrada::texto($entrada, 'descripcion'),
            'proceso_id'       => Entrada::entero($entrada, 'proceso_id'),
            'plantilla'        => strtoupper(Entrada::texto($entrada, 'plantilla', 'LIBRE')),
            'requiere_adjunto' => Entrada::booleano($entrada, 'requiere_adjunto'),
            'extensiones'      => $this->normalizarExtensiones(Entrada::texto($entrada, 'extensiones')),
            'activo'           => isset($entrada['activo']) ? Entrada::booleano($entrada, 'activo') : true,
            'orden'            => Entrada::entero($entrada, 'orden'),
        );

        $v = new Validador();
        $v->campo('nombre', 'Nombre', array(new Reglas\Requerido(), new Reglas\LongitudMinima(3), new Reglas\LongitudMaxima(100)))
          ->campo('descripcion', 'Descripcion', array(new Reglas\LongitudMaxima(500)))
          ->campo('proceso_id', 'Proceso', array(new Reglas\Entero(1)))
          ->campo('plantilla', 'Plantilla', array(new Reglas\Requerido(), new Reglas\EnLista($this->plantillas->codigos())))
          ->campo('orden', 'Orden', array(new Reglas\Entero(0, 9999)))
          ->validar($datos);

        if ($datos['id'] > 0 && $this->tipos->obtener($datos['id']) === null) {
            throw new NoEncontradoException('El tipo de solicitud no existe');
        }
        if ($this->catalogo->obtenerProceso($datos['proceso_id']) === null) {
            throw new ValidacionException('Revise los datos del formulario', array('proceso_id' => 'El proceso no existe'));
        }
        if ($this->tipos->existeNombreEnProceso($datos['nombre'], $datos['proceso_id'], $datos['id'])) {
            throw new ValidacionException('Revise los datos del formulario', array('nombre' => 'Ya existe un tipo con ese nombre en el proceso'));
        }

        // Las plantillas Excel siempre exigen su archivo
        if ($this->plantillas->obtener($datos['plantilla'])->requiereExcel()) {
            $datos['requiere_adjunto'] = true;
            $datos['extensiones']      = implode(',', $this->config['adjuntos']['ext_plantilla']);
        }

        $id = $this->tipos->guardar($datos, $actor->id());
        return $this->tipos->obtener($id);
    }

    private function normalizarExtensiones($csv)
    {
        $lista = array();
        foreach (preg_split('/[\s,;]+/', strtolower($csv)) as $ext) {
            $ext = ltrim($ext, '.');
            if ($ext === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9]{1,6}$/', $ext)) {
                throw new ValidacionException('Revise los datos del formulario', array('extensiones' => 'Extension no valida: ' . $ext));
            }
            $lista[$ext] = $ext;
        }
        return implode(',', array_values($lista));
    }
}

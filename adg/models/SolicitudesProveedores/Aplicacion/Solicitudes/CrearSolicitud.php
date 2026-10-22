<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;
use SolicitudesProveedores\Dominio\Puerto\Notificador;
use SolicitudesProveedores\Dominio\Puerto\SeguimientoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;
use SolicitudesProveedores\Dominio\Puerto\TipoSolicitudRepositorio;
use SolicitudesProveedores\Dominio\Puerto\Transaccion;
use SolicitudesProveedores\Dominio\ReglasAdjunto;
use SolicitudesProveedores\Dominio\Solicitud;
use SolicitudesProveedores\Dominio\Validacion\Reglas;
use SolicitudesProveedores\Dominio\Validacion\Validador;

class CrearSolicitud
{
    private $solicitudes;
    private $tipos;
    private $detalle;
    private $seguimiento;
    private $plantillas;
    private $analizador;
    private $reglasAdjunto;
    private $politica;
    private $transaccion;
    private $notificador;
    private $config;

    public function __construct(SolicitudRepositorio $solicitudes, TipoSolicitudRepositorio $tipos,
                                DetalleRepositorio $detalle, SeguimientoRepositorio $seguimiento,
                                RegistroPlantillas $plantillas, AnalizarDetalle $analizador,
                                ReglasAdjunto $reglasAdjunto, PoliticaAcceso $politica,
                                Transaccion $transaccion, Notificador $notificador, array $config)
    {
        $this->solicitudes   = $solicitudes;
        $this->tipos         = $tipos;
        $this->detalle       = $detalle;
        $this->seguimiento   = $seguimiento;
        $this->plantillas    = $plantillas;
        $this->analizador    = $analizador;
        $this->reglasAdjunto = $reglasAdjunto;
        $this->politica      = $politica;
        $this->transaccion   = $transaccion;
        $this->notificador   = $notificador;
        $this->config        = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $this->politica->asegurar(
            $this->politica->puedeCrearSolicitud($actor),
            'Solo los usuarios proveedor con laboratorio asignado pueden crear solicitudes'
        );

        $datos = array(
            'tipo_id'     => Entrada::entero($entrada, 'tipo_id'),
            'asunto'      => Entrada::texto($entrada, 'asunto'),
            'observacion' => Entrada::texto($entrada, 'observacion'),
        );
        $v = new Validador();
        $v->campo('tipo_id', 'Tipo de solicitud', array(new Reglas\Entero(1)))
          ->campo('asunto', 'Asunto', array(new Reglas\Requerido(), new Reglas\LongitudMinima(5), new Reglas\LongitudMaxima(150)))
          ->campo('observacion', 'Observacion', array(new Reglas\Requerido(), new Reglas\LongitudMinima(10), new Reglas\LongitudMaxima(8000)))
          ->validar($datos);

        $tipo = $this->tipoDisponible($actor, $datos['tipo_id']);
        $plantilla = $this->plantillas->obtener($tipo['PLANTILLA']);
        $extTipo = trim($tipo['EXTENSIONES']) === '' ? $this->config['adjuntos']['ext_gestion'] : explode(',', $tipo['EXTENSIONES']);

        // Adjuntos libres
        $adjuntos = $this->reglasAdjunto->normalizar(Entrada::lista($entrada, 'adjuntos'), $plantilla->requiereExcel() ? $this->config['adjuntos']['ext_gestion'] : $extTipo);

        // Plantilla Excel
        $filas = array();
        $excel = null;
        if ($plantilla->requiereExcel()) {
            $archivo = Entrada::objeto($entrada, 'archivo_excel');
            if ($archivo === null) {
                throw new ValidacionException('Debe adjuntar el archivo Excel de ' . $plantilla->nombre(), array('archivo_excel' => 'Obligatorio'));
            }
            $lista = $this->reglasAdjunto->normalizar(array($archivo), $this->config['adjuntos']['ext_plantilla']);
            $excel = $lista[0];

            $analisis = $this->analizador->analizar($actor, $tipo, Entrada::lista($entrada, 'filas'));
            if (!$analisis['valido']) {
                throw new ValidacionException('El archivo tiene errores. Corrijalos y vuelva a cargarlo', $analisis['errores']);
            }
            $filas = $analisis['filas'];
        } elseif ((int) $tipo['REQUIERE_ADJUNTO'] === 1 && empty($adjuntos)) {
            throw new ValidacionException('Este tipo de solicitud requiere al menos un adjunto', array('adjuntos' => 'Obligatorio'));
        }

        $solicitud   = Solicitud::nueva($actor, $tipo['ID'], $datos['asunto'], $datos['observacion']);
        $repo        = $this->solicitudes;
        $detalle     = $this->detalle;
        $seguimiento = $this->seguimiento;

        $id = $this->transaccion->ejecutar(function () use ($repo, $detalle, $seguimiento, $plantilla, $solicitud, $filas, $excel, $adjuntos, $actor) {
            $id = $repo->crear($solicitud);
            $plantilla->guardar($detalle, $id, $filas);
            if ($excel !== null) {
                $seguimiento->agregarAdjunto($id, null, $excel, $actor->id(), true);
            }
            foreach ($adjuntos as $a) {
                $seguimiento->agregarAdjunto($id, null, $a, $actor->id(), false);
            }
            $seguimiento->registrarEvento($id, $actor->id(), 'CREADA', array(
                'estado_nuevo' => $solicitud->estado(),
                'detalle'      => count($filas) > 0 ? count($filas) . ' registros en el detalle' : null,
            ));
            return $id;
        });

        $this->notificador->notificar('SOLICITUD_CREADA', array('id' => $id, 'tipo' => $tipo['NOMBRE'], 'actor' => $actor->id()));
        return array('id' => $id);
    }

    private function tipoDisponible(Actor $actor, $tipoId)
    {
        $tipo = $this->tipos->obtener($tipoId);
        if ($tipo === null || (int) $tipo['ACTIVO'] !== 1) {
            throw new ValidacionException('El tipo de solicitud no esta disponible', array('tipo_id' => 'No disponible'));
        }
        $orgTipo = trim((string) $tipo['ORGANIZACION']);
        if ($this->config['tipos_filtra_por_organizacion'] && $orgTipo !== '' && $orgTipo !== $actor->organizacion()) {
            throw new ValidacionException('El tipo de solicitud no aplica para su organizacion', array('tipo_id' => 'No disponible'));
        }
        return $tipo;
    }
}

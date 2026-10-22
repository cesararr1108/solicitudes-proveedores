<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\EstadoSolicitud;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\Notificador;
use SolicitudesProveedores\Dominio\Puerto\SeguimientoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;
use SolicitudesProveedores\Dominio\Puerto\Transaccion;
use SolicitudesProveedores\Dominio\Validacion\Reglas;
use SolicitudesProveedores\Dominio\Validacion\Validador;

class CambiarEstadoSolicitud
{
    private $solicitudes;
    private $seguimiento;
    private $politica;
    private $transaccion;
    private $notificador;

    public function __construct(SolicitudRepositorio $solicitudes, SeguimientoRepositorio $seguimiento,
                                PoliticaAcceso $politica, Transaccion $transaccion, Notificador $notificador)
    {
        $this->solicitudes = $solicitudes;
        $this->seguimiento = $seguimiento;
        $this->politica    = $politica;
        $this->transaccion = $transaccion;
        $this->notificador = $notificador;
    }

    /**
     * Reglas extra segun el estado destino. Agregue aqui nuevas exigencias por estado.
     * @return Validador
     */
    protected function validadorPara($estadoDestino)
    {
        $v = new Validador();
        $v->campo('estado', 'Estado', array(new Reglas\Requerido(), new Reglas\EnLista(EstadoSolicitud::todos())))
          ->campo('comentario', 'Comentario', array(new Reglas\LongitudMaxima(8000)));

        if ($estadoDestino === EstadoSolicitud::FINALIZADO) {
            $v->campo('comentario', 'El comentario de cierre', array(
                new Reglas\Requerido(), new Reglas\LongitudMinima(5), new Reglas\LongitudMaxima(8000),
            ));
        }
        return $v;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $s = $this->solicitudes->obtener(Entrada::entero($entrada, 'id'));
        if ($s === null || !$this->politica->puedeVer($actor, $s)) {
            throw new NoEncontradoException('La solicitud no existe o no tiene acceso a ella');
        }
        $this->politica->asegurar(
            $this->politica->puedeCambiarEstado($actor, $s),
            'Solo quien tiene asignada la solicitud (o el gerente) puede cambiar su estado'
        );

        $datos = array(
            'estado'     => Entrada::texto($entrada, 'estado'),
            'comentario' => Entrada::texto($entrada, 'comentario'),
        );
        $this->validadorPara($datos['estado'])->validar($datos);

        // Quien la pone en curso sin que este asignada, la toma
        $asignadoAnterior = null;
        $autoAsignada     = false;
        if (!$s->estaAsignada()) {
            $asignadoAnterior = $s->asignar($actor->id());
            $autoAsignada     = true;
        }
        $estadoAnterior = $s->cambiarEstado($datos['estado']);

        $repo        = $this->solicitudes;
        $seguimiento = $this->seguimiento;
        $this->transaccion->ejecutar(function () use ($repo, $seguimiento, $s, $actor, $datos, $estadoAnterior, $autoAsignada, $asignadoAnterior) {
            if ($autoAsignada) {
                $repo->actualizarAsignacion($s);
                $seguimiento->registrarEvento($s->id(), $actor->id(), 'ASIGNADA', array(
                    'asignado_anterior' => $asignadoAnterior,
                    'asignado_nuevo'    => $actor->id(),
                ));
            }
            $repo->actualizarEstado($s);
            $comentarioId = null;
            if ($datos['comentario'] !== '') {
                $comentarioId = $seguimiento->agregarComentario($s->id(), $actor->id(), $datos['comentario'], false);
            }
            $seguimiento->registrarEvento($s->id(), $actor->id(), 'ESTADO', array(
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $s->estado(),
                'detalle'         => $comentarioId ? 'Con comentario' : null,
            ));
        });

        $this->notificador->notificar('SOLICITUD_ESTADO', array(
            'id' => $s->id(), 'estado' => $s->estado(), 'anterior' => $estadoAnterior, 'actor' => $actor->id(),
        ));
        return array('id' => $s->id(), 'estado' => $s->estado());
    }
}

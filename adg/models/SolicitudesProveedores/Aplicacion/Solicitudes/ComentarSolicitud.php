<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\Notificador;
use SolicitudesProveedores\Dominio\Puerto\SeguimientoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;
use SolicitudesProveedores\Dominio\Puerto\Transaccion;
use SolicitudesProveedores\Dominio\ReglasAdjunto;
use SolicitudesProveedores\Dominio\Validacion\Reglas;
use SolicitudesProveedores\Dominio\Validacion\Validador;

/** Comentario con adjuntos opcionales (incluye pantallazos pegados con Ctrl+V). */
class ComentarSolicitud
{
    private $solicitudes;
    private $seguimiento;
    private $reglasAdjunto;
    private $politica;
    private $transaccion;
    private $notificador;
    private $config;

    public function __construct(SolicitudRepositorio $solicitudes, SeguimientoRepositorio $seguimiento,
                                ReglasAdjunto $reglasAdjunto, PoliticaAcceso $politica,
                                Transaccion $transaccion, Notificador $notificador, array $config)
    {
        $this->solicitudes   = $solicitudes;
        $this->seguimiento   = $seguimiento;
        $this->reglasAdjunto = $reglasAdjunto;
        $this->politica      = $politica;
        $this->transaccion   = $transaccion;
        $this->notificador   = $notificador;
        $this->config        = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $s = $this->solicitudes->obtener(Entrada::entero($entrada, 'id'));
        if ($s === null || !$this->politica->puedeVer($actor, $s)) {
            throw new NoEncontradoException('La solicitud no existe o no tiene acceso a ella');
        }
        $this->politica->asegurar($this->politica->puedeComentar($actor, $s), 'No puede comentar esta solicitud');

        $texto    = Entrada::texto($entrada, 'comentario');
        $interno  = $actor->esGestor() && Entrada::booleano($entrada, 'interno');
        $adjuntos = $this->reglasAdjunto->normalizar(Entrada::lista($entrada, 'adjuntos'), $this->config['adjuntos']['ext_gestion']);

        $v = new Validador();
        $v->campo('comentario', 'Comentario', array(new Reglas\LongitudMaxima(8000)))->validar(array('comentario' => $texto));
        if ($texto === '' && empty($adjuntos)) {
            throw new ValidacionException('Escriba un comentario o adjunte un archivo', array('comentario' => 'Obligatorio'));
        }

        $seguimiento = $this->seguimiento;
        $comentarioId = $this->transaccion->ejecutar(function () use ($seguimiento, $s, $actor, $texto, $interno, $adjuntos) {
            $id = $seguimiento->agregarComentario($s->id(), $actor->id(), $texto, $interno);
            foreach ($adjuntos as $a) {
                $seguimiento->agregarAdjunto($s->id(), $id, $a, $actor->id(), false);
            }
            $seguimiento->registrarEvento($s->id(), $actor->id(), $interno ? 'COMENTARIO_INTERNO' : 'COMENTARIO', array(
                'detalle' => count($adjuntos) > 0 ? count($adjuntos) . ' adjunto(s)' : null,
            ));
            return $id;
        });

        if (!$interno) {
            $this->notificador->notificar('SOLICITUD_COMENTARIO', array('id' => $s->id(), 'actor' => $actor->id()));
        }
        return array('id' => $comentarioId);
    }
}

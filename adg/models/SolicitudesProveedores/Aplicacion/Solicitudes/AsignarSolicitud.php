<?php
namespace SolicitudesProveedores\Aplicacion\Solicitudes;

use SolicitudesProveedores\Aplicacion\Entrada;
use SolicitudesProveedores\Dominio\Actor;
use SolicitudesProveedores\Dominio\Excepcion\NoEncontradoException;
use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\Notificador;
use SolicitudesProveedores\Dominio\Puerto\SeguimientoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\SolicitudRepositorio;
use SolicitudesProveedores\Dominio\Puerto\Transaccion;

/**
 * Gerente/administrador asigna a cualquier gestor; el auxiliar solo se la "toma" a si mismo.
 * Si usuario_id no viene, se asigna al actor.
 */
class AsignarSolicitud
{
    private $solicitudes;
    private $seguimiento;
    private $catalogo;
    private $politica;
    private $transaccion;
    private $notificador;
    private $config;

    public function __construct(SolicitudRepositorio $solicitudes, SeguimientoRepositorio $seguimiento,
                                CatalogoRepositorio $catalogo, PoliticaAcceso $politica,
                                Transaccion $transaccion, Notificador $notificador, array $config)
    {
        $this->solicitudes = $solicitudes;
        $this->seguimiento = $seguimiento;
        $this->catalogo    = $catalogo;
        $this->politica    = $politica;
        $this->transaccion = $transaccion;
        $this->notificador = $notificador;
        $this->config      = $config;
    }

    public function ejecutar(Actor $actor, array $entrada)
    {
        $s = $this->solicitudes->obtener(Entrada::entero($entrada, 'id'));
        if ($s === null || !$this->politica->puedeVer($actor, $s)) {
            throw new NoEncontradoException('La solicitud no existe o no tiene acceso a ella');
        }

        $destinoId = Entrada::entero($entrada, 'usuario_id', $actor->id());
        $this->politica->asegurar(
            $this->politica->puedeAsignar($actor, $s, $destinoId),
            $actor->esAuxiliar() ? 'Solo puede tomar solicitudes sin asignar, y asignarlas a usted mismo' : null
        );
        if ($s->asignadoA() === $destinoId) {
            throw new ValidacionException('La solicitud ya esta asignada a ese usuario');
        }
        $destino = $this->validarDestino($destinoId);

        $anterior    = $s->asignar($destinoId);
        $repo        = $this->solicitudes;
        $seguimiento = $this->seguimiento;
        $this->transaccion->ejecutar(function () use ($repo, $seguimiento, $s, $anterior, $destinoId, $actor) {
            $repo->actualizarAsignacion($s);
            $seguimiento->registrarEvento($s->id(), $actor->id(), 'ASIGNADA', array(
                'asignado_anterior' => $anterior,
                'asignado_nuevo'    => $destinoId,
            ));
        });

        $this->notificador->notificar('SOLICITUD_ASIGNADA', array('id' => $s->id(), 'usuario_id' => $destinoId, 'actor' => $actor->id()));
        return array('id' => $s->id(), 'asignado_a' => $destinoId, 'asignado_nombre' => $destino['NOMBRE']);
    }

    private function validarDestino($usuarioId)
    {
        $u     = $this->catalogo->obtenerUsuario($usuarioId);
        $roles = $this->config['roles'];
        $validos = array((int) $roles['gerente'], (int) $roles['auxiliar'], (int) $roles['administrador']);
        if ($u === null || $u['ESTADO'] !== 'A' || !in_array((int) $u['ROLES_ID'], $validos, true)) {
            throw new ValidacionException('El usuario destino no es un gestor activo de mercadeo', array('usuario_id' => 'No valido'));
        }
        return $u;
    }
}

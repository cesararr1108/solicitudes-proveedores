<?php
namespace SolicitudesProveedores\Dominio;

use SolicitudesProveedores\Dominio\Excepcion\AccesoDenegadoException;

/**
 * Reglas de autorizacion del modulo. Un solo lugar para "quien puede que".
 * La UI no decide permisos: los recibe calculados desde aqui (ver permisos()).
 */
class PoliticaAcceso
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function asegurar($condicion, $mensaje = null)
    {
        if (!$condicion) {
            throw $mensaje === null ? new AccesoDenegadoException() : new AccesoDenegadoException($mensaje);
        }
    }

    public function puedeAdministrarTipos(Actor $actor)
    {
        return $actor->esAdministrador();
    }

    public function puedeCrearSolicitud(Actor $actor)
    {
        return $actor->esProveedor() && $actor->codigoSap() > 0;
    }

    public function puedeVerDashboard(Actor $actor)
    {
        return $actor->esGestor();
    }

    public function puedeVer(Actor $actor, Solicitud $s)
    {
        if ($actor->esAdministrador()) {
            return true;
        }
        if ($actor->esProveedor()) {
            if ($s->codigoSapProveedor() !== $actor->codigoSap()) {
                return false;
            }
            return $this->config['proveedor_ve_todo_el_laboratorio'] || $s->usuarioId() === $actor->id();
        }
        if ($actor->esGestor()) {
            if ($this->config['gestor_filtra_por_organizacion'] && $actor->organizacion() !== ''
                && $s->organizacion() !== $actor->organizacion()) {
                return false;
            }
            if ($this->config['gestor_filtra_por_proceso_rol'] && $actor->procesoRolId() > 0
                && $s->procesoId() !== $actor->procesoRolId()) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Gerente/administrador: asigna a cualquier gestor.
     * Auxiliar: solo se asigna a si mismo y solo si nadie la tiene.
     */
    public function puedeAsignar(Actor $actor, Solicitud $s, $destinoId)
    {
        if ($s->estaFinalizada() || !$this->puedeVer($actor, $s)) {
            return false;
        }
        if ($actor->esSupervisor()) {
            return true;
        }
        if ($actor->esAuxiliar()) {
            return (int) $destinoId === $actor->id() && !$s->estaAsignada();
        }
        return false;
    }

    public function puedeCambiarEstado(Actor $actor, Solicitud $s)
    {
        if ($s->estaFinalizada() || !$this->puedeVer($actor, $s) || !$actor->esGestor()) {
            return false;
        }
        if ($actor->esSupervisor()) {
            return true;
        }
        // Auxiliar: sobre las suyas, o tomando una sin asignar
        return !$s->estaAsignada() || $s->asignadoA() === $actor->id();
    }

    public function puedeComentar(Actor $actor, Solicitud $s)
    {
        if (!$this->puedeVer($actor, $s)) {
            return false;
        }
        if ($actor->esProveedor()) {
            return !$s->estaFinalizada();
        }
        return $actor->esGestor();
    }

    public function puedeVerComentariosInternos(Actor $actor)
    {
        return $actor->esGestor();
    }

    /** Permisos calculados para pintar la UI del detalle. */
    public function permisos(Actor $actor, Solicitud $s)
    {
        $cambiaEstado = $this->puedeCambiarEstado($actor, $s);
        return array(
            'asignar_a_otros'  => !$s->estaFinalizada() && $actor->esSupervisor() && $this->puedeVer($actor, $s),
            'tomar'            => $this->puedeAsignar($actor, $s, $actor->id()) && $s->asignadoA() !== $actor->id(),
            'cambiar_estado'   => $cambiaEstado,
            'estados_destino'  => $cambiaEstado ? EstadoSolicitud::siguientes($s->estado()) : array(),
            'comentar'         => $this->puedeComentar($actor, $s),
            'comentar_interno' => $actor->esGestor(),
        );
    }
}

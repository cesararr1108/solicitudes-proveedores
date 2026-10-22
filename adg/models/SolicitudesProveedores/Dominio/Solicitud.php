<?php
namespace SolicitudesProveedores\Dominio;

use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;

/**
 * Entidad Solicitud. Encapsula las reglas que cambian su estado o asignacion.
 */
class Solicitud
{
    private $id;
    private $tipoId;
    private $asunto;
    private $observacion;
    private $estado;
    private $codigoSapProveedor;
    private $usuarioId;
    private $organizacion;
    private $oficina;
    private $asignadoA;
    private $procesoId;

    public static function nueva(Actor $actor, $tipoId, $asunto, $observacion)
    {
        $s = new self();
        $s->tipoId             = (int) $tipoId;
        $s->asunto             = $asunto;
        $s->observacion        = $observacion;
        $s->estado             = EstadoSolicitud::PENDIENTE;
        $s->codigoSapProveedor = $actor->codigoSap();
        $s->usuarioId          = $actor->id();
        $s->organizacion       = $actor->organizacion();
        $s->oficina            = $actor->oficina();
        return $s;
    }

    /** Reconstruye la entidad desde una fila de BD. */
    public static function desdeFila(array $f)
    {
        $s = new self();
        $s->id                 = (int) $f['ID'];
        $s->tipoId             = (int) $f['TIPO_ID'];
        $s->asunto             = $f['ASUNTO'];
        $s->observacion        = isset($f['OBSERVACION']) ? $f['OBSERVACION'] : '';
        $s->estado             = $f['ESTADO'];
        $s->codigoSapProveedor = (int) $f['CODIGO_SAP_PROVEEDOR'];
        $s->usuarioId          = (int) $f['USUARIO_ID'];
        $s->organizacion       = trim($f['ORGANIZACION_VENTA']);
        $s->oficina            = trim($f['OFICINA_VENTAS']);
        $s->asignadoA          = $f['ASIGNADO_A'] === null || $f['ASIGNADO_A'] === '' ? null : (int) $f['ASIGNADO_A'];
        $s->procesoId          = isset($f['PROCESO_ID']) ? (int) $f['PROCESO_ID'] : 0;
        return $s;
    }

    public function asignar($usuarioId)
    {
        if ($this->estaFinalizada()) {
            throw new ValidacionException('La solicitud ya esta finalizada, no se puede reasignar');
        }
        $anterior        = $this->asignadoA;
        $this->asignadoA = (int) $usuarioId;
        return $anterior;
    }

    public function cambiarEstado($nuevo)
    {
        EstadoSolicitud::asegurarTransicion($this->estado, $nuevo);
        $anterior     = $this->estado;
        $this->estado = $nuevo;
        return $anterior;
    }

    public function estaFinalizada() { return $this->estado === EstadoSolicitud::FINALIZADO; }
    public function estaAsignada()   { return $this->asignadoA !== null && $this->asignadoA > 0; }

    public function id()                 { return $this->id; }
    public function tipoId()             { return $this->tipoId; }
    public function asunto()             { return $this->asunto; }
    public function observacion()        { return $this->observacion; }
    public function estado()             { return $this->estado; }
    public function codigoSapProveedor() { return $this->codigoSapProveedor; }
    public function usuarioId()          { return $this->usuarioId; }
    public function organizacion()       { return $this->organizacion; }
    public function oficina()            { return $this->oficina; }
    public function asignadoA()          { return $this->asignadoA; }
    public function procesoId()          { return $this->procesoId; }
}

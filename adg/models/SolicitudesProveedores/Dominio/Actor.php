<?php
namespace SolicitudesProveedores\Dominio;

/**
 * Usuario autenticado que ejecuta un caso de uso (se arma desde la sesion PHP).
 * Inmutable: el dominio nunca lee $_SESSION directamente.
 */
class Actor
{
    private $id;
    private $nombre;
    private $email;
    private $rolId;
    private $codigoSap;
    private $organizacion;
    private $oficina;
    private $procesoRolId;
    private $roles;

    public function __construct(array $datos, array $roles)
    {
        $this->id           = (int) $datos['id'];
        $this->nombre       = isset($datos['nombre']) ? $datos['nombre'] : '';
        $this->email        = isset($datos['email']) ? $datos['email'] : '';
        $this->rolId        = (int) $datos['rol_id'];
        $this->codigoSap    = isset($datos['codigo_sap']) ? (int) $datos['codigo_sap'] : 0;
        $this->organizacion = isset($datos['organizacion']) ? trim($datos['organizacion']) : '';
        $this->oficina      = isset($datos['oficina']) ? trim($datos['oficina']) : '';
        $this->procesoRolId = isset($datos['proceso_rol_id']) ? (int) $datos['proceso_rol_id'] : 0;
        $this->roles        = $roles;
    }

    public function id()           { return $this->id; }
    public function nombre()       { return $this->nombre; }
    public function email()        { return $this->email; }
    public function rolId()        { return $this->rolId; }
    public function codigoSap()    { return $this->codigoSap; }
    public function organizacion() { return $this->organizacion; }
    public function oficina()      { return $this->oficina; }
    public function procesoRolId() { return $this->procesoRolId; }

    public function esAdministrador() { return $this->rolId === (int) $this->roles['administrador']; }
    public function esGerente()       { return $this->rolId === (int) $this->roles['gerente']; }
    public function esAuxiliar()      { return $this->rolId === (int) $this->roles['auxiliar']; }
    public function esProveedor()     { return $this->rolId === (int) $this->roles['proveedor']; }

    /** Puede gestionar solicitudes (mercadeo o administrador). */
    public function esGestor()
    {
        return $this->esAdministrador() || $this->esGerente() || $this->esAuxiliar();
    }

    /** Puede asignar solicitudes a terceros. */
    public function esSupervisor()
    {
        return $this->esAdministrador() || $this->esGerente();
    }

    public function perfil()
    {
        if ($this->esAdministrador()) { return 'ADMINISTRADOR'; }
        if ($this->esGerente())       { return 'GERENTE'; }
        if ($this->esAuxiliar())      { return 'AUXILIAR'; }
        if ($this->esProveedor())     { return 'PROVEEDOR'; }
        return 'OTRO';
    }
}

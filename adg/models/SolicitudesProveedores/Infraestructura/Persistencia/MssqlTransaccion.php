<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

use SolicitudesProveedores\Dominio\Puerto\Transaccion;

class MssqlTransaccion implements Transaccion
{
    private $db;

    public function __construct(ConexionMssql $db)
    {
        $this->db = $db;
    }

    public function ejecutar($fn)
    {
        $this->db->iniciar();
        try {
            $resultado = call_user_func($fn);
            $this->db->confirmar();
            return $resultado;
        } catch (\Exception $e) {
            $this->db->revertir();
            throw $e;
        }
    }
}

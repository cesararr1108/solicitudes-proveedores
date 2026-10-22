<?php
namespace SolicitudesProveedores\Dominio\Puerto;

/** Lecturas de tablas maestras existentes (T_MATERIALES, T_CAL_PROCESOS, T_USUARIOS, T_TERCEROS...). */
interface CatalogoRepositorio
{
    /**
     * @param array    $codigos codigos tal como vienen del Excel
     * @param int|null $codigoSapLaboratorio si se envia, solo materiales de ese laboratorio
     * @return array codigo_buscado => array(CODIGO_MATERIAL, DESCRIPCION, ANULADA, BLOQUEO_VENTA)
     */
    public function buscarMateriales(array $codigos, $codigoSapLaboratorio = null);

    public function listarProcesos();

    public function obtenerProceso($id);

    /** Proveedores (T_TERCEROS) que tienen solicitudes registradas. */
    public function listarProveedoresConSolicitudes(array $alcance);

    public function nombreProveedor($codigoSap);

    /** Usuarios activos con roles de gestion. */
    public function listarGestores(array $rolesIds, $organizacion = '');

    /** @return array|null datos basicos del usuario (ID, NOMBRE, ROLES_ID, ESTADO, ORGANIZACION_VENTA) */
    public function obtenerUsuario($id);

    /** PROCESO_ID asociado a un rol en T_ROLES (0 si no tiene). */
    public function procesoDeRol($rolId);
}

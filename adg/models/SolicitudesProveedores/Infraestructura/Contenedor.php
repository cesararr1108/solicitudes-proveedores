<?php
namespace SolicitudesProveedores\Infraestructura;

use SolicitudesProveedores\Aplicacion\Catalogos\ObtenerCatalogos;
use SolicitudesProveedores\Aplicacion\Dashboard\ObtenerDashboard;
use SolicitudesProveedores\Aplicacion\Solicitudes\AnalizarDetalle;
use SolicitudesProveedores\Aplicacion\Solicitudes\AsignarSolicitud;
use SolicitudesProveedores\Aplicacion\Solicitudes\CambiarEstadoSolicitud;
use SolicitudesProveedores\Aplicacion\Solicitudes\ComentarSolicitud;
use SolicitudesProveedores\Aplicacion\Solicitudes\CrearSolicitud;
use SolicitudesProveedores\Aplicacion\Solicitudes\ListarSolicitudes;
use SolicitudesProveedores\Aplicacion\Solicitudes\VerSolicitud;
use SolicitudesProveedores\Aplicacion\Tipos\CambiarEstadoTipo;
use SolicitudesProveedores\Aplicacion\Tipos\GuardarTipo;
use SolicitudesProveedores\Aplicacion\Tipos\ListarTipos;
use SolicitudesProveedores\Dominio\Excepcion\DominioException;
use SolicitudesProveedores\Dominio\Plantilla\RegistroPlantillas;
use SolicitudesProveedores\Dominio\PoliticaAcceso;
use SolicitudesProveedores\Dominio\ReglasAdjunto;
use SolicitudesProveedores\Infraestructura\Notificacion\NotificadorNulo;
use SolicitudesProveedores\Infraestructura\Persistencia\ConexionMssql;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlCatalogoRepositorio;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlDashboardRepositorio;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlDetalleRepositorio;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlSeguimientoRepositorio;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlSolicitudRepositorio;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlTipoSolicitudRepositorio;
use SolicitudesProveedores\Infraestructura\Persistencia\MssqlTransaccion;

/**
 * Composicion de dependencias (unico lugar donde se eligen los adaptadores).
 * Cambiar de motor de BD, notificador, etc. = cambiar la fabrica aqui.
 */
class Contenedor
{
    private $config;
    private $db;
    private $instancias = array();
    private $fabricas   = array();

    public function __construct(array $config, ConexionMssql $db)
    {
        $this->config = $config;
        $this->db     = $db;
        $this->registrarServicios();
        $this->registrarCasosDeUso();
    }

    public function config()
    {
        return $this->config;
    }

    public function obtener($nombre)
    {
        if (!isset($this->instancias[$nombre])) {
            if (!isset($this->fabricas[$nombre])) {
                throw new DominioException('Servicio no registrado: ' . $nombre, 500);
            }
            $this->instancias[$nombre] = call_user_func($this->fabricas[$nombre], $this);
        }
        return $this->instancias[$nombre];
    }

    public function registrar($nombre, $fabrica)
    {
        $this->fabricas[$nombre] = $fabrica;
        unset($this->instancias[$nombre]);
    }

    public function tiene($nombre)
    {
        return isset($this->fabricas[$nombre]);
    }

    private function registrarServicios()
    {
        $db  = $this->db;
        $cfg = $this->config;

        $this->registrar('politica', function () use ($cfg) { return new PoliticaAcceso($cfg); });
        $this->registrar('plantillas', function () { return RegistroPlantillas::predeterminado(); });
        $this->registrar('reglas_adjunto', function () use ($cfg) { return new ReglasAdjunto($cfg['adjuntos']); });
        $this->registrar('notificador', function () { return new NotificadorNulo(); });
        $this->registrar('transaccion', function () use ($db) { return new MssqlTransaccion($db); });

        $this->registrar('repo.tipos', function () use ($db) { return new MssqlTipoSolicitudRepositorio($db); });
        $this->registrar('repo.solicitudes', function () use ($db) { return new MssqlSolicitudRepositorio($db); });
        $this->registrar('repo.detalle', function () use ($db) { return new MssqlDetalleRepositorio($db); });
        $this->registrar('repo.seguimiento', function () use ($db, $cfg) { return new MssqlSeguimientoRepositorio($db, $cfg['roles']['proveedor']); });
        $this->registrar('repo.catalogo', function () use ($db) { return new MssqlCatalogoRepositorio($db); });
        $this->registrar('repo.dashboard', function () use ($db) { return new MssqlDashboardRepositorio($db); });
    }

    private function registrarCasosDeUso()
    {
        $cfg = $this->config;

        $this->registrar('tipos.listar', function ($c) use ($cfg) {
            return new ListarTipos($c->obtener('repo.tipos'), $c->obtener('plantillas'), $cfg);
        });
        $this->registrar('tipos.guardar', function ($c) use ($cfg) {
            return new GuardarTipo($c->obtener('repo.tipos'), $c->obtener('repo.catalogo'), $c->obtener('plantillas'), $c->obtener('politica'), $cfg);
        });
        $this->registrar('tipos.estado', function ($c) {
            return new CambiarEstadoTipo($c->obtener('repo.tipos'), $c->obtener('politica'));
        });
        $this->registrar('solicitudes.analizar', function ($c) use ($cfg) {
            return new AnalizarDetalle($c->obtener('repo.tipos'), $c->obtener('repo.catalogo'), $c->obtener('plantillas'), $c->obtener('politica'), $cfg);
        });
        $this->registrar('solicitudes.crear', function ($c) use ($cfg) {
            return new CrearSolicitud(
                $c->obtener('repo.solicitudes'), $c->obtener('repo.tipos'), $c->obtener('repo.detalle'),
                $c->obtener('repo.seguimiento'), $c->obtener('plantillas'), $c->obtener('solicitudes.analizar'),
                $c->obtener('reglas_adjunto'), $c->obtener('politica'), $c->obtener('transaccion'),
                $c->obtener('notificador'), $cfg
            );
        });
        $this->registrar('solicitudes.listar', function ($c) use ($cfg) {
            return new ListarSolicitudes($c->obtener('repo.solicitudes'), $cfg);
        });
        $this->registrar('solicitudes.ver', function ($c) use ($cfg) {
            return new VerSolicitud(
                $c->obtener('repo.solicitudes'), $c->obtener('repo.detalle'), $c->obtener('repo.seguimiento'),
                $c->obtener('repo.catalogo'), $c->obtener('plantillas'), $c->obtener('politica'), $cfg
            );
        });
        $this->registrar('solicitudes.asignar', function ($c) use ($cfg) {
            return new AsignarSolicitud(
                $c->obtener('repo.solicitudes'), $c->obtener('repo.seguimiento'), $c->obtener('repo.catalogo'),
                $c->obtener('politica'), $c->obtener('transaccion'), $c->obtener('notificador'), $cfg
            );
        });
        $this->registrar('solicitudes.estado', function ($c) {
            return new CambiarEstadoSolicitud(
                $c->obtener('repo.solicitudes'), $c->obtener('repo.seguimiento'), $c->obtener('politica'),
                $c->obtener('transaccion'), $c->obtener('notificador')
            );
        });
        $this->registrar('solicitudes.comentar', function ($c) use ($cfg) {
            return new ComentarSolicitud(
                $c->obtener('repo.solicitudes'), $c->obtener('repo.seguimiento'), $c->obtener('reglas_adjunto'),
                $c->obtener('politica'), $c->obtener('transaccion'), $c->obtener('notificador'), $cfg
            );
        });
        $this->registrar('dashboard', function ($c) use ($cfg) {
            return new ObtenerDashboard($c->obtener('repo.dashboard'), $c->obtener('politica'), $cfg);
        });
        $this->registrar('catalogos', function ($c) use ($cfg) {
            return new ObtenerCatalogos($c->obtener('repo.catalogo'), $c->obtener('tipos.listar'), $c->obtener('plantillas'), $cfg);
        });
    }
}

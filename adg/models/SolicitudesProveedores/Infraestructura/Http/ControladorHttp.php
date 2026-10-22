<?php
namespace SolicitudesProveedores\Infraestructura\Http;

use SolicitudesProveedores\Dominio\Excepcion\DominioException;
use SolicitudesProveedores\Infraestructura\Contenedor;

/**
 * Adaptador HTTP: accion -> caso de uso. Respuesta JSON uniforme:
 *   { ok: true,  datos: ... }
 *   { ok: false, mensaje: '...', errores: {...} }   (con codigo HTTP 4xx/5xx)
 */
class ControladorHttp
{
    private $contenedor;

    /** accion recibida en POST => servicio del contenedor */
    private $rutas = array(
        'catalogos'        => 'catalogos',
        'tipos_listar'     => 'tipos.listar',
        'tipos_guardar'    => 'tipos.guardar',
        'tipos_estado'     => 'tipos.estado',
        'analizar_detalle' => 'solicitudes.analizar',
        'crear'            => 'solicitudes.crear',
        'listar'           => 'solicitudes.listar',
        'ver'              => 'solicitudes.ver',
        'asignar'          => 'solicitudes.asignar',
        'cambiar_estado'   => 'solicitudes.estado',
        'comentar'         => 'solicitudes.comentar',
        'dashboard'        => 'dashboard',
    );

    public function __construct(Contenedor $contenedor)
    {
        $this->contenedor = $contenedor;
    }

    public function agregarRuta($accion, $servicio)
    {
        $this->rutas[$accion] = $servicio;
    }

    public function atender(array $entrada, array $sesion, array $servidor)
    {
        try {
            if (!isset($servidor['REQUEST_METHOD']) || $servidor['REQUEST_METHOD'] !== 'POST') {
                throw new DominioException('Metodo no permitido', 405);
            }
            // Mitigacion CSRF: solo peticiones AJAX del mismo sitio (jQuery agrega este encabezado)
            if (!isset($servidor['HTTP_X_REQUESTED_WITH']) || strtolower($servidor['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                throw new DominioException('Peticion no valida', 400);
            }
            $accion = isset($entrada['accion']) ? (string) $entrada['accion'] : '';
            if (!isset($this->rutas[$accion])) {
                throw new DominioException('Accion no soportada: ' . $accion, 404);
            }

            $actor = ActorDesdeSesion::crear($sesion, $this->contenedor->config(), $this->contenedor->obtener('repo.catalogo'));
            $datos = $this->contenedor->obtener($this->rutas[$accion])->ejecutar($actor, $entrada);
            $this->responder(200, array('ok' => true, 'datos' => $datos));
        } catch (DominioException $e) {
            $this->responder($e->getCode() ? $e->getCode() : 400, array(
                'ok'      => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->errores(),
            ));
        } catch (\Exception $e) {
            error_log('[SolicitudesProveedores] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->responder(500, array(
                'ok'      => false,
                'mensaje' => 'Ocurrio un error inesperado. Intente de nuevo o contacte a soporte.',
                'errores' => array(),
            ));
        }
    }

    private function responder($codigo, array $cuerpo)
    {
        // Descarta warnings/notices que hayan quedado en el buffer y romperian el JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            http_response_code(500);
            $json = json_encode(array('ok' => false, 'mensaje' => 'No fue posible codificar la respuesta (revise db_charset en config.php)'));
        }
        echo $json;
    }
}

<?php
namespace SolicitudesProveedores\Dominio\Validacion;

use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;

/**
 * Validador declarativo y encadenable:
 *
 *   $v = new Validador();
 *   $v->campo('asunto', 'Asunto', array(new Reglas\Requerido(), new Reglas\LongitudMaxima(150)))
 *     ->campo('tipo_id', 'Tipo', array(new Reglas\Entero(1)))
 *     ->validar($datos);   // lanza ValidacionException con errores por campo
 */
class Validador
{
    private $campos = array();

    public function campo($nombre, $etiqueta, array $reglas)
    {
        $this->campos[$nombre] = array('etiqueta' => $etiqueta, 'reglas' => $reglas);
        return $this;
    }

    /** @return array errores campo => mensaje (vacio si todo es valido) */
    public function errores(array $datos)
    {
        $errores = array();
        foreach ($this->campos as $nombre => $def) {
            $valor = isset($datos[$nombre]) ? $datos[$nombre] : null;
            foreach ($def['reglas'] as $regla) {
                $mensaje = $regla->validar($valor, $def['etiqueta'], $datos);
                if ($mensaje !== null) {
                    $errores[$nombre] = $mensaje;
                    break;
                }
            }
        }
        return $errores;
    }

    public function validar(array $datos, $mensaje = 'Revise los datos del formulario')
    {
        $errores = $this->errores($datos);
        if (!empty($errores)) {
            throw new ValidacionException($mensaje, $errores);
        }
    }
}

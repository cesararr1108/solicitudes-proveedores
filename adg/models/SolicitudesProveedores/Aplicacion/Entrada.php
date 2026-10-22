<?php
namespace SolicitudesProveedores\Aplicacion;

use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;

/** Lectura segura de la entrada de un caso de uso. */
class Entrada
{
    public static function texto(array $e, $clave, $defecto = '')
    {
        return isset($e[$clave]) && !is_array($e[$clave]) ? trim((string) $e[$clave]) : $defecto;
    }

    public static function entero(array $e, $clave, $defecto = 0)
    {
        return isset($e[$clave]) && is_numeric($e[$clave]) ? (int) $e[$clave] : $defecto;
    }

    public static function booleano(array $e, $clave)
    {
        if (!isset($e[$clave])) {
            return false;
        }
        $v = $e[$clave];
        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on';
    }

    /** Acepta arreglo o cadena JSON (recomendado: evita el limite max_input_vars). */
    public static function lista(array $e, $clave)
    {
        if (!isset($e[$clave]) || $e[$clave] === '') {
            return array();
        }
        if (is_array($e[$clave])) {
            return $e[$clave];
        }
        $datos = json_decode($e[$clave], true);
        if (!is_array($datos)) {
            throw new ValidacionException('El campo ' . $clave . ' no tiene un formato valido');
        }
        return $datos;
    }

    public static function objeto(array $e, $clave)
    {
        $d = self::lista($e, $clave);
        return empty($d) ? null : $d;
    }

    public static function fecha(array $e, $clave, $defecto = '')
    {
        $v = self::texto($e, $clave);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $defecto;
    }
}

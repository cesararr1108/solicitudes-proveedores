<?php
namespace SolicitudesProveedores\Dominio;

use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;

/**
 * Maquina de estados de la solicitud.
 * Para agregar un estado: constante + etiqueta + transiciones (+ CHECK en la tabla).
 */
class EstadoSolicitud
{
    const PENDIENTE  = 'PENDIENTE';
    const EN_CURSO   = 'EN_CURSO';
    const FINALIZADO = 'FINALIZADO';

    private static $etiquetas = array(
        'PENDIENTE'  => 'Pendiente',
        'EN_CURSO'   => 'En curso',
        'FINALIZADO' => 'Finalizado',
    );

    private static $transiciones = array(
        'PENDIENTE'  => array('EN_CURSO'),
        'EN_CURSO'   => array('FINALIZADO'),
        'FINALIZADO' => array(),
    );

    public static function todos()
    {
        return array_keys(self::$etiquetas);
    }

    public static function abiertos()
    {
        return array(self::PENDIENTE, self::EN_CURSO);
    }

    public static function existe($estado)
    {
        return isset(self::$etiquetas[$estado]);
    }

    public static function etiqueta($estado)
    {
        return isset(self::$etiquetas[$estado]) ? self::$etiquetas[$estado] : $estado;
    }

    public static function siguientes($estado)
    {
        return isset(self::$transiciones[$estado]) ? self::$transiciones[$estado] : array();
    }

    public static function asegurarTransicion($desde, $hacia)
    {
        if (!in_array($hacia, self::siguientes($desde), true)) {
            throw new ValidacionException(
                'No se puede pasar de "' . self::etiqueta($desde) . '" a "' . self::etiqueta($hacia) . '"',
                array('estado' => 'Transicion no permitida')
            );
        }
    }

    public static function catalogo()
    {
        $lista = array();
        foreach (self::$etiquetas as $codigo => $etiqueta) {
            $lista[] = array('codigo' => $codigo, 'etiqueta' => $etiqueta, 'siguientes' => self::siguientes($codigo));
        }
        return $lista;
    }
}

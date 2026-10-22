<?php
namespace SolicitudesProveedores\Dominio\Plantilla;

use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;

/** Excel: COD_MATERIAL_REGULAR | CANTIDAD_REGULAR | COD_MATERIAL_BONIFICADO | CANTIDAD_BONIFICADA */
class PlantillaBonificados extends PlantillaExcelBase
{
    public function codigo() { return 'BONIFICADOS'; }
    public function nombre() { return 'Bonificados'; }

    public function columnas()
    {
        return array(
            array('clave' => 'codigo_regular',      'titulo' => 'CODIGO_MATERIAL_REGULAR',    'tipo' => 'texto',  'ejemplo' => '100245'),
            array('clave' => 'cantidad_regular',    'titulo' => 'CANTIDAD_REGULAR',           'tipo' => 'entero', 'ejemplo' => '12'),
            array('clave' => 'codigo_bonificado',   'titulo' => 'CODIGO_MATERIAL_BONIFICADO', 'tipo' => 'texto',  'ejemplo' => '100245'),
            array('clave' => 'cantidad_bonificada', 'titulo' => 'CANTIDAD_BONIFICADA',        'tipo' => 'entero', 'ejemplo' => '1'),
        );
    }

    public function analizar(array $filas, CatalogoRepositorio $catalogo, $codigoSapLaboratorio, $maxFilas)
    {
        $errores = array();
        $avisos  = array();
        $claves  = array('codigo_regular', 'cantidad_regular', 'codigo_bonificado', 'cantidad_bonificada');
        $limpias = array();
        foreach ($filas as $i => $f) {
            if (!$this->filaVacia($f, $claves)) {
                $limpias[] = array(
                    'fila'                => $this->filaNumero($f, $i),
                    'codigo_regular'      => $this->normalizarCodigo(isset($f['codigo_regular']) ? $f['codigo_regular'] : ''),
                    'cantidad_regular'    => isset($f['cantidad_regular']) ? $f['cantidad_regular'] : null,
                    'codigo_bonificado'   => $this->normalizarCodigo(isset($f['codigo_bonificado']) ? $f['codigo_bonificado'] : ''),
                    'cantidad_bonificada' => isset($f['cantidad_bonificada']) ? $f['cantidad_bonificada'] : null,
                );
            }
        }
        if (!$this->validarCantidadFilas($limpias, $maxFilas, $errores)) {
            return array('filas' => array(), 'errores' => $errores, 'advertencias' => $avisos);
        }

        $codigos = array();
        foreach ($limpias as $f) {
            $codigos[] = $f['codigo_regular'];
            $codigos[] = $f['codigo_bonificado'];
        }
        $mapa   = $this->materiales($codigos, $catalogo, $codigoSapLaboratorio);
        $vistos = array();
        $salida = array();

        foreach ($limpias as $f) {
            $n   = $f['fila'];
            $reg = $this->validarMaterial($f['codigo_regular'], $mapa, $n, 'codigo_regular', 'CODIGO_MATERIAL_REGULAR', $errores);
            $bon = $this->validarMaterial($f['codigo_bonificado'], $mapa, $n, 'codigo_bonificado', 'CODIGO_MATERIAL_BONIFICADO', $errores);
            $cr  = $this->numero($f['cantidad_regular']);
            $cb  = $this->numero($f['cantidad_bonificada']);

            if (!$this->esEntero($cr) || $cr <= 0) {
                $this->error($errores, $n, 'cantidad_regular', 'CANTIDAD_REGULAR debe ser un entero mayor que 0');
            }
            if (!$this->esEntero($cb) || $cb <= 0) {
                $this->error($errores, $n, 'cantidad_bonificada', 'CANTIDAD_BONIFICADA debe ser un entero mayor que 0');
            } elseif ($this->esEntero($cr) && $cb > $cr) {
                $this->error($avisos, $n, 'cantidad_bonificada', 'La cantidad bonificada es mayor que la regular');
            }

            if ($reg !== null && $bon !== null) {
                $llave = $reg['CODIGO_MATERIAL'] . '|' . $bon['CODIGO_MATERIAL'];
                if (isset($vistos[$llave])) {
                    $this->error($errores, $n, 'codigo_regular', 'Combinacion repetida (ya esta en la fila ' . $vistos[$llave] . ')');
                }
                $vistos[$llave] = $n;
            }

            $salida[] = array(
                'fila'                     => $n,
                'codigo_regular'           => $reg !== null ? $reg['CODIGO_MATERIAL'] : $f['codigo_regular'],
                'descripcion_regular'      => $reg !== null ? $reg['DESCRIPCION'] : '',
                'cantidad_regular'         => $cr === null ? null : (int) $cr,
                'codigo_bonificado'        => $bon !== null ? $bon['CODIGO_MATERIAL'] : $f['codigo_bonificado'],
                'descripcion_bonificado'   => $bon !== null ? $bon['DESCRIPCION'] : '',
                'cantidad_bonificada'      => $cb === null ? null : (int) $cb,
            );
        }
        return array('filas' => $salida, 'errores' => $errores, 'advertencias' => $avisos);
    }

    public function guardar(DetalleRepositorio $repo, $solicitudId, array $filas)
    {
        $repo->guardarBonificados($solicitudId, $filas);
    }

    public function obtener(DetalleRepositorio $repo, $solicitudId)
    {
        return $repo->obtenerBonificados($solicitudId);
    }
}

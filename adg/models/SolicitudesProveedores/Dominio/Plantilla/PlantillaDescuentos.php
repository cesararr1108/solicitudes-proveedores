<?php
namespace SolicitudesProveedores\Dominio\Plantilla;

use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;
use SolicitudesProveedores\Dominio\Puerto\DetalleRepositorio;

/** Excel: CODIGO_MATERIAL | DESCUENTO (%) */
class PlantillaDescuentos extends PlantillaExcelBase
{
    public function codigo() { return 'DESCUENTOS'; }
    public function nombre() { return 'Descuentos'; }

    public function columnas()
    {
        return array(
            array('clave' => 'codigo_material', 'titulo' => 'CODIGO_MATERIAL', 'tipo' => 'texto',   'ejemplo' => '100245'),
            array('clave' => 'descuento',       'titulo' => 'DESCUENTO',       'tipo' => 'decimal', 'ejemplo' => '10'),
        );
    }

    public function analizar(array $filas, CatalogoRepositorio $catalogo, $codigoSapLaboratorio, $maxFilas)
    {
        $errores = array();
        $avisos  = array();
        $claves  = array('codigo_material', 'descuento');
        $limpias = array();
        foreach ($filas as $i => $f) {
            if (!$this->filaVacia($f, $claves)) {
                $limpias[] = array(
                    'fila'            => $this->filaNumero($f, $i),
                    'codigo_material' => $this->normalizarCodigo(isset($f['codigo_material']) ? $f['codigo_material'] : ''),
                    'descuento'       => isset($f['descuento']) ? $f['descuento'] : null,
                );
            }
        }
        if (!$this->validarCantidadFilas($limpias, $maxFilas, $errores)) {
            return array('filas' => array(), 'errores' => $errores, 'advertencias' => $avisos);
        }

        $mapa   = $this->materiales(array_map(function ($f) { return $f['codigo_material']; }, $limpias), $catalogo, $codigoSapLaboratorio);
        $vistos = array();
        $salida = array();

        foreach ($limpias as $f) {
            $n   = $f['fila'];
            $mat = $this->validarMaterial($f['codigo_material'], $mapa, $n, 'codigo_material', 'CODIGO_MATERIAL', $errores);
            $dto = $this->numero($f['descuento']);

            if ($dto === null) {
                $this->error($errores, $n, 'descuento', 'DESCUENTO debe ser numerico');
            } elseif ($dto <= 0 || $dto > 100) {
                $this->error($errores, $n, 'descuento', 'DESCUENTO debe estar entre 0 y 100');
            } elseif ($dto < 1) {
                $this->error($avisos, $n, 'descuento', 'Descuento de ' . $dto . '%. Si quiso decir ' . ($dto * 100) . '%, corrija el archivo');
            }

            if ($mat !== null) {
                $codigo = $mat['CODIGO_MATERIAL'];
                if (isset($vistos[$codigo])) {
                    $this->error($errores, $n, 'codigo_material', 'Material repetido (ya esta en la fila ' . $vistos[$codigo] . ')');
                }
                $vistos[$codigo] = $n;
            }

            $salida[] = array(
                'fila'            => $n,
                'codigo_material' => $mat !== null ? $mat['CODIGO_MATERIAL'] : $f['codigo_material'],
                'descripcion'     => $mat !== null ? $mat['DESCRIPCION'] : '',
                'descuento'       => $dto === null ? null : round($dto, 2),
            );
        }
        return array('filas' => $salida, 'errores' => $errores, 'advertencias' => $avisos);
    }

    public function guardar(DetalleRepositorio $repo, $solicitudId, array $filas)
    {
        $repo->guardarDescuentos($solicitudId, $filas);
    }

    public function obtener(DetalleRepositorio $repo, $solicitudId)
    {
        return $repo->obtenerDescuentos($solicitudId);
    }
}

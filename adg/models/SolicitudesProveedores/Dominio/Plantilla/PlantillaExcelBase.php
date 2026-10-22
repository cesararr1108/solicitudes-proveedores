<?php
namespace SolicitudesProveedores\Dominio\Plantilla;

use SolicitudesProveedores\Dominio\Puerto\CatalogoRepositorio;

/**
 * Utilidades comunes a plantillas que llegan desde Excel.
 */
abstract class PlantillaExcelBase implements PlantillaDetalle
{
    const MAX_ERRORES = 200;

    protected $filtraLaboratorio = false;

    public function requiereExcel()
    {
        return true;
    }

    /** Quita espacios y caracteres invisibles; SAP guarda los codigos en mayuscula. */
    protected function normalizarCodigo($valor)
    {
        $valor = preg_replace('/\s+/', '', (string) $valor);
        return strtoupper($valor);
    }

    /** Acepta "10", "10,5", "10.5", "10%", "1.234,50". Retorna float o null. */
    protected function numero($valor)
    {
        if ($valor === null) {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $txt = str_replace(array(' ', '%', '$'), '', trim((string) $valor));
        if ($txt === '') {
            return null;
        }
        if (strpos($txt, '.') !== false && strpos($txt, ',') !== false) {
            $txt = str_replace('.', '', $txt);
        }
        $txt = str_replace(',', '.', $txt);
        return is_numeric($txt) ? (float) $txt : null;
    }

    protected function esEntero($n)
    {
        return $n !== null && floor($n) == $n;
    }

    protected function filaVacia(array $fila, array $claves)
    {
        foreach ($claves as $c) {
            if (isset($fila[$c]) && trim((string) $fila[$c]) !== '') {
                return false;
            }
        }
        return true;
    }

    /** Resuelve todos los codigos en una sola consulta. */
    protected function materiales(array $codigos, CatalogoRepositorio $catalogo, $codigoSapLaboratorio)
    {
        $this->filtraLaboratorio = $codigoSapLaboratorio !== null;
        $codigos = array_values(array_unique(array_filter($codigos, 'strlen')));
        if (empty($codigos)) {
            return array();
        }
        return $catalogo->buscarMateriales($codigos, $codigoSapLaboratorio);
    }

    /**
     * Valida que el codigo exista y este activo.
     * @return array|null material encontrado
     */
    protected function validarMaterial($codigo, array $mapa, $filaNum, $campo, $titulo, array &$errores)
    {
        if ($codigo === '') {
            $this->error($errores, $filaNum, $campo, $titulo . ' es obligatorio');
            return null;
        }
        if (!isset($mapa[$codigo])) {
            $this->error($errores, $filaNum, $campo, $this->filtraLaboratorio
                ? 'El material ' . $codigo . ' no existe o no pertenece a su laboratorio'
                : 'El material ' . $codigo . ' no existe en el maestro de materiales');
            return null;
        }
        $m = $mapa[$codigo];
        if ((int) $m['ANULADA'] === 1) {
            $this->error($errores, $filaNum, $campo, 'El material ' . $codigo . ' esta anulado');
            return null;
        }
        return $m;
    }

    protected function error(array &$lista, $fila, $campo, $mensaje)
    {
        if (count($lista) < self::MAX_ERRORES) {
            $lista[] = array('fila' => $fila, 'campo' => $campo, 'mensaje' => $mensaje);
        }
    }

    protected function filaNumero(array $fila, $indice)
    {
        return isset($fila['fila']) ? (int) $fila['fila'] : $indice + 2;
    }

    protected function validarCantidadFilas(array $filas, $maxFilas, array &$errores)
    {
        if (count($filas) === 0) {
            $this->error($errores, 0, 'archivo', 'El archivo no tiene filas con datos');
            return false;
        }
        if (count($filas) > $maxFilas) {
            $this->error($errores, 0, 'archivo', 'El archivo supera el maximo de ' . $maxFilas . ' filas');
            return false;
        }
        return true;
    }
}

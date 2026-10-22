<?php
namespace SolicitudesProveedores\Infraestructura\Persistencia;

/**
 * Adaptador sobre las funciones mssql_* (PHP 5.4).
 * - Parametros con nombre (:nombre) escapados segun su tipo PHP: int/float sin comillas,
 *   string con comillas y '' , null -> NULL, array -> lista para IN (...).
 * - Convierte el texto entre UTF-8 (aplicacion) y el charset de la BD.
 */
class ConexionMssql
{
    private $link;
    private $charsetBd;
    private $nivelTransaccion = 0;

    /**
     * @param resource|null $link lo que retorne conectar() (si no retorna nada se usa la ultima conexion)
     * @param string        $charsetBd 'ISO-8859-1' o 'UTF-8'
     */
    public function __construct($link, $charsetBd = 'ISO-8859-1')
    {
        $this->link      = is_resource($link) ? $link : null;
        $this->charsetBd = strtoupper($charsetBd);

        // Evita que los campos varchar(max)/text lleguen truncados a 4096
        @ini_set('mssql.textlimit', '2147483647');
        @ini_set('mssql.textsize', '2147483647');
        $this->ejecutar('SET TEXTSIZE 2147483647');
    }

    /** @return array filas asociativas */
    public function consultar($sql, array $params = array())
    {
        $res   = $this->query($this->preparar($sql, $params));
        $filas = array();
        if (is_resource($res)) {
            while ($f = mssql_fetch_assoc($res)) {
                $filas[] = $this->filaAUtf8($f);
            }
            mssql_free_result($res);
        }
        return $filas;
    }

    /** @return array|null */
    public function fila($sql, array $params = array())
    {
        $filas = $this->consultar($sql, $params);
        return empty($filas) ? null : $filas[0];
    }

    /** @return mixed primer valor de la primera fila, o null */
    public function valor($sql, array $params = array())
    {
        $f = $this->fila($sql, $params);
        return $f === null ? null : reset($f);
    }

    public function ejecutar($sql, array $params = array())
    {
        $res = $this->query($this->preparar($sql, $params));
        if (is_resource($res)) {
            mssql_free_result($res);
        }
        return true;
    }

    /** Ejecuta un INSERT y retorna el IDENTITY generado. */
    public function insertar($sql, array $params = array())
    {
        $sql = 'SET NOCOUNT ON; ' . $this->preparar($sql, $params) . '; SELECT CAST(SCOPE_IDENTITY() AS INT) AS ID;';
        $res = $this->query($sql);
        $id  = 0;
        if (is_resource($res)) {
            $f  = mssql_fetch_assoc($res);
            $id = $f ? (int) $f['ID'] : 0;
            mssql_free_result($res);
        }
        return $id;
    }

    public function iniciar()
    {
        if ($this->nivelTransaccion === 0) {
            $this->ejecutar('BEGIN TRANSACTION');
        }
        $this->nivelTransaccion++;
    }

    public function confirmar()
    {
        $this->nivelTransaccion--;
        if ($this->nivelTransaccion === 0) {
            $this->ejecutar('COMMIT TRANSACTION');
        }
    }

    public function revertir()
    {
        if ($this->nivelTransaccion > 0) {
            $this->nivelTransaccion = 0;
            $this->ejecutar('IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION');
        }
    }

    /** Escapa un valor para usarlo dentro de SQL. Publico para casos puntuales. */
    public function escapar($valor)
    {
        if ($valor === null) {
            return 'NULL';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            return str_replace(',', '.', (string) $valor);
        }
        if (is_array($valor)) {
            if (empty($valor)) {
                return 'NULL';
            }
            $partes = array();
            foreach ($valor as $v) {
                $partes[] = $this->escapar($v);
            }
            return implode(', ', $partes);
        }
        $texto = str_replace("\0", '', $this->aBd((string) $valor));
        return "'" . str_replace("'", "''", $texto) . "'";
    }

    /** Escapa los comodines de LIKE y envuelve en %...%. */
    public function like($texto)
    {
        return '%' . str_replace(array('[', '%', '_'), array('[[]', '[%]', '[_]'), $texto) . '%';
    }

    private function preparar($sql, array $params)
    {
        if (empty($params)) {
            return $sql;
        }
        $self = $this;
        return preg_replace_callback('/(?<![:\w]):([a-zA-Z_][a-zA-Z0-9_]*)/', function ($m) use ($params, $self) {
            if (!array_key_exists($m[1], $params)) {
                throw new \RuntimeException('Parametro SQL sin valor: ' . $m[1]);
            }
            return $self->escapar($params[$m[1]]);
        }, $sql);
    }

    private function query($sql)
    {
        $res = $this->link ? @mssql_query($sql, $this->link) : @mssql_query($sql);
        if ($res === false) {
            $msg = function_exists('mssql_get_last_message') ? mssql_get_last_message() : '';
            throw new \RuntimeException('Error SQL: ' . $this->aUtf8($msg) . ' | ' . substr($sql, 0, 500));
        }
        return $res;
    }

    private function filaAUtf8(array $fila)
    {
        if ($this->charsetBd === 'UTF-8') {
            return $fila;
        }
        foreach ($fila as $k => $v) {
            if (is_string($v)) {
                $fila[$k] = $this->aUtf8($v);
            }
        }
        return $fila;
    }

    private function aUtf8($texto)
    {
        if ($this->charsetBd === 'UTF-8' || $texto === '') {
            return $texto;
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        return utf8_encode($texto);
    }

    private function aBd($texto)
    {
        if ($this->charsetBd === 'UTF-8' || $texto === '') {
            return $texto;
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        }
        return utf8_decode($texto);
    }
}

<?php
// Ejecutar desde consola:  php adg/tests/SolicitudesProveedores/DominioTest.php
// Pruebas de dominio/aplicacion con dobles en memoria (sin SQL Server)
error_reporting(E_ALL);
$GLOBALS['SQL'] = array();
function mssql_query($sql, $link = null) { $GLOBALS['SQL'][] = $sql; return true; }
function mssql_get_last_message() { return ''; }

require dirname(__FILE__) . '/../../models/SolicitudesProveedores/bootstrap.php';
use SolicitudesProveedores\Dominio as D;
use SolicitudesProveedores\Infraestructura\Persistencia\ConexionMssql;

$fallos = 0;
function ok($cond, $msg) { global $fallos; echo ($cond ? "  OK   " : "  FAIL ") . $msg . "\n"; if (!$cond) $fallos++; }
function lanza($fn, $clase) { try { $fn(); return false; } catch (Exception $e) { return $e instanceof $clase; } }

$config = require dirname(__FILE__) . '/../../models/SolicitudesProveedores/config.php';
$roles = $config['roles'];
$prov  = new D\Actor(array('id' => 50, 'rol_id' => 9, 'codigo_sap' => 777, 'organizacion' => '2000'), $roles);
$prov2 = new D\Actor(array('id' => 51, 'rol_id' => 9, 'codigo_sap' => 888, 'organizacion' => '2000'), $roles);
$ger   = new D\Actor(array('id' => 10, 'rol_id' => 118, 'organizacion' => '2000'), $roles);
$aux   = new D\Actor(array('id' => 11, 'rol_id' => 6, 'organizacion' => '2000'), $roles);
$aux2  = new D\Actor(array('id' => 12, 'rol_id' => 6, 'organizacion' => '2000'), $roles);
$auxO  = new D\Actor(array('id' => 13, 'rol_id' => 6, 'organizacion' => '1000'), $roles);
$adm   = new D\Actor(array('id' => 1, 'rol_id' => 1), $roles);

echo "== Escapado SQL\n";
$db = new ConexionMssql(null, 'UTF-8');
ok($db->escapar("O'Brien") === "'O''Brien'", 'comillas simples duplicadas');
ok($db->escapar(null) === 'NULL', 'null -> NULL');
ok($db->escapar(5) === '5' && $db->escapar(true) === '1', 'int y bool');
ok($db->escapar(array('A', 1)) === "'A', 1", 'arreglo -> lista IN');
ok($db->like('50%_[x]') === '%50[%][_][[]x]%', 'comodines LIKE escapados');
$GLOBALS['SQL'] = array();
$db->ejecutar('SELECT * FROM X WHERE A = :a AND B IN (:b) AND C = :a', array('a' => "x'; DROP TABLE T--", 'b' => array(1, 2)));
ok(end($GLOBALS['SQL']) === "SELECT * FROM X WHERE A = 'x''; DROP TABLE T--' AND B IN (1, 2) AND C = 'x''; DROP TABLE T--'", 'parametros con nombre: ' . end($GLOBALS['SQL']));
ok(lanza(function () use ($db) { $db->ejecutar('SELECT :falta', array('otro' => 1)); }, 'RuntimeException'), 'parametro faltante lanza error');
$latin = new ConexionMssql(null, 'ISO-8859-1');
ok($latin->escapar('Año') === "'" . mb_convert_encoding('Año', 'Windows-1252', 'UTF-8') . "'", 'convierte UTF-8 -> charset BD');

echo "== Maquina de estados\n";
ok(D\EstadoSolicitud::siguientes('PENDIENTE') === array('EN_CURSO'), 'PENDIENTE -> EN_CURSO');
ok(lanza(function () { D\EstadoSolicitud::asegurarTransicion('PENDIENTE', 'FINALIZADO'); }, 'SolicitudesProveedores\Dominio\Excepcion\ValidacionException'), 'no salta de PENDIENTE a FINALIZADO');
ok(lanza(function () { D\EstadoSolicitud::asegurarTransicion('FINALIZADO', 'EN_CURSO'); }, 'SolicitudesProveedores\Dominio\Excepcion\ValidacionException'), 'no reabre');

echo "== Politica de acceso\n";
$pol = new D\PoliticaAcceso($config);
$fila = array('ID' => 1, 'TIPO_ID' => 1, 'ASUNTO' => 'x', 'ESTADO' => 'PENDIENTE', 'CODIGO_SAP_PROVEEDOR' => 777, 'USUARIO_ID' => 50,
              'ORGANIZACION_VENTA' => '2000', 'OFICINA_VENTAS' => '', 'ASIGNADO_A' => null, 'PROCESO_ID' => 3);
$s = D\Solicitud::desdeFila($fila);
ok($pol->puedeVer($prov, $s) && !$pol->puedeVer($prov2, $s), 'proveedor solo ve su laboratorio');
ok($pol->puedeVer($aux, $s) && !$pol->puedeVer($auxO, $s), 'gestor filtra por organizacion');
ok($pol->puedeVer($adm, $s), 'administrador ve todo');
ok($pol->puedeAsignar($aux, $s, 11), 'auxiliar se puede tomar una sin asignar');
ok(!$pol->puedeAsignar($aux, $s, 12), 'auxiliar NO asigna a otro');
ok($pol->puedeAsignar($ger, $s, 12), 'gerente asigna a cualquiera');
ok(!$pol->puedeAsignar($prov, $s, 50), 'proveedor no asigna');
$s->asignar(12);
ok(!$pol->puedeAsignar($aux, $s, 11), 'auxiliar NO toma una ya asignada a otro');
ok(!$pol->puedeCambiarEstado($aux, $s) && $pol->puedeCambiarEstado($aux2, $s), 'solo el asignado (auxiliar) cambia estado');
ok($pol->puedeCambiarEstado($ger, $s), 'gerente cambia estado de cualquiera');
ok($pol->puedeComentar($prov, $s) && !$pol->puedeComentar($prov2, $s), 'comentarios del proveedor');
$p = $pol->permisos($aux2, $s);
ok($p['cambiar_estado'] && $p['estados_destino'] === array('EN_CURSO') && !$p['asignar_a_otros'] && !$p['tomar'], 'permisos UI auxiliar asignado');
ok(D\Alcance::para($prov, $config) === array('codigo_sap_proveedor' => 777), 'alcance proveedor');
ok(D\Alcance::para($ger, $config) === array('organizacion' => '2000'), 'alcance gestor');

echo "== Plantillas Excel\n";
class CatalogoFalso implements D\Puerto\CatalogoRepositorio {
    public $mat = array(
        '000000000000100245' => array('CODIGO_MATERIAL' => '000000000000100245', 'DESCRIPCION' => 'ACETAMINOFEN 500MG', 'ANULADA' => 0, 'BLOQUEO_VENTA' => 0),
        'ABC1' => array('CODIGO_MATERIAL' => 'ABC1', 'DESCRIPCION' => 'GEL 30G', 'ANULADA' => 0, 'BLOQUEO_VENTA' => 0),
        'ANU' => array('CODIGO_MATERIAL' => 'ANU', 'DESCRIPCION' => 'ANULADO', 'ANULADA' => 1, 'BLOQUEO_VENTA' => 0),
    );
    public function buscarMateriales(array $codigos, $lab = null) {
        $r = array();
        foreach ($codigos as $c) {
            $p = ctype_digit($c) ? str_pad($c, 18, '0', STR_PAD_LEFT) : $c;
            if (isset($this->mat[$c])) $r[$c] = $this->mat[$c]; elseif (isset($this->mat[$p])) $r[$c] = $this->mat[$p];
        }
        return $r;
    }
    public function listarProcesos() { return array(); }
    public function obtenerProceso($id) { return null; }
    public function listarProveedoresConSolicitudes(array $a) { return array(); }
    public function nombreProveedor($c) { return ''; }
    public function listarGestores(array $r, $o = '') { return array(); }
    public function obtenerUsuario($id) { return null; }
    public function procesoDeRol($r) { return 0; }
}
$cat = new CatalogoFalso();
$reg = D\Plantilla\RegistroPlantillas::predeterminado();
$r = $reg->obtener('DESCUENTOS')->analizar(array(
    array('fila' => 2, 'codigo_material' => ' 100245 ', 'descuento' => '10%'),
    array('fila' => 3, 'codigo_material' => 'abc1', 'descuento' => '12,5'),
    array('fila' => 4, 'codigo_material' => '100245', 'descuento' => 5),
    array('fila' => 5, 'codigo_material' => 'NOEXISTE', 'descuento' => 150),
    array('fila' => 6, 'codigo_material' => 'ANU', 'descuento' => 0.5),
    array('fila' => 7, 'codigo_material' => '', 'descuento' => ''),
), $cat, null, 3000);
ok(count($r['filas']) === 5, 'ignora filas vacias');
ok($r['filas'][0]['codigo_material'] === '000000000000100245' && $r['filas'][0]['descuento'] == 10 && $r['filas'][0]['descripcion'] === 'ACETAMINOFEN 500MG', 'resuelve ceros a la izquierda, "10%" y descripcion');
ok($r['filas'][1]['descuento'] == 12.5 && $r['filas'][1]['codigo_material'] === 'ABC1', 'coma decimal y mayusculas');
$porFila = array(); foreach ($r['errores'] as $e) { $porFila[$e['fila']][] = $e['mensaje']; }
ok(isset($porFila[4]) && strpos($porFila[4][0], 'repetido') !== false, 'detecta material repetido');
ok(isset($porFila[5]) && count($porFila[5]) === 2, 'material inexistente + descuento fuera de rango');
ok(isset($porFila[6]) && strpos($porFila[6][0], 'anulado') !== false, 'material anulado');
ok(count($r['advertencias']) === 1 && $r['advertencias'][0]['fila'] === 6, 'advierte descuento < 1%');

$r = $reg->obtener('BONIFICADOS')->analizar(array(
    array('fila' => 2, 'codigo_regular' => '100245', 'cantidad_regular' => '12', 'codigo_bonificado' => 'ABC1', 'cantidad_bonificada' => '1'),
    array('fila' => 3, 'codigo_regular' => '100245', 'cantidad_regular' => '1.5', 'codigo_bonificado' => 'ABC1', 'cantidad_bonificada' => '0'),
    array('fila' => 4, 'codigo_regular' => 'ABC1', 'cantidad_regular' => 2, 'codigo_bonificado' => 'ABC1', 'cantidad_bonificada' => 5),
), $cat, null, 3000);
$porFila = array(); foreach ($r['errores'] as $e) { $porFila[$e['fila']][] = $e['campo']; }
ok(!isset($porFila[2]), 'fila valida de bonificados');
ok(isset($porFila[3]) && in_array('cantidad_regular', $porFila[3]) && in_array('cantidad_bonificada', $porFila[3]) && in_array('codigo_regular', $porFila[3]), 'cantidades no enteras/cero y combinacion repetida');
ok(count($r['advertencias']) === 1, 'advierte bonificado > regular');
ok($r['filas'][0]['descripcion_bonificado'] === 'GEL 30G', 'descripcion del bonificado');
$r = $reg->obtener('DESCUENTOS')->analizar(array(), $cat, null, 3000);
ok(count($r['errores']) === 1 && $r['errores'][0]['fila'] === 0, 'archivo vacio');

echo "== Validador\n";
$v = new D\Validacion\Validador();
$v->campo('asunto', 'Asunto', array(new D\Validacion\Reglas\Requerido(), new D\Validacion\Reglas\LongitudMaxima(5)))
  ->campo('n', 'N', array(new D\Validacion\Reglas\Entero(1, 3)));
$e = $v->errores(array('asunto' => 'demasiado largo', 'n' => '7'));
ok(isset($e['asunto']) && isset($e['n']), 'reglas encadenadas: ' . json_encode($e));
ok($v->errores(array('asunto' => 'ok', 'n' => 2)) === array(), 'datos validos');

echo "== Adjuntos\n";
$ra = new D\ReglasAdjunto($config['adjuntos']);
$base = 'https://app.pwmultiroma.com/adg/archivos/solicitudes_proveedores/';
ok(count($ra->normalizar(array(array('nombre' => 'a.PDF', 'ruta' => '/adg/archivos/solicitudes_proveedores/sp_1.pdf', 'url' => $base . 'sp_1.pdf', 'tamano_kb' => 10)), array('pdf'))) === 1, 'adjunto valido');
ok(lanza(function () use ($ra, $base) { $ra->normalizar(array(array('nombre' => 'a.exe', 'ruta' => '/adg/archivos/solicitudes_proveedores/x.exe', 'url' => $base . 'x.exe')), array('pdf')); }, 'Exception'), 'rechaza extension');
ok(lanza(function () use ($ra) { $ra->normalizar(array(array('nombre' => 'a.pdf', 'ruta' => '/otro/x.pdf', 'url' => 'https://evil.com/x.pdf')), array('pdf')); }, 'Exception'), 'rechaza URL externa');
ok(lanza(function () use ($ra, $base) { $ra->normalizar(array(array('nombre' => 'a.pdf', 'ruta' => '/adg/archivos/solicitudes_proveedores/../x.pdf', 'url' => $base . '../x.pdf')), array('pdf')); }, 'Exception'), 'rechaza ..');

echo $fallos ? "\n$fallos FALLOS\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);

<?php
/**
 * Layout comun de las vistas de Solicitudes de Proveedores.
 * Uso:  sp_render_vista('Titulo', array(roles permitidos), array('sp-xxx.js'), $usaExcel);
 *
 * Funciona de tres formas, sin cambios:
 *   1. Navegando directo a la URL de la vista        -> documento HTML completo.
 *   2. Incluida (include/require) desde otra pagina  -> solo el fragmento del modulo.
 *   3. Cargada por AJAX en un contenedor ($.load)    -> solo el fragmento del modulo.
 * Las rutas son absolutas (calculadas en el servidor), asi no dependen de la URL de la pagina.
 */
if (session_id() === '' && !headers_sent()) {
    session_start();
}

if (!function_exists('sp_render_vista')) {

    /** Carpeta del modulo (adg/) dentro de la carpeta publica del servidor. */
    function sp_dir_app()
    {
        return realpath(dirname(__FILE__) . '/../..');
    }

    function sp_normalizar_ruta($ruta)
    {
        return rtrim(str_replace('\\', '/', $ruta), '/');
    }

    /**
     * URL web de la aplicacion, por ejemplo "/calidad/".
     * Prioridad: config.php (url_app) -> DOCUMENT_ROOT -> URL del script ejecutado.
     */
    function sp_url_app()
    {
        static $url = null;
        if ($url !== null) {
            return $url;
        }
        $config = require dirname(__FILE__) . '/../../models/SolicitudesProveedores/config.php';
        if (!empty($config['url_app'])) {
            return $url = rtrim($config['url_app'], '/') . '/';
        }

        $app  = sp_normalizar_ruta(sp_dir_app());
        $raiz = isset($_SERVER['DOCUMENT_ROOT']) ? sp_normalizar_ruta(realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        if ($raiz !== '' && stripos($app . '/', $raiz . '/') === 0) {
            return $url = substr($app, strlen($raiz)) . '/';
        }

        // Alias/enlaces simbolicos: deducir desde el script ejecutado (SCRIPT_FILENAME <-> SCRIPT_NAME)
        $archivo = isset($_SERVER['SCRIPT_FILENAME']) ? sp_normalizar_ruta(realpath($_SERVER['SCRIPT_FILENAME'])) : '';
        $web     = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        if ($archivo !== '' && stripos($archivo, $app . '/') === 0) {
            $relativo = substr($archivo, strlen($app));                    // /views/x/y.php
            if (substr($web, -strlen($relativo)) === $relativo) {
                return $url = substr($web, 0, -strlen($relativo)) . '/';
            }
        }
        return $url = '../../'; // ultimo recurso: relativo a views/solicitudes-proveedores/
    }

    function sp_asset($ruta)
    {
        $archivo = sp_dir_app() . '/' . $ruta;
        return sp_url_app() . $ruta . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
    }

    /** true si la vista se pide directo por URL (no incluida ni cargada por AJAX). */
    function sp_es_pagina_completa()
    {
        if (isset($_GET['fragmento'])) {
            return false;
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return false;
        }
        $script = isset($_SERVER['SCRIPT_FILENAME']) ? sp_normalizar_ruta(realpath($_SERVER['SCRIPT_FILENAME'])) : '';
        return $script !== '' && strcasecmp(sp_normalizar_ruta(dirname($script)), sp_normalizar_ruta(realpath(dirname(__FILE__)))) === 0;
    }

    function sp_render_vista($titulo, array $rolesPermitidos, array $scripts, $usaExcel)
    {
        $logueado  = !empty($_SESSION['ses_Id']) && !empty($_SESSION['ses_RolesId']);
        $permitido = $logueado && in_array((int) $_SESSION['ses_RolesId'], $rolesPermitidos, true);
        $org       = $logueado && isset($_SESSION['ses_NumOrg']) ? trim($_SESSION['ses_NumOrg']) : '';
        $org       = preg_match('/^\d{4}$/', $org) ? $org : '2000';
        $completa  = sp_es_pagina_completa();
        $base      = sp_url_app();

        // Scripts en orden; "si" indica la variable global que, si ya existe, evita cargarlo de nuevo
        $js = array(
            array('src' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js', 'si' => 'jQuery'),
        );
        if ($usaExcel) {
            $js[] = array('src' => 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js', 'si' => 'XLSX');
        }
        $js[] = array('src' => sp_asset('lib/js/servicios.js'), 'si' => 'enviarPeticion');
        $js[] = array('src' => sp_asset('lib/js/solicitudes-proveedores/sp-core.js'), 'si' => '');
        if ($usaExcel) {
            $js[] = array('src' => sp_asset('lib/js/solicitudes-proveedores/sp-excel.js'), 'si' => '');
            $js[] = array('src' => sp_asset('lib/js/solicitudes-proveedores/sp-detalle.js'), 'si' => '');
        }
        foreach ($scripts as $s) {
            $js[] = array('src' => sp_asset('lib/js/solicitudes-proveedores/' . $s), 'si' => '');
        }

        $spConfig = array(
            'controlador'    => $base . 'controllers/solicitudes-proveedores/SolicitudesProveedoresController.php',
            'servicioSubida' => $base . 'models/ServiceUploadFile.php',
            'vistas'         => $base . 'views/solicitudes-proveedores/',
        );

        if ($completa) { ?>
<!DOCTYPE html>
<html lang="es" data-org="<?php echo htmlspecialchars($org); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($titulo); ?> · ADG</title>
  <link rel="stylesheet" href="<?php echo sp_asset('lib/css/solicitudes-proveedores/solicitudes_proveedores.css'); ?>">
</head>
<body style="margin:0">
<?php } else { ?>
<link rel="stylesheet" href="<?php echo sp_asset('lib/css/solicitudes-proveedores/solicitudes_proveedores.css'); ?>">
<?php } ?>
  <div class="sp-app" data-org="<?php echo htmlspecialchars($org); ?>">
    <main class="sp-container" id="sp-root">
<?php if (!$logueado) { ?>
      <div class="sp-card"><div class="sp-empty"><div class="sp-empty__title">Su sesion ha expirado</div><div>Ingrese nuevamente al ADG.</div></div></div>
<?php } elseif (!$permitido) { ?>
      <div class="sp-card"><div class="sp-empty"><div class="sp-empty__title">Acceso restringido</div><div>Su rol no tiene permiso para esta opcion.</div></div></div>
<?php } else { ?>
      <div class="sp-skeleton" style="height:28px;width:260px;margin-bottom:24px"></div>
      <div class="sp-skeleton" style="height:320px"></div>
<?php } ?>
    </main>
  </div>
<?php if ($permitido) { ?>
  <script>
  window.SP_CONFIG = <?php echo json_encode($spConfig); ?>;
  (function () {
    // Carga secuencial; omite librerias que la pagina contenedora ya tenga (jQuery, servicios.js, ...)
    var lista = <?php echo json_encode($js); ?>;
    function siguiente(i) {
      if (i >= lista.length) { return; }
      if (lista[i].si && typeof window[lista[i].si] !== 'undefined') { siguiente(i + 1); return; }
      var s = document.createElement('script');
      s.src = lista[i].src;
      s.onload = function () { siguiente(i + 1); };
      s.onerror = function () {
        var raiz = document.getElementById('sp-root');
        if (raiz) { raiz.innerHTML = '<div class="sp-card"><div class="sp-empty"><div class="sp-empty__title">No se pudo cargar el modulo</div><div>' + lista[i].src.split('?')[0] + '</div></div></div>'; }
      };
      document.body.appendChild(s);
    }
    siguiente(0);
  })();
  </script>
<?php } ?>
<?php if ($completa) { ?>
</body>
</html>
<?php }
    }
}

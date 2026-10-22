<?php
/**
 * Layout comun de las vistas de Solicitudes de Proveedores.
 * Uso:  sp_render_vista('Titulo', array(roles permitidos), array('sp-xxx.js'), $usaExcel);
 */
if (session_id() === '') {
    session_start();
}

function sp_asset($ruta)
{
    $archivo = dirname(__FILE__) . '/../' . $ruta;
    return '../' . $ruta . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

function sp_render_vista($titulo, array $rolesPermitidos, array $scripts, $usaExcel)
{
    $logueado = !empty($_SESSION['ses_Id']) && !empty($_SESSION['ses_RolesId']);
    $permitido = $logueado && in_array((int) $_SESSION['ses_RolesId'], $rolesPermitidos, true);
    $org = $logueado && isset($_SESSION['ses_NumOrg']) ? trim($_SESSION['ses_NumOrg']) : '';
    $org = preg_match('/^\d{4}$/', $org) ? $org : '2000';
    ?>
<!DOCTYPE html>
<html lang="es" data-org="<?php echo htmlspecialchars($org); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($titulo); ?> · ADG</title>
  <link rel="stylesheet" href="<?php echo sp_asset('lib/css/solicitudes_proveedores.css'); ?>">
</head>
<body style="margin:0">
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
  <!-- Si el ADG ya incluye jQuery en su plantilla, puede quitar esta linea -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<?php if ($usaExcel) { ?>
  <script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<?php } ?>
  <script src="<?php echo sp_asset('lib/js/servicios.js'); ?>"></script>
  <script src="<?php echo sp_asset('lib/js/solicitudes_proveedores/sp-core.js'); ?>"></script>
<?php if ($usaExcel) { ?>
  <script src="<?php echo sp_asset('lib/js/solicitudes_proveedores/sp-excel.js'); ?>"></script>
  <script src="<?php echo sp_asset('lib/js/solicitudes_proveedores/sp-detalle.js'); ?>"></script>
<?php } ?>
<?php foreach ($scripts as $js) { ?>
  <script src="<?php echo sp_asset('lib/js/solicitudes_proveedores/' . $js); ?>"></script>
<?php } ?>
<?php } ?>
</body>
</html>
<?php
}

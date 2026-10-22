/* =====================================================================
   SOLICITUDES DE PROVEEDORES - Vista de mercadeo
   Bandeja (gestion) · Historial · Dashboard
   ===================================================================== */
(function (window, $) {
  'use strict';

  const SP = window.SP;
  const CLAVE_VISTA = 'sp_mercadeo_vista';

  const st = { cat: null, vista: 'bandeja', $root: null, $panel: null };

  const bandeja = { asignacion: 'todas', estado: '', proveedor: '', tipo_id: '', texto: '', pagina: 1 };
  const historial = { estado: 'FINALIZADO', desde: SP.fmt.hoy(-90), hasta: SP.fmt.hoy(), proveedor: '', tipo_id: '', asignado_a: '', texto: '', pagina: 1 };
  const dash = { desde: SP.fmt.hoy(-30), hasta: SP.fmt.hoy(), proveedor: '', tipo_id: '', asignado_a: '' };

  $(function () {
    st.$root = $('#sp-root');
    try { st.vista = window.localStorage.getItem(CLAVE_VISTA) || 'bandeja'; } catch (e) { st.vista = 'bandeja'; }
    SP.api('catalogos').then(function (cat) {
      st.cat = cat;
      if (cat.actor.perfil === 'AUXILIAR') { bandeja.asignacion = 'mias'; }
      pintarShell();
      cambiarVista(st.vista);
      const id = new URLSearchParams(window.location.search).get('id');
      if (id && /^\d+$/.test(id)) { abrirDetalle(+id); }
    }, function (err) {
      st.$root.html(SP.vacio('No fue posible cargar el modulo', err.message, 'alert'));
    });
  });

  function pintarShell() {
    const a = st.cat.actor;
    const perfil = { ADMINISTRADOR: 'Administrador', GERENTE: 'Gerente de mercadeo', AUXILIAR: 'Auxiliar de mercadeo' }[a.perfil] || a.perfil;
    st.$root.html(
      '<header class="sp-header"><div><h1 class="sp-header__title">Solicitudes de proveedores</h1>' +
      '<p class="sp-header__sub"><span class="sp-org-chip">Org. ' + SP.esc(a.organizacion || 'Todas') + '</span><span>' + SP.esc(a.nombre) + ' · ' + SP.esc(perfil) + '</span></p></div>' +
      '<div class="sp-flex">' + (a.perfil === 'ADMINISTRADOR' ? '<a class="sp-btn" href="sp_tipos_solicitud.php">' + SP.icono('settings') + 'Tipos de solicitud</a>' : '') +
      '<button type="button" class="sp-btn" data-refrescar title="Actualizar">' + SP.icono('refresh') + 'Actualizar</button></div></header>' +
      '<nav class="sp-nav" role="tablist">' +
        '<button type="button" class="sp-nav__item" role="tab" data-vista="bandeja">' + SP.icono('inbox') + 'Solicitudes <span class="sp-count" data-conteo-abiertas hidden>0</span></button>' +
        '<button type="button" class="sp-nav__item" role="tab" data-vista="historial">' + SP.icono('history') + 'Historial</button>' +
        '<button type="button" class="sp-nav__item" role="tab" data-vista="dashboard">' + SP.icono('chart') + 'Dashboard</button>' +
      '</nav><div data-panel></div>'
    );
    st.$panel = st.$root.find('[data-panel]');
    st.$root.on('click', '[data-vista]', function () { cambiarVista($(this).data('vista')); });
    st.$root.on('click', '[data-refrescar]', function () { cambiarVista(st.vista); });
  }

  function cambiarVista(vista) {
    st.vista = vista;
    try { window.localStorage.setItem(CLAVE_VISTA, vista); } catch (e) { /* almacenamiento no disponible */ }
    st.$root.find('[data-vista]').attr('aria-selected', 'false').filter('[data-vista="' + vista + '"]').attr('aria-selected', 'true');
    st.$panel.off();
    if (vista === 'historial') { vistaHistorial(); } else if (vista === 'dashboard') { vistaDashboard(); } else { vistaBandeja(); }
  }

  function abrirDetalle(id) {
    SP.detalle.abrir(id, { catalogos: st.cat, alCambiar: function () { refrescarActual(); } });
  }

  function refrescarActual() {
    if (st.vista === 'bandeja') { listarBandeja(); } else if (st.vista === 'historial') { listarHistorial(); } else { cargarDashboard(); }
  }

  /* ------------------------- Filtros comunes ------------------------- */

  function opciones(lista, valor, texto, seleccionado, todos) {
    return '<option value="">' + todos + '</option>' + (lista || []).map(function (x) {
      return '<option value="' + SP.esc(x[valor]) + '"' + (String(x[valor]) === String(seleccionado) ? ' selected' : '') + '>' + SP.esc(x[texto] || x[valor]) + '</option>';
    }).join('');
  }

  function selectProveedor(sel) {
    return '<select class="sp-select" style="width:auto;min-width:200px;max-width:260px" name="proveedor" aria-label="Proveedor">' +
      opciones(st.cat.proveedores, 'CODIGO_SAP', 'NOMBRE', sel, 'Todos los proveedores') + '</select>';
  }
  function selectTipo(sel) {
    return '<select class="sp-select" style="width:auto;min-width:160px" name="tipo_id" aria-label="Tipo">' + opciones(st.cat.tipos, 'ID', 'NOMBRE', sel, 'Todos los tipos') + '</select>';
  }
  function selectGestor(sel) {
    return '<select class="sp-select" style="width:auto;min-width:180px" name="asignado_a" aria-label="Responsable">' + opciones(st.cat.gestores, 'ID', 'NOMBRE', sel, 'Todos los responsables') + '</select>';
  }
  function rangoFechas(f) {
    return '<label class="sp-flex sp-muted">Desde <input type="date" class="sp-input" style="width:auto" name="desde" value="' + f.desde + '"></label>' +
      '<label class="sp-flex sp-muted">Hasta <input type="date" class="sp-input" style="width:auto" name="hasta" value="' + f.hasta + '"></label>';
  }

  /** Enlaza los controles con name=... a un objeto de filtros. */
  function enlazarFiltros($cont, filtros, alCambiar) {
    $cont.on('change', 'select[name], input[type=date][name]', function () {
      filtros[this.name] = $(this).val();
      if ('pagina' in filtros) { filtros.pagina = 1; }
      alCambiar();
    });
    $cont.on('input', 'input[type=search][name]', SP.debounce(function () {
      filtros[this.name] = $.trim($(this).val());
      if ('pagina' in filtros) { filtros.pagina = 1; }
      alCambiar();
    }, 350));
  }

  /* =================================================================
     BANDEJA
     ================================================================= */
  function vistaBandeja() {
    st.$panel.html(
      '<div class="sp-toolbar"><div class="sp-chips" role="group" aria-label="Asignacion">' +
        chipAsig('todas', 'Todas las abiertas', 'ABIERTAS') + chipAsig('sin_asignar', 'Sin asignar', 'SIN_ASIGNAR') + chipAsig('mias', 'Asignadas a mi', 'MIAS') +
      '</div><span style="flex:1"></span><div class="sp-chips" role="group" aria-label="Estado">' +
        chipEstado('', 'Todos') + chipEstado('PENDIENTE', 'Pendientes') + chipEstado('EN_CURSO', 'En curso') +
      '</div></div>' +
      '<div class="sp-toolbar" data-filtros>' +
        '<div class="sp-search sp-toolbar__grow">' + SP.icono('search') + '<input type="search" class="sp-input" name="texto" placeholder="Buscar por #, asunto, proveedor o tipo" value="' + SP.esc(bandeja.texto) + '" aria-label="Buscar"></div>' +
        selectProveedor(bandeja.proveedor) + selectTipo(bandeja.tipo_id) +
      '</div>' +
      '<div class="sp-card"><div class="sp-table-wrap"><table class="sp-table"><thead><tr>' +
        '<th style="width:64px">#</th><th>Proveedor</th><th>Solicitud</th><th>Estado</th><th>Responsable</th><th>Antiguedad</th><th class="sp-hide-sm">Actividad</th><th style="width:1%"></th>' +
      '</tr></thead><tbody data-filas></tbody></table></div><div data-pager></div></div>'
    );

    enlazarFiltros(st.$panel.find('[data-filtros]'), bandeja, listarBandeja);
    st.$panel.on('click', '[data-asig]', function () {
      bandeja.asignacion = $(this).data('asig'); bandeja.pagina = 1;
      st.$panel.find('[data-asig]').attr('aria-pressed', 'false'); $(this).attr('aria-pressed', 'true');
      listarBandeja();
    });
    st.$panel.on('click', '[data-est]', function () {
      bandeja.estado = $(this).data('est'); bandeja.pagina = 1;
      st.$panel.find('[data-est]').attr('aria-pressed', 'false'); $(this).attr('aria-pressed', 'true');
      listarBandeja();
    });
    st.$panel.on('click', '[data-pagina]', function () { bandeja.pagina = +$(this).data('pagina'); listarBandeja(); });
    st.$panel.on('click', '[data-tomar]', function (e) {
      e.stopPropagation();
      const $b = $(this);
      SP.boton.cargando($b, '');
      SP.api('asignar', { id: $b.data('tomar') }).then(function () {
        SP.toast('Solicitud #' + $b.data('tomar') + ' asignada a usted', 'ok');
        listarBandeja();
      }, function (err) { SP.boton.restaurar($b); SP.toast(err.message, 'error'); });
    });
    st.$panel.on('click keydown', 'tr[data-id]', function (e) {
      if (e.type === 'keydown' && e.key !== 'Enter') { return; }
      abrirDetalle(+$(this).data('id'));
    });
    listarBandeja();
  }

  function chipAsig(valor, texto, conteo) {
    return '<button type="button" class="sp-chip" data-asig="' + valor + '" aria-pressed="' + (bandeja.asignacion === valor) + '">' + texto +
      ' <span class="sp-count" data-c="' + conteo + '">·</span></button>';
  }
  function chipEstado(valor, texto) {
    return '<button type="button" class="sp-chip" data-est="' + valor + '" aria-pressed="' + (bandeja.estado === valor) + '">' + texto + '</button>';
  }

  function listarBandeja() {
    const $f = st.$panel.find('[data-filas]');
    if (!$f.length) { return; }
    $f.html(SP.skeletonFilas(8, 6));
    const params = $.extend({}, bandeja, { abiertas: 1, orden: 'antiguas' });
    return SP.api('listar', params).then(function (r) {
      const c = r.conteos;
      const abiertas = (c.PENDIENTE || 0) + (c.EN_CURSO || 0);
      st.$panel.find('[data-c=SIN_ASIGNAR]').text(c.SIN_ASIGNAR);
      st.$panel.find('[data-c=MIAS]').text(c.MIAS);
      st.$panel.find('[data-c=ABIERTAS]').text(bandeja.asignacion === 'todas' ? abiertas : '·');
      st.$root.find('[data-conteo-abiertas]').text(c.SIN_ASIGNAR).attr('title', c.SIN_ASIGNAR + ' sin asignar').prop('hidden', !c.SIN_ASIGNAR);

      if (!r.filas.length) {
        const msg = bandeja.asignacion === 'sin_asignar' ? ['Todo asignado', 'No hay solicitudes pendientes por asignar.']
          : (bandeja.asignacion === 'mias' ? ['Sin solicitudes asignadas', 'Tome una solicitud desde "Sin asignar".'] : ['Bandeja al dia', 'No hay solicitudes abiertas con estos filtros.']);
        $f.html('<tr><td colspan="8">' + SP.vacio(msg[0], msg[1], 'check') + '</td></tr>');
      } else {
        $f.html(r.filas.map(function (s) {
          return '<tr class="sp-row" tabindex="0" data-id="' + s.ID + '">' +
            '<td><span class="sp-id">#' + s.ID + '</span></td>' +
            '<td><div class="sp-cell-main">' + SP.esc(s.PROVEEDOR || s.CODIGO_SAP_PROVEEDOR) + '</div><div class="sp-cell-sub">' + SP.esc(s.USUARIO) + '</div></td>' +
            '<td><div>' + SP.esc(s.ASUNTO) + '</div><div class="sp-cell-sub">' + SP.esc(s.TIPO) + '</div></td>' +
            '<td>' + SP.badge(s.ESTADO) + '</td>' +
            '<td>' + SP.persona(s.ASIGNADO) + '</td>' +
            '<td>' + SP.antiguedad(s.MINUTOS, r.sla) + '</td>' +
            '<td class="sp-hide-sm sp-muted sp-nowrap">' + SP.fmt.relativo(s.ULTIMA_ACTIVIDAD) + '</td>' +
            '<td>' + (!s.ASIGNADO_A ? '<button type="button" class="sp-btn sp-btn--sm" data-tomar="' + s.ID + '" title="Asignarme esta solicitud">' + SP.icono('user') + 'Asignarme</button>' : '') + '</td></tr>';
        }).join(''));
      }
      st.$panel.find('[data-pager]').html(r.total > r.por_pagina ? SP.paginador(r.total, r.pagina, r.por_pagina) : '');
    }, function (err) {
      $f.html('<tr><td colspan="8">' + SP.vacio('No fue posible cargar la bandeja', err.message, 'alert') + '</td></tr>');
    });
  }

  /* =================================================================
     HISTORIAL
     ================================================================= */
  function vistaHistorial() {
    st.$panel.html(
      '<div class="sp-toolbar" data-filtros>' +
        '<div class="sp-search sp-toolbar__grow">' + SP.icono('search') + '<input type="search" class="sp-input" name="texto" placeholder="Buscar por #, asunto, proveedor o tipo" value="' + SP.esc(historial.texto) + '" aria-label="Buscar"></div>' +
        '<select class="sp-select" style="width:auto" name="estado" aria-label="Estado">' +
          '<option value="FINALIZADO"' + (historial.estado === 'FINALIZADO' ? ' selected' : '') + '>Finalizadas</option>' +
          '<option value=""' + (historial.estado === '' ? ' selected' : '') + '>Todos los estados</option></select>' +
        rangoFechas(historial) +
      '</div><div class="sp-toolbar" data-filtros2>' + selectProveedor(historial.proveedor) + selectTipo(historial.tipo_id) + selectGestor(historial.asignado_a) +
        '<span style="flex:1"></span><button type="button" class="sp-btn" data-exportar>' + SP.icono('download') + 'Exportar</button></div>' +
      '<div class="sp-card"><div class="sp-table-wrap"><table class="sp-table"><thead><tr>' +
        '<th style="width:64px">#</th><th>Proveedor</th><th>Solicitud</th><th>Estado</th><th>Responsable</th><th>Creada</th><th>Finalizada</th><th class="sp-num">Duracion</th>' +
      '</tr></thead><tbody data-filas></tbody></table></div><div data-pager></div></div>'
    );
    enlazarFiltros(st.$panel.find('[data-filtros],[data-filtros2]'), historial, listarHistorial);
    st.$panel.on('click', '[data-pagina]', function () { historial.pagina = +$(this).data('pagina'); listarHistorial(); });
    st.$panel.on('click', '[data-exportar]', exportarHistorial);
    st.$panel.on('click keydown', 'tr[data-id]', function (e) {
      if (e.type === 'keydown' && e.key !== 'Enter') { return; }
      abrirDetalle(+$(this).data('id'));
    });
    listarHistorial();
  }

  function listarHistorial() {
    const $f = st.$panel.find('[data-filas]');
    if (!$f.length) { return; }
    $f.html(SP.skeletonFilas(8, 6));
    return SP.api('listar', $.extend({}, historial, { por_pagina: 50, orden: 'recientes' })).then(function (r) {
      st.ultimoHistorial = r.filas;
      if (!r.filas.length) {
        $f.html('<tr><td colspan="8">' + SP.vacio('Sin resultados', 'No hay solicitudes con estos filtros.', 'search') + '</td></tr>');
      } else {
        $f.html(r.filas.map(function (s) {
          return '<tr class="sp-row" tabindex="0" data-id="' + s.ID + '">' +
            '<td><span class="sp-id">#' + s.ID + '</span></td>' +
            '<td><div class="sp-cell-main">' + SP.esc(s.PROVEEDOR || s.CODIGO_SAP_PROVEEDOR) + '</div></td>' +
            '<td><div>' + SP.esc(s.ASUNTO) + '</div><div class="sp-cell-sub">' + SP.esc(s.TIPO) + '</div></td>' +
            '<td>' + SP.badge(s.ESTADO) + '</td>' +
            '<td>' + SP.persona(s.ASIGNADO) + '</td>' +
            '<td class="sp-nowrap">' + SP.fmt.fecha(s.FECHA_CREACION, false) + '</td>' +
            '<td class="sp-nowrap">' + (s.FECHA_FINALIZACION ? SP.fmt.fecha(s.FECHA_FINALIZACION, false) : '—') + '</td>' +
            '<td class="sp-num sp-nowrap">' + SP.fmt.duracion(s.MINUTOS) + '</td></tr>';
        }).join(''));
      }
      st.$panel.find('[data-pager]').html(r.total > r.por_pagina ? SP.paginador(r.total, r.pagina, r.por_pagina) : '');
    }, function (err) {
      $f.html('<tr><td colspan="8">' + SP.vacio('No fue posible cargar el historial', err.message, 'alert') + '</td></tr>');
    });
  }

  /** Exporta todos los resultados del filtro actual (paginando de a 200). */
  function exportarHistorial() {
    const $b = st.$panel.find('[data-exportar]');
    SP.boton.cargando($b, 'Exportando...');
    const todas = [];
    const pagina = function (n) {
      return SP.api('listar', $.extend({}, historial, { por_pagina: 200, pagina: n, orden: 'recientes' })).then(function (r) {
        Array.prototype.push.apply(todas, r.filas);
        return todas.length < r.total && r.filas.length ? pagina(n + 1) : null;
      });
    };
    pagina(1).then(function () {
      SP.excel.exportar('historial_solicitudes_' + historial.desde + '_' + historial.hasta + '.xlsx', [
        { clave: 'ID', titulo: 'ID' }, { clave: 'CODIGO_SAP_PROVEEDOR', titulo: 'CODIGO_SAP' }, { clave: 'PROVEEDOR', titulo: 'PROVEEDOR' },
        { clave: 'TIPO', titulo: 'TIPO' }, { clave: 'ASUNTO', titulo: 'ASUNTO' }, { clave: 'ESTADO', titulo: 'ESTADO' },
        { clave: 'USUARIO', titulo: 'CREADA_POR' }, { clave: 'ASIGNADO', titulo: 'RESPONSABLE' },
        { clave: 'FECHA_CREACION', titulo: 'FECHA_CREACION' }, { clave: 'FECHA_FINALIZACION', titulo: 'FECHA_FINALIZACION' },
        { clave: 'HORAS', titulo: 'DURACION_HORAS' }
      ], todas.map(function (s) { return $.extend({}, s, { HORAS: Math.round((s.MINUTOS / 60) * 10) / 10 }); }));
      SP.boton.restaurar($b);
    }, function (err) { SP.boton.restaurar($b); SP.toast(err.message, 'error'); });
  }

  /* =================================================================
     DASHBOARD
     ================================================================= */
  function vistaDashboard() {
    st.$panel.html(
      '<div class="sp-toolbar" data-filtros>' + rangoFechas(dash) + selectProveedor(dash.proveedor) + selectTipo(dash.tipo_id) + selectGestor(dash.asignado_a) + '</div>' +
      '<div data-dash></div>'
    );
    enlazarFiltros(st.$panel.find('[data-filtros]'), dash, cargarDashboard);
    st.$panel.on('click keydown', 'tr[data-id]', function (e) {
      if (e.type === 'keydown' && e.key !== 'Enter') { return; }
      abrirDetalle(+$(this).data('id'));
    });
    st.$panel.on('click keydown', '[data-ir]', function (e) {
      if (e.type === 'keydown' && e.key !== 'Enter') { return; }
      const ir = $(this).data('ir');
      $.extend(bandeja, { asignacion: ir === 'sin_asignar' ? 'sin_asignar' : 'todas', estado: ir === 'PENDIENTE' || ir === 'EN_CURSO' ? ir : '', pagina: 1, proveedor: dash.proveedor, tipo_id: dash.tipo_id });
      if (ir === 'FINALIZADO') {
        $.extend(historial, { estado: 'FINALIZADO', desde: dash.desde, hasta: dash.hasta, proveedor: dash.proveedor, tipo_id: dash.tipo_id, asignado_a: dash.asignado_a, pagina: 1 });
        cambiarVista('historial');
      } else {
        cambiarVista('bandeja');
      }
    });
    cargarDashboard();
  }

  function cargarDashboard() {
    const $d = st.$panel.find('[data-dash]');
    if (!$d.length) { return; }
    $d.html('<div class="sp-kpis">' + new Array(8).join('<div class="sp-kpi"><div class="sp-skeleton" style="width:60%"></div><div class="sp-skeleton" style="height:28px;width:40%;margin-top:10px"></div></div>') + '</div>');
    return SP.api('dashboard', dash).then(function (r) { pintarDashboard($d, r); }, function (err) {
      $d.html(SP.vacio('No fue posible cargar el dashboard', err.message, 'alert'));
    });
  }

  function kpi(etiqueta, valor, hint, ir, color) {
    return '<div class="sp-kpi' + (ir ? ' sp-kpi--link' : '') + '"' + (ir ? ' data-ir="' + ir + '" role="button" tabindex="0"' : '') + '>' +
      '<div class="sp-kpi__label">' + (color ? '<span class="sp-kpi__dot" style="background:' + color + '"></span>' : '') + etiqueta + '</div>' +
      '<div class="sp-kpi__value">' + valor + '</div>' + (hint ? '<div class="sp-kpi__hint">' + hint + '</div>' : '') + '</div>';
  }

  function barras(lista, campoNombre, campoValor, sufijo) {
    if (!lista.length) { return '<div class="sp-muted">Sin datos en el periodo.</div>'; }
    const max = Math.max.apply(null, lista.map(function (x) { return +x[campoValor]; })) || 1;
    return '<div class="sp-bars">' + lista.map(function (x) {
      const v = +x[campoValor];
      return '<div class="sp-bar__row" title="' + SP.esc(x[campoNombre]) + ': ' + v + '"><span class="sp-bar__label">' + SP.esc(x[campoNombre] || '—') + '</span>' +
        '<span class="sp-bar__track"><span class="sp-bar__fill" style="width:' + (v / max * 100).toFixed(1) + '%"></span></span>' +
        '<span class="sp-bar__val">' + v + (sufijo ? sufijo(x) : '') + '</span></div>';
    }).join('') + '</div>';
  }

  function pintarDashboard($d, r) {
    const k = r.kpi;
    const sla = r.sla;
    const css = getComputedStyle(st.$root.closest('.sp-app')[0] || document.body);
    const color = function (v) { return css.getPropertyValue(v).trim(); };

    let html = '<div class="sp-kpis">' +
      kpi('Recibidas', k.TOTAL, SP.fmt.fecha(r.filtros.desde, false) + ' – ' + SP.fmt.fecha(r.filtros.hasta, false)) +
      kpi('Pendientes', k.PENDIENTE, 'Ver en bandeja', 'PENDIENTE', color('--sp-pend')) +
      kpi('En curso', k.EN_CURSO, 'Ver en bandeja', 'EN_CURSO', color('--sp-curso')) +
      kpi('Finalizadas', k.FINALIZADO, 'Ver en historial', 'FINALIZADO', color('--sp-fin')) +
      kpi('Sin asignar', k.SIN_ASIGNAR, k.SIN_ASIGNAR ? 'Requieren responsable' : 'Todo asignado', 'sin_asignar') +
      kpi('Fuera de tiempo', k.CRITICAS, 'Abiertas hace mas de ' + sla.critico + ' h' + (k.EN_ALERTA ? ' · ' + k.EN_ALERTA + ' en alerta' : '')) +
      kpi('Primera atencion', k.MIN_PROM_ATENCION ? SP.fmt.duracion(k.MIN_PROM_ATENCION) : '—', 'Promedio hasta "En curso"') +
      kpi('Resolucion', k.MIN_PROM_RESOLUCION ? SP.fmt.duracion(k.MIN_PROM_RESOLUCION) : '—', 'Promedio hasta finalizar') +
    '</div>';

    html += '<div class="sp-dash-grid">' +
      panelLista('Sin asignar', 'Las mas antiguas primero', r.sin_asignar, sla, false) +
      panelLista('En proceso sin cerrar', 'Asignadas y abiertas, las mas antiguas primero', r.en_proceso, sla, true) +
    '</div>';

    // Carga por responsable (barra apilada por estado + valores en columnas)
    html += '<div class="sp-card" style="margin-bottom:16px"><div class="sp-card__head"><h3 class="sp-card__title">Carga por responsable</h3>' +
      '<div class="sp-legend"><span><i style="background:' + color('--sp-pend') + '"></i>Pendiente</span><span><i style="background:' + color('--sp-curso') + '"></i>En curso</span><span><i style="background:' + color('--sp-fin') + '"></i>Finalizada</span></div></div>';
    if (!r.por_gestor.length) {
      html += '<div class="sp-card__body sp-muted">Aun no hay solicitudes asignadas en el periodo.</div>';
    } else {
      const max = Math.max.apply(null, r.por_gestor.map(function (g) { return +g.PENDIENTE + +g.EN_CURSO + +g.FINALIZADO; })) || 1;
      html += '<div class="sp-table-wrap"><table class="sp-table sp-table--compact"><thead><tr><th>Responsable</th><th class="sp-num">Pend.</th><th class="sp-num">En curso</th><th class="sp-num">Final.</th><th style="width:34%">Distribucion</th><th class="sp-num">Resolucion prom.</th></tr></thead><tbody>' +
        r.por_gestor.map(function (g) {
          const tot = +g.PENDIENTE + +g.EN_CURSO + +g.FINALIZADO;
          const seg = function (n, v) { return n ? '<span style="width:' + (n / max * 100).toFixed(1) + '%;background:' + color(v) + '" title="' + n + '"></span>' : ''; };
          return '<tr><td>' + SP.persona(g.NOMBRE) + '</td><td class="sp-num">' + g.PENDIENTE + '</td><td class="sp-num">' + g.EN_CURSO + '</td><td class="sp-num">' + g.FINALIZADO + '</td>' +
            '<td><div class="sp-stack" title="' + tot + ' solicitudes">' + seg(+g.PENDIENTE, '--sp-pend') + seg(+g.EN_CURSO, '--sp-curso') + seg(+g.FINALIZADO, '--sp-fin') + '</div></td>' +
            '<td class="sp-num">' + (g.MIN_PROM_RESOLUCION ? SP.fmt.duracion(g.MIN_PROM_RESOLUCION) : '—') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }
    html += '</div>';

    html += '<div class="sp-dash-grid">' +
      '<div class="sp-card"><div class="sp-card__head"><h3 class="sp-card__title">Solicitudes por tipo</h3></div><div class="sp-card__body">' +
        barras(r.por_tipo, 'NOMBRE', 'TOTAL') + '</div></div>' +
      '<div class="sp-card"><div class="sp-card__head"><h3 class="sp-card__title">Proveedores con mas solicitudes</h3></div><div class="sp-card__body">' +
        barras(r.por_proveedor, 'NOMBRE', 'TOTAL') + '</div></div>' +
    '</div>';

    $d.html(html);
  }

  function panelLista(titulo, sub, filas, sla, conResponsable) {
    let html = '<div class="sp-card"><div class="sp-card__head"><div><h3 class="sp-card__title">' + titulo + ' <span class="sp-count">' + filas.length + (filas.length >= 100 ? '+' : '') + '</span></h3>' +
      '<div class="sp-cell-sub">' + sub + '</div></div></div>';
    if (!filas.length) {
      return html + SP.vacio(conResponsable ? 'Nada en proceso' : 'Todo asignado', '', 'check') + '</div>';
    }
    html += '<div class="sp-table-wrap" style="max-height:360px;overflow:auto"><table class="sp-table sp-table--compact"><thead><tr><th>#</th><th>Proveedor / asunto</th>' +
      (conResponsable ? '<th>Responsable</th>' : '') + '<th>Abierta</th>' + (conResponsable ? '<th class="sp-hide-sm">Asignada hace</th>' : '') + '</tr></thead><tbody>' +
      filas.map(function (s) {
        return '<tr class="sp-row" tabindex="0" data-id="' + s.ID + '"><td><span class="sp-id">#' + s.ID + '</span></td>' +
          '<td><div class="sp-cell-main">' + SP.esc(s.PROVEEDOR || '—') + '</div><div class="sp-cell-sub">' + SP.esc(s.ASUNTO) + ' · ' + SP.esc(s.TIPO) + '</div></td>' +
          (conResponsable ? '<td>' + SP.persona(s.ASIGNADO) + '<div class="sp-cell-sub">' + SP.esc(SP.ESTADOS[s.ESTADO]) + '</div></td>' : '') +
          '<td>' + SP.antiguedad(s.MINUTOS, sla) + '</td>' +
          (conResponsable ? '<td class="sp-hide-sm sp-nowrap">' + (s.MINUTOS_ASIGNADA !== null ? SP.fmt.duracion(s.MINUTOS_ASIGNADA) : '—') + '</td>' : '') + '</tr>';
      }).join('') + '</tbody></table></div>';
    return html + '</div>';
  }

})(window, jQuery);

/* =====================================================================
   SOLICITUDES DE PROVEEDORES - Vista del proveedor
   Crear solicitudes y seguir su estado (pendientes / en curso / finalizadas)
   ===================================================================== */
(function (window, $) {
  'use strict';

  const SP = window.SP;
  const ICONO_PLANTILLA = { DESCUENTOS: 'percent', BONIFICADOS: 'gift', LIBRE: 'file' };

  const st = {
    cat: null,
    filtros: { estado: 'PENDIENTE', texto: '', tipo_id: '', pagina: 1, novedades: 0 },
    estadoPrevio: 'PENDIENTE',
    totalNovedades: null,
    $root: null
  };

  const REFRESCO_MS = 60000;

  $(function () {
    st.$root = $('#sp-root');
    SP.api('catalogos').then(function (cat) {
      st.cat = cat;
      pintarShell();
      listar();
      abrirDesdeUrl();
      // Revisa respuestas nuevas de mercadeo sin recargar la pagina
      setInterval(function () {
        if (!document.hidden && !SP.drawer.abierto()) { listar(true); }
      }, REFRESCO_MS);
    }, function (err) {
      st.$root.html(SP.vacio('No fue posible cargar el modulo', err.message, 'alert'));
    });
  });

  /** Permite enlazar directo a una solicitud: ?id=123 */
  function abrirDesdeUrl() {
    const id = new URLSearchParams(window.location.search).get('id');
    if (id && /^\d+$/.test(id)) { abrirDetalle(+id); }
  }

  function pintarShell() {
    const a = st.cat.actor;
    st.$root.html(
      '<header class="sp-header"><div>' +
        '<h1 class="sp-header__title">Mis solicitudes</h1>' +
        '<p class="sp-header__sub"><span class="sp-org-chip">Org. ' + SP.esc(a.organizacion || '—') + '</span>' +
        (a.proveedor ? '<span>' + SP.esc(a.proveedor) + '</span>' : '') + '</p>' +
      '</div><button type="button" class="sp-btn sp-btn--primary" data-nueva>' + SP.icono('plus') + 'Nueva solicitud</button></header>' +
      '<nav class="sp-nav" role="tablist">' +
        ['PENDIENTE', 'EN_CURSO', 'FINALIZADO'].map(function (e) {
          return '<button type="button" class="sp-nav__item" role="tab" data-estado="' + e + '" aria-selected="' + (st.filtros.estado === e) + '">' +
            { PENDIENTE: 'Pendientes', EN_CURSO: 'En curso', FINALIZADO: 'Finalizadas' }[e] + ' <span class="sp-count" data-conteo="' + e + '">0</span>' +
            '<span class="sp-dot-nuevo" data-nov="' + e + '" title="Con respuestas nuevas de mercadeo" hidden></span></button>';
        }).join('') +
      '</nav>' +
      '<div data-aviso></div>' +
      '<div class="sp-toolbar">' +
        '<div class="sp-search sp-toolbar__grow">' + SP.icono('search') + '<input type="search" class="sp-input" placeholder="Buscar por numero o asunto" data-texto aria-label="Buscar"></div>' +
        '<select class="sp-select" style="width:auto;min-width:180px" data-tipo aria-label="Tipo de solicitud"><option value="">Todos los tipos</option>' +
          st.cat.tipos.map(function (t) { return '<option value="' + t.ID + '">' + SP.esc(t.NOMBRE) + '</option>'; }).join('') +
        '</select>' +
      '</div>' +
      '<div class="sp-card"><div class="sp-table-wrap"><table class="sp-table"><thead><tr>' +
        '<th style="width:70px">#</th><th>Solicitud</th><th>Estado</th><th class="sp-hide-sm">Responsable</th><th class="sp-hide-sm">Creada</th><th class="sp-hide-sm">Ultima actividad</th>' +
      '</tr></thead><tbody data-filas></tbody></table></div><div data-pager></div></div>'
    );

    st.$root.on('click', '[data-nueva]', abrirNueva);
    st.$root.on('click', '[data-estado]', function () {
      st.filtros.estado = $(this).data('estado');
      st.filtros.novedades = 0;
      st.filtros.pagina = 1;
      st.$root.find('[data-estado]').attr('aria-selected', 'false');
      $(this).attr('aria-selected', 'true');
      listar();
    });
    st.$root.on('input', '[data-texto]', SP.debounce(function () { st.filtros.texto = $.trim($(this).val()); st.filtros.pagina = 1; listar(); }, 350));
    st.$root.on('change', '[data-tipo]', function () { st.filtros.tipo_id = $(this).val(); st.filtros.pagina = 1; listar(); });
    st.$root.on('click', '[data-ver-novedades]', function () {
      st.estadoPrevio = st.filtros.estado || st.estadoPrevio;
      $.extend(st.filtros, { novedades: 1, estado: '', pagina: 1 });
      st.$root.find('[data-estado]').attr('aria-selected', 'false');
      listar();
    });
    st.$root.on('click', '[data-quitar-novedades]', function () {
      st.$root.find('[data-estado="' + st.estadoPrevio + '"]').trigger('click');
    });
    st.$root.on('click', '[data-pagina]', function () { st.filtros.pagina = +$(this).data('pagina'); listar(); });
    st.$root.on('click keydown', '[data-id]', function (e) {
      if (e.type === 'keydown' && e.key !== 'Enter') { return; }
      abrirDetalle(+$(this).data('id'));
    });
  }

  /** @param {boolean} silencioso true = sin esqueleto de carga (refresco automatico) */
  function listar(silencioso) {
    const $f = st.$root.find('[data-filas]');
    if (!silencioso) { $f.html(SP.skeletonFilas(6, 4)); }
    return SP.api('listar', $.extend({}, st.filtros, { orden: st.filtros.estado === 'FINALIZADO' ? 'recientes' : 'antiguas' })).then(function (r) {
      $.each(r.conteos, function (e, n) { st.$root.find('[data-conteo="' + e + '"]').text(n); });
      pintarNovedades(r.conteos);
      if (!r.filas.length) {
        $f.html('<tr><td colspan="6">' + (st.filtros.novedades
          ? SP.vacio('Esta al dia', 'No hay respuestas nuevas de mercadeo.', 'check')
          : st.filtros.texto || st.filtros.tipo_id
          ? SP.vacio('Sin resultados', 'Pruebe con otros filtros.', 'search')
          : SP.vacio('No tiene solicitudes ' + SP.ESTADOS[st.filtros.estado].toLowerCase() + 's', st.filtros.estado === 'PENDIENTE' ? 'Cree una nueva solicitud con el boton "Nueva solicitud".' : '')) + '</td></tr>');
      } else {
        $f.html(r.filas.map(function (s) {
          const nuevo = +s.NOVEDADES > 0;
          return '<tr class="sp-row' + (nuevo ? ' sp-row--nuevo' : '') + '" tabindex="0" data-id="' + s.ID + '">' +
            '<td><span class="sp-id">#' + s.ID + '</span></td>' +
            '<td><div class="sp-cell-main">' + SP.esc(s.ASUNTO) + '</div><div class="sp-cell-sub">' + SP.esc(s.TIPO) + ' · ' + SP.esc(s.USUARIO) + '</div>' +
            (nuevo ? SP.textoNovedad(s, true) : '') + '</td>' +
            '<td>' + SP.badge(s.ESTADO) + '</td>' +
            '<td class="sp-hide-sm">' + SP.persona(s.ASIGNADO, 'Por asignar') + '</td>' +
            '<td class="sp-hide-sm sp-nowrap" title="' + SP.esc(SP.fmt.fecha(s.FECHA_CREACION)) + '">' + SP.fmt.fecha(s.FECHA_CREACION, false) + '</td>' +
            '<td class="sp-hide-sm sp-nowrap sp-muted">' + SP.fmt.relativo(s.ULTIMA_ACTIVIDAD) + '</td></tr>';
        }).join(''));
      }
      st.$root.find('[data-pager]').html(r.total > r.por_pagina ? SP.paginador(r.total, r.pagina, r.por_pagina) : '');
    }, function (err) {
      $f.html('<tr><td colspan="6">' + SP.vacio('No fue posible cargar las solicitudes', err.message, 'alert') + '</td></tr>');
    });
  }

  /** Aviso superior, puntos en las pestanas y notificacion cuando llega una respuesta nueva. */
  function pintarNovedades(c) {
    const total = c.NOVEDADES || 0;
    ['PENDIENTE', 'EN_CURSO', 'FINALIZADO'].forEach(function (e) {
      st.$root.find('[data-nov="' + e + '"]').prop('hidden', !(c['NOVEDADES_' + e] > 0));
    });
    let html = '';
    if (st.filtros.novedades) {
      html = '<div class="sp-alert sp-alert--info" style="align-items:center">' + SP.icono('chat') +
        '<span style="flex:1">Mostrando solo las solicitudes con respuestas nuevas de mercadeo.</span>' +
        '<button type="button" class="sp-btn sp-btn--sm" data-quitar-novedades>Ver todas</button></div>';
    } else if (total > 0) {
      html = '<div class="sp-aviso-nuevo" style="align-items:center">' + SP.icono('chat') +
        '<span><b>Mercadeo respondio ' + (total === 1 ? 'en 1 solicitud' : 'en ' + total + ' solicitudes') + '.</b> ' +
        'Hay asignaciones, cambios de estado o mensajes que aun no ha visto.</span>' +
        '<button type="button" class="sp-btn sp-btn--sm" data-ver-novedades>Ver respuestas</button></div>';
    }
    st.$root.find('[data-aviso]').html(html);
    if (st.totalNovedades !== null && total > st.totalNovedades) {
      SP.toast('Tiene una respuesta nueva del departamento de mercadeo', 'ok', 6000);
    }
    st.totalNovedades = total;
  }

  function abrirDetalle(id) {
    // Al cerrar se refresca la lista para quitar la marca de "nuevo" de lo que ya vio
    SP.detalle.abrir(id, { catalogos: st.cat, alCambiar: listar, alCerrar: function () { listar(true); } });
  }

  /* =================================================================
     NUEVA SOLICITUD
     ================================================================= */
  function abrirNueva() {
    if (!st.cat.tipos.length) {
      SP.toast('No hay tipos de solicitud disponibles. Contacte al administrador.', 'error');
      return;
    }
    const f = { tipo: null, excel: null, analisis: null, enviando: false, sucio: false };
    const drawer = SP.drawer.abrir({
      titulo: 'Nueva solicitud',
      subtitulo: 'Dirigida al equipo de mercadeo',
      puedeCerrar: function () { return f.enviando ? false : (!f.sucio || window.confirm('¿Descartar la solicitud que esta creando?')); }
    });
    f.drawer = drawer;
    pasoTipo(f);
  }

  function pasos(actual) {
    return '<div class="sp-steps"><span class="sp-step-dot ' + (actual >= 1 ? 'is-active' : '') + '">1</span><span>' + (actual === 1 ? '<b>Tipo</b>' : 'Tipo') + '</span>' +
      '<span class="sp-steps__sep"></span><span class="sp-step-dot ' + (actual >= 2 ? 'is-active' : '') + '">2</span><span>' + (actual === 2 ? '<b>Informacion</b>' : 'Informacion') + '</span></div>';
  }

  function pasoTipo(f) {
    const $b = f.drawer.cuerpo(pasos(1) +
      '<p class="sp-muted" style="margin-top:0">¿Que necesita solicitar?</p>' +
      '<div class="sp-choices" role="radiogroup">' + st.cat.tipos.map(function (t) {
        return '<button type="button" class="sp-choice" role="radio" aria-checked="' + (f.tipo && f.tipo.ID === t.ID) + '" data-tipo-id="' + t.ID + '">' +
          '<span class="sp-choice__icon">' + SP.icono(ICONO_PLANTILLA[t.PLANTILLA] || 'file') + '</span>' +
          '<span class="sp-choice__title">' + SP.esc(t.NOMBRE) + '</span>' +
          '<span class="sp-choice__desc">' + SP.esc(t.DESCRIPCION || '') + '</span>' +
          (t.REQUIERE_EXCEL ? '<span class="sp-tag sp-tag--brand" style="align-self:flex-start">Requiere Excel</span>' : '') +
        '</button>';
      }).join('') + '</div>');
    f.drawer.pie('<button type="button" class="sp-btn sp-btn--ghost" data-cerrar>Cancelar</button>');
    $b.on('click', '[data-tipo-id]', function () {
      const id = +$(this).data('tipo-id');
      f.tipo = st.cat.tipos.filter(function (t) { return +t.ID === id; })[0];
      f.excel = null;
      f.analisis = null;
      pasoFormulario(f);
    });
    $b.find('.sp-choice').first().trigger('focus');
  }

  function pasoFormulario(f) {
    const t = f.tipo;
    const cat = st.cat;
    const plantilla = cat.plantillas.filter(function (p) { return p.codigo === t.PLANTILLA; })[0];
    const extAdjuntos = t.REQUIERE_EXCEL ? cat.adjuntos.ext_gestion : t.EXTENSIONES;
    const adjuntoObligatorio = !t.REQUIERE_EXCEL && +t.REQUIERE_ADJUNTO === 1;

    const $b = f.drawer.cuerpo(pasos(2) +
      '<div class="sp-alert sp-alert--info" style="justify-content:space-between;align-items:center">' +
        '<span class="sp-flex">' + SP.icono(ICONO_PLANTILLA[t.PLANTILLA] || 'file') + '<b>' + SP.esc(t.NOMBRE) + '</b></span>' +
        '<button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" data-cambiar-tipo>Cambiar tipo</button></div>' +
      '<form data-form novalidate>' +
        '<div class="sp-field"><label for="sp-asunto" class="sp-req">Asunto</label>' +
          '<input id="sp-asunto" name="asunto" class="sp-input" maxlength="150" autocomplete="off" placeholder="Resumen corto de lo que solicita"></div>' +
        '<div class="sp-field"><label for="sp-obs" class="sp-req">Observacion</label>' +
          '<textarea id="sp-obs" name="observacion" class="sp-textarea" rows="5" maxlength="8000" placeholder="Explique el caso con el mayor detalle posible: vigencia, condiciones, canales, etc."></textarea>' +
          '<div class="sp-between"><span class="sp-field__help">Minimo 10 caracteres.</span><span class="sp-counter" data-contador>0 / 8000</span></div></div>' +
        (t.REQUIERE_EXCEL ? seccionExcel(plantilla, cat) : '') +
        '<div class="sp-field" data-campo-adjuntos><span class="sp-label ' + (adjuntoObligatorio ? 'sp-req' : '') + '">' + (t.REQUIERE_EXCEL ? 'Soportes adicionales (opcional)' : 'Adjuntos') + '</span>' +
          '<input type="hidden" name="adjuntos">' +
          '<label class="sp-drop" data-drop-adjuntos tabindex="0">' + SP.icono('upload') +
            '<div class="sp-drop__title">Arrastre archivos, haga clic o pegue una imagen (Ctrl+V)</div>' +
            '<div class="sp-drop__hint">' + SP.esc(extAdjuntos.join(', ')) + ' · max. ' + cat.adjuntos.max_mb + ' MB c/u</div>' +
            '<input type="file" multiple data-input-adjuntos accept="' + extAdjuntos.map(function (e) { return '.' + e; }).join(',') + '"></label>' +
          '<div class="sp-files" data-lista-adjuntos></div></div>' +
      '</form>');

    f.drawer.pie('<span class="sp-grow" data-estado-envio></span><button type="button" class="sp-btn sp-btn--ghost" data-cerrar>Cancelar</button>' +
      '<button type="button" class="sp-btn sp-btn--primary" data-enviar>' + SP.icono('arrow') + 'Enviar solicitud</button>');
    const $pie = f.drawer.$el.find('.sp-drawer__foot');

    // Adjuntos
    f.cargador = new SP.Cargador({
      extensiones: extAdjuntos, maxMb: cat.adjuntos.max_mb, maxArchivos: cat.adjuntos.max_por_envio,
      alCambiar: function () { $b.find('[data-lista-adjuntos]').html(f.cargador.html()); f.sucio = true; }
    });
    f.cargador.conectar($b.find('[data-drop-adjuntos]'), $b.find('[data-input-adjuntos]'), f.drawer.$el);
    $b.on('click', '[data-lista-adjuntos] [data-quitar]', function (e) { e.preventDefault(); f.cargador.quitar($(this).data('quitar')); });

    $b.on('click', '[data-cambiar-tipo]', function () { pasoTipo(f); });
    $b.on('input', 'input,textarea', function () { f.sucio = true; });
    $b.on('input', '[name=observacion]', function () { $b.find('[data-contador]').text(this.value.length + ' / 8000'); });

    if (t.REQUIERE_EXCEL) { eventosExcel(f, $b, plantilla); }

    $pie.on('click', '[data-enviar]', function () { enviar(f, $b, $pie, adjuntoObligatorio); });
    $b.find('[name=asunto]').trigger('focus');
  }

  /* ---------------------------- Excel -------------------------------- */

  function seccionExcel(plantilla, cat) {
    return '<div class="sp-field" data-campo-excel><div class="sp-between"><span class="sp-label sp-req">Archivo Excel de ' + SP.esc(plantilla.nombre.toLowerCase()) + '</span>' +
      '<button type="button" class="sp-btn sp-btn--sm" data-plantilla>' + SP.icono('download') + 'Descargar plantilla</button></div>' +
      '<input type="hidden" name="archivo_excel">' +
      '<div class="sp-field__help">Columnas: ' + plantilla.columnas.map(function (c) { return '<b>' + SP.esc(c.titulo) + '</b>'; }).join(', ') +
      '. La descripcion de cada material se toma automaticamente del maestro.</div>' +
      '<label class="sp-drop" data-drop-excel tabindex="0">' + SP.icono('upload') +
        '<div class="sp-drop__title">Arrastre el archivo Excel o haga clic para seleccionarlo</div>' +
        '<div class="sp-drop__hint">.xlsx o .xls · maximo ' + cat.max_filas_excel + ' filas</div>' +
        '<input type="file" data-input-excel accept=".xlsx,.xls"></label>' +
      '<div data-resultado-excel></div></div>';
  }

  function eventosExcel(f, $b, plantilla) {
    const $zona = $b.find('[data-drop-excel]');
    const procesar = function (files) {
      const file = files && files[0];
      if (!file) { return; }
      const ext = file.name.split('.').pop().toLowerCase();
      if (st.cat.adjuntos.ext_plantilla.indexOf(ext) === -1) { SP.toast('Seleccione un archivo .xlsx o .xls', 'error'); return; }
      if (file.size / 1048576 > st.cat.adjuntos.max_mb) { SP.toast('El archivo supera ' + st.cat.adjuntos.max_mb + ' MB', 'error'); return; }
      f.excel = file;
      f.analisis = null;
      f.sucio = true;
      analizarExcel(f, $b, plantilla);
    };
    $b.on('click', '[data-plantilla]', function () {
      try { SP.excel.descargarPlantilla(plantilla); } catch (e) { SP.toast(e.message, 'error'); }
    });
    $zona.on('dragover', function (e) { e.preventDefault(); $zona.addClass('is-over'); })
      .on('dragleave drop', function () { $zona.removeClass('is-over'); })
      .on('drop', function (e) { e.preventDefault(); procesar(e.originalEvent.dataTransfer.files); });
    $b.find('[data-input-excel]').on('change', function () { procesar(this.files); this.value = ''; });
    $b.on('click', '[data-quitar-excel]', function (e) {
      e.preventDefault();
      f.excel = null; f.analisis = null;
      $b.find('[data-resultado-excel]').empty();
      $zona.prop('hidden', false);
    });
  }

  function analizarExcel(f, $b, plantilla) {
    const $r = $b.find('[data-resultado-excel]');
    const $zona = $b.find('[data-drop-excel]');
    $zona.prop('hidden', true);
    $r.html('<div class="sp-alert sp-alert--info"><span class="sp-spin"></span>Leyendo y validando "' + SP.esc(f.excel.name) + '" contra el maestro de materiales...</div>');

    SP.excel.leer(f.excel, plantilla.columnas)
      .then(function (lectura) {
        f.filasLeidas = lectura.filas;
        return SP.api('analizar_detalle', { tipo_id: f.tipo.ID, filas: JSON.stringify(lectura.filas) });
      })
      .then(function (an) {
        f.analisis = an;
        pintarAnalisis(f, $r, an);
      }, function (err) {
        f.analisis = null;
        $r.html('<div class="sp-alert sp-alert--err">' + SP.icono('alert') + '<div><b>No se pudo procesar el archivo.</b><br>' + SP.esc(err.message) +
          '</div></div><button type="button" class="sp-btn sp-btn--sm" data-quitar-excel>Elegir otro archivo</button>');
      });
  }

  function pintarAnalisis(f, $r, an) {
    const generales = an.errores.filter(function (e) { return !e.fila; });
    const porFila = an.errores.filter(function (e) { return e.fila; });
    const filasConError = {};
    porFila.forEach(function (e) { filasConError[e.fila] = true; });
    const nErr = Object.keys(filasConError).length;

    let html = '<div class="sp-files" style="margin-top:0">' + SP.archivoChip({ nombre: f.excel.name, extension: f.excel.name.split('.').pop(), tamano_kb: Math.ceil(f.excel.size / 1024) }) +
      '<button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" data-quitar-excel>' + SP.icono('refresh') + 'Cambiar archivo</button></div>';

    html += '<div class="sp-check">' +
      '<div class="sp-check__item"><div class="sp-check__num">' + an.total + '</div><div class="sp-check__lbl">Filas leidas</div></div>' +
      '<div class="sp-check__item sp-check__item--ok"><div class="sp-check__num">' + Math.max(0, an.total - nErr) + '</div><div class="sp-check__lbl">Filas validas</div></div>' +
      '<div class="sp-check__item ' + (nErr ? 'sp-check__item--err' : '') + '"><div class="sp-check__num">' + nErr + '</div><div class="sp-check__lbl">Filas con error</div></div>' +
      (an.advertencias.length ? '<div class="sp-check__item sp-check__item--warn"><div class="sp-check__num">' + an.advertencias.length + '</div><div class="sp-check__lbl">Advertencias</div></div>' : '') +
    '</div>';

    if (an.valido) {
      html += '<div class="sp-alert sp-alert--ok">' + SP.icono('check') + 'El archivo es valido y esta listo para enviarse.' + (an.advertencias.length ? ' Revise las advertencias resaltadas.' : '') + '</div>';
    } else {
      html += '<div class="sp-alert sp-alert--err">' + SP.icono('alert') + '<div><b>Corrija el archivo y vuelva a cargarlo.</b>' +
        (generales.length ? '<ul>' + generales.map(function (e) { return '<li>' + SP.esc(e.mensaje) + '</li>'; }).join('') + '</ul>' : ' Las filas con error estan resaltadas en rojo.') + '</div></div>';
    }
    if (an.filas.length) {
      html += SP.excel.tablaDetalle(an.plantilla, an.filas, porFila, an.advertencias);
    }
    $r.html(html);
  }

  /* ---------------------------- Envio -------------------------------- */

  function enviar(f, $b, $pie, adjuntoObligatorio) {
    const $form = $b.find('[data-form]');
    const datos = { asunto: $.trim($form.find('[name=asunto]').val()), observacion: $.trim($form.find('[name=observacion]').val()) };
    const errores = {};
    if (datos.asunto.length < 5) { errores.asunto = 'Escriba un asunto de al menos 5 caracteres'; }
    if (datos.observacion.length < 10) { errores.observacion = 'Describa el caso con al menos 10 caracteres'; }
    if (f.tipo.REQUIERE_EXCEL && !f.excel) { errores.archivo_excel = 'Cargue el archivo Excel'; }
    if (adjuntoObligatorio && !f.cargador.archivos.length) { errores.adjuntos = 'Adjunte al menos un archivo'; }
    SP.mostrarErrores($form, errores);
    if (Object.keys(errores).length) { return; }
    if (f.tipo.REQUIERE_EXCEL && (!f.analisis || !f.analisis.valido)) {
      SP.toast(f.analisis ? 'El archivo Excel tiene errores. Corrijalo antes de enviar.' : 'Espere a que termine la validacion del Excel', 'error');
      return;
    }

    const $btn = $pie.find('[data-enviar]');
    const $estado = $pie.find('[data-estado-envio]');
    const ruta = st.cat.adjuntos.ruta_ftp;
    f.enviando = true;
    SP.boton.cargando($btn, 'Enviando...');

    let excelSubido = null;
    const pasoExcel = f.tipo.REQUIERE_EXCEL
      ? (function () {
        $estado.text('Subiendo archivo Excel...');
        return SP.subirArchivo(f.excel, ruta).then(function (r) {
          excelSubido = { nombre: f.excel.name, ruta: r.ruta, url: r.url, tamano_kb: Math.ceil(f.excel.size / 1024) };
        });
      })()
      : Promise.resolve();

    pasoExcel
      .then(function () {
        return f.cargador.subir(ruta, function (i, n) { $estado.text('Subiendo adjunto ' + i + ' de ' + n + '...'); });
      })
      .then(function (adjuntos) {
        $estado.text('Registrando solicitud...');
        return SP.api('crear', {
          tipo_id: f.tipo.ID,
          asunto: datos.asunto,
          observacion: datos.observacion,
          filas: f.tipo.REQUIERE_EXCEL ? JSON.stringify(f.filasLeidas) : '',
          archivo_excel: excelSubido ? JSON.stringify(excelSubido) : '',
          adjuntos: JSON.stringify(adjuntos)
        });
      })
      .then(function (r) {
        f.enviando = false;
        f.sucio = false;
        f.drawer.cerrar();
        SP.toast('Solicitud #' + r.id + ' enviada a mercadeo', 'ok');
        st.filtros.estado = 'PENDIENTE';
        st.filtros.novedades = 0;
        st.$root.find('[data-estado]').attr('aria-selected', 'false').filter('[data-estado=PENDIENTE]').attr('aria-selected', 'true');
        listar();
      }, function (err) {
        f.enviando = false;
        SP.boton.restaurar($btn);
        $estado.text('');
        SP.toast(err.message, 'error');
        if (err.errores && !$.isArray(err.errores)) { SP.mostrarErrores($form, err.errores); }
        if ($.isArray(err.errores) && err.errores.length && f.analisis) {
          f.analisis.valido = false;
          f.analisis.errores = err.errores;
          pintarAnalisis(f, $b.find('[data-resultado-excel]'), f.analisis);
        }
      });
  }

})(window, jQuery);

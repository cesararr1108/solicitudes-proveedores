/* =====================================================================
   SOLICITUDES DE PROVEEDORES - Administracion de tipos de solicitud
   Solo rol administrador. Cada tipo se asocia a un proceso (T_CAL_PROCESOS).
   ===================================================================== */
(function (window, $) {
  'use strict';

  const SP = window.SP;
  const ICONO_PLANTILLA = { DESCUENTOS: 'percent', BONIFICADOS: 'gift', LIBRE: 'file' };
  const st = { cat: null, tipos: [], texto: '', $root: null };

  $(function () {
    st.$root = $('#sp-root');
    SP.api('catalogos').then(function (cat) {
      st.cat = cat;
      pintarShell();
      listar();
    }, function (err) {
      st.$root.html(SP.vacio('No fue posible cargar el modulo', err.message, 'alert'));
    });
  });

  function pintarShell() {
    st.$root.html(
      '<header class="sp-header"><div><h1 class="sp-header__title">Tipos de solicitud</h1>' +
      '<p class="sp-header__sub">Defina que pueden solicitar los proveedores y a que proceso llega cada solicitud.</p></div>' +
      '<button type="button" class="sp-btn sp-btn--primary" data-nuevo>' + SP.icono('plus') + 'Nuevo tipo</button></header>' +
      '<div class="sp-toolbar"><div class="sp-search sp-toolbar__grow">' + SP.icono('search') +
      '<input type="search" class="sp-input" placeholder="Buscar por nombre o proceso" data-texto aria-label="Buscar"></div></div>' +
      '<div class="sp-card"><div class="sp-table-wrap"><table class="sp-table"><thead><tr>' +
        '<th>Tipo</th><th>Proceso</th><th>Plantilla</th><th>Adjuntos</th><th class="sp-num">Solicitudes</th><th>Activo</th><th style="width:1%"></th>' +
      '</tr></thead><tbody data-filas></tbody></table></div></div>'
    );
    st.$root.on('click', '[data-nuevo]', function () { abrirFormulario(null); });
    st.$root.on('click', '[data-editar]', function () {
      const id = +$(this).data('editar');
      abrirFormulario(st.tipos.filter(function (t) { return +t.ID === id; })[0]);
    });
    st.$root.on('input', '[data-texto]', SP.debounce(function () { st.texto = $.trim($(this).val()).toLowerCase(); pintarFilas(); }, 200));
    st.$root.on('change', '[data-activo]', function () {
      const $chk = $(this);
      const id = $chk.data('activo');
      $chk.prop('disabled', true);
      SP.api('tipos_estado', { id: id, activo: $chk.is(':checked') ? 1 : 0 }).then(function (t) {
        SP.toast('"' + t.NOMBRE + '" ' + (+t.ACTIVO ? 'activado' : 'desactivado'), 'ok');
        listar();
      }, function (err) {
        $chk.prop('checked', !$chk.is(':checked')).prop('disabled', false);
        SP.toast(err.message, 'error');
      });
    });
  }

  function listar() {
    st.$root.find('[data-filas]').html(SP.skeletonFilas(7, 3));
    return SP.api('tipos_listar', { incluir_inactivos: 1 }).then(function (tipos) {
      st.tipos = tipos;
      pintarFilas();
    }, function (err) {
      st.$root.find('[data-filas]').html('<tr><td colspan="7">' + SP.vacio('Error', err.message, 'alert') + '</td></tr>');
    });
  }

  function pintarFilas() {
    const lista = st.tipos.filter(function (t) {
      return !st.texto || (t.NOMBRE + ' ' + t.PROCESO).toLowerCase().indexOf(st.texto) !== -1;
    });
    const $f = st.$root.find('[data-filas]');
    if (!lista.length) {
      $f.html('<tr><td colspan="7">' + SP.vacio(st.texto ? 'Sin resultados' : 'Aun no hay tipos de solicitud', st.texto ? '' : 'Cree el primero con "Nuevo tipo".', 'settings') + '</td></tr>');
      return;
    }
    $f.html(lista.map(function (t) {
      const plantilla = (st.cat.plantillas.filter(function (p) { return p.codigo === t.PLANTILLA; })[0] || {}).nombre || t.PLANTILLA;
      return '<tr>' +
        '<td><div class="sp-cell-main">' + SP.esc(t.NOMBRE) + '</div><div class="sp-cell-sub">' + SP.esc(t.DESCRIPCION || '') + '</div></td>' +
        '<td>' + SP.esc(t.PROCESO) + (t.ORGANIZACION ? '<div class="sp-cell-sub">Org. ' + SP.esc(t.ORGANIZACION) + '</div>' : '') + '</td>' +
        '<td><span class="sp-tag ' + (t.PLANTILLA !== 'LIBRE' ? 'sp-tag--brand' : '') + '">' + SP.icono(ICONO_PLANTILLA[t.PLANTILLA] || 'file') + SP.esc(plantilla) + '</span></td>' +
        '<td>' + (+t.REQUIERE_ADJUNTO ? '<b>Obligatorio</b>' : 'Opcional') + '<div class="sp-cell-sub">' + SP.esc((t.EXTENSIONES || []).join(', ')) + '</div></td>' +
        '<td class="sp-num">' + t.TOTAL_SOLICITUDES + '</td>' +
        '<td><label class="sp-switch"><input type="checkbox" data-activo="' + t.ID + '"' + (+t.ACTIVO ? ' checked' : '') + '><span></span><span class="sp-sr">Activo</span></label></td>' +
        '<td><button type="button" class="sp-btn sp-btn--sm" data-editar="' + t.ID + '">' + SP.icono('edit') + 'Editar</button></td></tr>';
    }).join(''));
  }

  function abrirFormulario(tipo) {
    const t = tipo || { NOMBRE: '', DESCRIPCION: '', PROCESO_ID: '', PLANTILLA: 'LIBRE', REQUIERE_ADJUNTO: 0, EXTENSIONES: st.cat.adjuntos.ext_gestion, ACTIVO: 1, ORDEN: st.tipos.length + 1 };
    const drawer = SP.drawer.abrir({
      angosto: true,
      titulo: tipo ? 'Editar tipo de solicitud' : 'Nuevo tipo de solicitud',
      subtitulo: tipo ? 'Usado en ' + tipo.TOTAL_SOLICITUDES + ' solicitud(es)' : '',
      cuerpo:
        '<form data-form novalidate>' +
        '<div class="sp-field"><label class="sp-req" for="t-nombre">Nombre</label><input id="t-nombre" name="nombre" class="sp-input" maxlength="100" value="' + SP.esc(t.NOMBRE) + '"></div>' +
        '<div class="sp-field"><label for="t-desc">Descripcion</label><textarea id="t-desc" name="descripcion" class="sp-textarea" rows="3" maxlength="500" placeholder="Ayuda al proveedor a elegir el tipo correcto">' + SP.esc(t.DESCRIPCION || '') + '</textarea></div>' +
        '<div class="sp-field"><label class="sp-req" for="t-proc">Proceso que atiende</label><select id="t-proc" name="proceso_id" class="sp-select"><option value="">Seleccione...</option>' +
          (st.cat.procesos || []).map(function (p) {
            return '<option value="' + p.ID + '"' + (+p.ID === +t.PROCESO_ID ? ' selected' : '') + '>' + SP.esc(p.PROCESO) + (p.ORGANIZACION ? ' · Org. ' + SP.esc(p.ORGANIZACION) : '') + '</option>';
          }).join('') + '</select><span class="sp-field__help">Si el proceso tiene organizacion, solo lo veran los proveedores de esa organizacion.</span></div>' +
        '<div class="sp-field"><span class="sp-label sp-req">Plantilla de detalle</span><input type="hidden" name="plantilla" value="' + SP.esc(t.PLANTILLA) + '">' +
          '<div class="sp-choices" role="radiogroup">' + st.cat.plantillas.map(function (p) {
            return '<button type="button" class="sp-choice" role="radio" data-plantilla="' + p.codigo + '" aria-checked="' + (p.codigo === t.PLANTILLA) + '">' +
              '<span class="sp-choice__icon">' + SP.icono(ICONO_PLANTILLA[p.codigo] || 'file') + '</span><span class="sp-choice__title">' + SP.esc(p.nombre) + '</span>' +
              '<span class="sp-choice__desc">' + (p.requiere_excel ? 'Excel: ' + p.columnas.map(function (c) { return c.titulo; }).join(', ') : 'Observacion y adjuntos libres') + '</span></button>';
          }).join('') + '</div></div>' +
        '<div data-solo-libre>' +
          '<div class="sp-field"><label class="sp-switch"><input type="checkbox" name="requiere_adjunto"' + (+t.REQUIERE_ADJUNTO ? ' checked' : '') + '><span></span>Adjunto obligatorio</label></div>' +
          '<div class="sp-field"><label for="t-ext">Extensiones permitidas</label><input id="t-ext" name="extensiones" class="sp-input" value="' + SP.esc((t.EXTENSIONES || []).join(', ')) + '">' +
          '<span class="sp-field__help">Separadas por coma. Vacio = extensiones por defecto.</span></div>' +
        '</div>' +
        '<div class="sp-alert sp-alert--info" data-solo-excel>' + SP.icono('lock') + 'Las plantillas Excel siempre exigen el archivo (.xlsx, .xls).</div>' +
        '<div class="sp-grid-2"><div class="sp-field"><label for="t-orden">Orden</label><input id="t-orden" type="number" min="0" name="orden" class="sp-input" value="' + (+t.ORDEN || 0) + '"></div>' +
        '<div class="sp-field"><span class="sp-label">Estado</span><label class="sp-switch" style="height:36px"><input type="checkbox" name="activo"' + (+t.ACTIVO ? ' checked' : '') + '><span></span>Activo</label></div></div>' +
        '</form>',
      pie: '<button type="button" class="sp-btn sp-btn--ghost" data-cerrar>Cancelar</button><button type="button" class="sp-btn sp-btn--primary" data-guardar>' + SP.icono('check') + 'Guardar</button>'
    });

    const $form = drawer.$el.find('[data-form]');
    const sincronizar = function () {
      const p = $form.find('[name=plantilla]').val();
      const esExcel = (st.cat.plantillas.filter(function (x) { return x.codigo === p; })[0] || {}).requiere_excel;
      $form.find('[data-solo-libre]').prop('hidden', !!esExcel);
      $form.find('[data-solo-excel]').prop('hidden', !esExcel);
    };
    sincronizar();
    $form.on('click', '[data-plantilla]', function () {
      $form.find('[data-plantilla]').attr('aria-checked', 'false');
      $(this).attr('aria-checked', 'true');
      $form.find('[name=plantilla]').val($(this).data('plantilla'));
      sincronizar();
    });
    $form.find('[name=nombre]').trigger('focus');

    drawer.$el.on('click', '[data-guardar]', function () {
      const $b = $(this);
      const datos = {
        id: tipo ? tipo.ID : 0,
        nombre: $.trim($form.find('[name=nombre]').val()),
        descripcion: $.trim($form.find('[name=descripcion]').val()),
        proceso_id: $form.find('[name=proceso_id]').val(),
        plantilla: $form.find('[name=plantilla]').val(),
        requiere_adjunto: $form.find('[name=requiere_adjunto]').is(':checked') ? 1 : 0,
        extensiones: $form.find('[name=extensiones]').val(),
        orden: $form.find('[name=orden]').val(),
        activo: $form.find('[name=activo]').is(':checked') ? 1 : 0
      };
      const errores = {};
      if (datos.nombre.length < 3) { errores.nombre = 'Minimo 3 caracteres'; }
      if (!datos.proceso_id) { errores.proceso_id = 'Seleccione el proceso'; }
      SP.mostrarErrores($form, errores);
      if (Object.keys(errores).length) { return; }

      SP.boton.cargando($b, 'Guardando...');
      SP.api('tipos_guardar', datos).then(function (t) {
        drawer.cerrar();
        SP.toast('Tipo "' + t.NOMBRE + '" guardado', 'ok');
        listar();
      }, function (err) {
        SP.boton.restaurar($b);
        SP.mostrarErrores($form, err.errores);
        SP.toast(err.message, 'error');
      });
    });
  }

})(window, jQuery);

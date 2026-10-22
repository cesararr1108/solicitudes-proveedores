/* =====================================================================
   SOLICITUDES DE PROVEEDORES - Nucleo compartido
   Requiere: jQuery, lib/js/servicios.js (enviarPeticion, subirArchivos)
   Las vistas viven en views/solicitudes-proveedores/, por eso las rutas suben dos niveles.
   ===================================================================== */
(function (window, $) {
  'use strict';

  const SP = window.SP = window.SP || {};

  // Las vistas inyectan window.SP_CONFIG con rutas absolutas (ver sp_layout.php); estos son respaldos
  SP.config = $.extend({
    controlador: '../../controllers/solicitudes-proveedores/SolicitudesProveedoresController.php',
    servicioSubida: '../../models/ServiceUploadFile.php'
  }, window.SP_CONFIG || {});

  /*
   * subirArchivos() de servicios.js usa la ruta fija '../models/ServiceUploadFile.php',
   * pensada para vistas en views/. Como este modulo esta en views/solicitudes-proveedores/,
   * se corrige la URL solo para esa peticion, sin modificar servicios.js.
   */
  $.ajaxPrefilter(function (opciones) {
    if (opciones.url === '../models/ServiceUploadFile.php') {
      opciones.url = SP.config.servicioSubida;
    }
  });

  /* ------------------------------------------------------------------
     API: todas las peticiones pasan por enviarPeticion() de servicios.js
     Resuelve con "datos"; rechaza con { message, errores, httpStatus }
     ------------------------------------------------------------------ */
  SP.api = function (accion, datos) {
    const opciones = $.extend({ link: SP.config.controlador, accion: accion }, datos || {});
    return enviarPeticion(opciones).then(function (r) {
      if (r && r.ok) { return r.datos; }
      return Promise.reject({ message: (r && r.mensaje) || 'Respuesta inesperada del servidor', errores: (r && r.errores) || {} });
    }, function (err) {
      let json = err && err.xhr && err.xhr.responseJSON;
      if (!json && err && err.xhr && err.xhr.responseText) {
        try { json = JSON.parse(err.xhr.responseText); } catch (e) { json = null; }
      }
      if (err && err.httpStatus === 401) {
        SP.toast('Su sesion expiro. Ingrese nuevamente al ADG.', 'error');
      }
      return Promise.reject({
        message: (json && json.mensaje) || (err && err.message) || 'Error de comunicacion',
        errores: (json && json.errores) || {},
        httpStatus: err && err.httpStatus
      });
    });
  };

  /* ------------------------------------------------------------------
     Utilidades
     ------------------------------------------------------------------ */
  /** Contenedor del modulo: drawers, modales y toasts se montan dentro para heredar los estilos/tema. */
  SP.raiz = function () {
    const $app = $('.sp-app').first();
    return $app.length ? $app : $('body');
  };

  SP.esc = function (v) {
    if (v === null || v === undefined) { return ''; }
    return String(v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  SP.fmt = {
    /** '2026-10-22 14:05:00' -> Date */
    parse: function (s) {
      if (!s) { return null; }
      const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
      return m ? new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0)) : null;
    },
    fecha: function (s, conHora) {
      const d = SP.fmt.parse(s);
      if (!d) { return '—'; }
      let txt = d.getDate() + ' ' + MESES[d.getMonth()] + ' ' + d.getFullYear();
      if (conHora !== false) {
        let h = d.getHours();
        const ap = h >= 12 ? 'p. m.' : 'a. m.';
        h = h % 12 || 12;
        txt += ', ' + h + ':' + String(d.getMinutes()).padStart(2, '0') + ' ' + ap;
      }
      return txt;
    },
    relativo: function (s) {
      const d = SP.fmt.parse(s);
      if (!d) { return '—'; }
      const min = Math.round((Date.now() - d.getTime()) / 60000);
      if (min < 1) { return 'hace un momento'; }
      if (min < 60) { return 'hace ' + min + ' min'; }
      if (min < 1440) { return 'hace ' + Math.floor(min / 60) + ' h'; }
      if (min < 2880) { return 'ayer'; }
      if (min < 43200) { return 'hace ' + Math.floor(min / 1440) + ' dias'; }
      return SP.fmt.fecha(s, false);
    },
    /** minutos -> '2 d 4 h' | '3 h 10 min' | '25 min' */
    duracion: function (minutos) {
      const m = Math.max(0, Math.round(+minutos || 0));
      if (m < 60) { return m + ' min'; }
      const h = Math.floor(m / 60);
      if (h < 24) { return h + ' h' + (m % 60 ? ' ' + (m % 60) + ' min' : ''); }
      const d = Math.floor(h / 24);
      return d + ' d' + (h % 24 ? ' ' + (h % 24) + ' h' : '');
    },
    tamano: function (kb) {
      kb = +kb || 0;
      return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(kb)) + ' KB';
    },
    numero: function (n, dec) {
      if (n === null || n === undefined || n === '') { return '—'; }
      return Number(n).toLocaleString('es-CO', { minimumFractionDigits: dec || 0, maximumFractionDigits: dec === undefined ? 2 : dec });
    },
    hoy: function (deltaDias) {
      const d = new Date();
      d.setDate(d.getDate() + (deltaDias || 0));
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
  };

  SP.iniciales = function (nombre) {
    const p = String(nombre || '?').trim().split(/\s+/);
    return ((p[0] || '?')[0] + (p.length > 2 ? p[2][0] : (p[1] || '')[0] || '')).toUpperCase();
  };

  SP.debounce = function (fn, ms) {
    let t;
    return function () {
      const args = arguments, ctx = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, ms || 300);
    };
  };

  /* ------------------------------------------------------------------
     Iconos (SVG en linea, trazo)
     ------------------------------------------------------------------ */
  const ICONOS = {
    plus: '<path d="M12 5v14M5 12h14"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    file: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
    download: '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
    percent: '<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>',
    gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    clip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
    chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
    chart: '<path d="M3 3v18h18"/><path d="M8 16v-5M13 16V8M18 16v-9"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    refresh: '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    alert: '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'
  };
  SP.icono = function (nombre, clase) {
    return '<svg class="sp-icon ' + (clase || '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONOS[nombre] || '') + '</svg>';
  };

  /* ------------------------------------------------------------------
     Estados y componentes pequenos
     ------------------------------------------------------------------ */
  SP.ESTADOS = { PENDIENTE: 'Pendiente', EN_CURSO: 'En curso', FINALIZADO: 'Finalizado' };
  SP.ACCION_ESTADO = { EN_CURSO: 'Iniciar gestion', FINALIZADO: 'Finalizar' };

  SP.badge = function (estado) {
    return '<span class="sp-badge sp-badge--' + SP.esc(estado) + '">' + SP.esc(SP.ESTADOS[estado] || estado) + '</span>';
  };

  /** Antiguedad con semaforo segun SLA (horas). */
  SP.antiguedad = function (minutos, sla, abierta) {
    let clase = '';
    if (abierta !== false && sla) {
      const h = (+minutos || 0) / 60;
      clase = h >= sla.critico ? 'sp-age--critico' : (h >= sla.alerta ? 'sp-age--alerta' : '');
    }
    const titulo = clase === 'sp-age--critico' ? 'Supera ' + sla.critico + ' h' : (clase ? 'Supera ' + sla.alerta + ' h' : '');
    return '<span class="sp-age ' + clase + '" title="' + titulo + '">' + SP.icono('clock') + SP.fmt.duracion(minutos) + '</span>';
  };

  SP.persona = function (nombre, vacio) {
    if (!nombre) { return '<span class="sp-unassigned">' + SP.esc(vacio || 'Sin asignar') + '</span>'; }
    return '<span class="sp-person"><span class="sp-avatar">' + SP.esc(SP.iniciales(nombre)) + '</span>' + SP.esc(nombre) + '</span>';
  };

  SP.vacio = function (titulo, texto, icono) {
    return '<div class="sp-empty"><div class="sp-empty__icon">' + SP.icono(icono || 'inbox') + '</div>' +
      '<div class="sp-empty__title">' + SP.esc(titulo) + '</div><div>' + SP.esc(texto || '') + '</div></div>';
  };

  SP.skeletonFilas = function (columnas, filas) {
    let html = '';
    for (let i = 0; i < (filas || 5); i++) {
      html += '<tr>';
      for (let c = 0; c < columnas; c++) {
        html += '<td><div class="sp-skeleton" style="width:' + (40 + ((i * 7 + c * 13) % 50)) + '%"></div></td>';
      }
      html += '</tr>';
    }
    return html;
  };

  SP.archivoChip = function (a, opciones) {
    opciones = opciones || {};
    const ext = String(a.EXTENSION || a.extension || '').toLowerCase();
    const nombre = a.NOMBRE_ORIGINAL || a.nombre || 'archivo';
    const esImg = /^(png|jpe?g|gif|webp)$/.test(ext);
    const thumb = esImg && (a.URL || a.preview)
      ? '<img src="' + SP.esc(a.preview || a.URL) + '" alt="">'
      : SP.esc(ext.slice(0, 4));
    const tam = a.TAMANO_KB || a.tamano_kb;
    const contenido = '<span class="sp-file__thumb">' + thumb + '</span>' +
      '<span class="sp-file__name" title="' + SP.esc(nombre) + '">' + SP.esc(nombre) + '</span>' +
      (tam ? '<span class="sp-file__size">' + SP.fmt.tamano(tam) + '</span>' : '') +
      (opciones.quitar ? '<button type="button" class="sp-file__x" data-quitar="' + opciones.quitar + '" aria-label="Quitar archivo">' + SP.icono('x') + '</button>' : '');
    if (a.URL) {
      return '<a class="sp-file sp-file--' + SP.esc(ext) + '" href="' + SP.esc(a.URL) + '" target="_blank" rel="noopener">' + contenido + '</a>';
    }
    return '<span class="sp-file sp-file--' + SP.esc(ext) + '">' + contenido + '</span>';
  };

  /* ------------------------------------------------------------------
     Toasts (notificaciones no invasivas)
     ------------------------------------------------------------------ */
  SP.toast = function (mensaje, tipo, ms) {
    let $c = $('.sp-toasts');
    if (!$c.length) { $c = $('<div class="sp-toasts" role="status" aria-live="polite"></div>').appendTo(SP.raiz()); }
    const $t = $('<div class="sp-toast sp-toast--' + (tipo || 'info') + '"><span class="sp-toast__dot"></span><div></div></div>');
    $t.find('div').text(mensaje);
    $c.append($t);
    setTimeout(function () { $t.fadeOut(200, function () { $t.remove(); }); }, ms || (tipo === 'error' ? 6000 : 3500));
  };

  /** Muestra los errores de validacion del servidor en el formulario. */
  SP.mostrarErrores = function ($form, errores) {
    $form.find('.sp-field--invalid').removeClass('sp-field--invalid');
    $form.find('.sp-field__error').remove();
    let primero = null;
    $.each(errores || {}, function (campo, msg) {
      if (typeof msg !== 'string') { return; }
      const $campo = $form.find('[name="' + campo + '"]').closest('.sp-field');
      if ($campo.length) {
        $campo.addClass('sp-field--invalid').append('<div class="sp-field__error">' + SP.esc(msg) + '</div>');
        primero = primero || $campo;
      }
    });
    if (primero) { primero.find('input,select,textarea').first().trigger('focus'); }
  };

  // Al corregir un campo con error, el mensaje desaparece de inmediato
  $(document).on('input change', '.sp-field--invalid input, .sp-field--invalid select, .sp-field--invalid textarea', function () {
    $(this).closest('.sp-field').removeClass('sp-field--invalid').find('.sp-field__error').remove();
  });

  /* ------------------------------------------------------------------
     Drawer lateral: el contexto (la lista) sigue visible detras
     ------------------------------------------------------------------ */
  const pila = [];
  SP.drawer = {
    abrir: function (opciones) {
      const $ov = $('<div class="sp-overlay"></div>');
      const $d = $(
        '<aside class="sp-drawer ' + (opciones.angosto ? 'sp-drawer--narrow' : '') + '" role="dialog" aria-modal="true" tabindex="-1">' +
          '<header class="sp-drawer__head"><div class="sp-drawer__titles"><h2 class="sp-drawer__title"></h2><div class="sp-drawer__sub"></div></div>' +
          '<button type="button" class="sp-btn sp-btn--ghost sp-btn--icon" data-cerrar aria-label="Cerrar">' + SP.icono('x') + '</button></header>' +
          '<div class="sp-drawer__body"></div>' +
          '<footer class="sp-drawer__foot" hidden></footer>' +
        '</aside>'
      );
      $d.find('.sp-drawer__title').text(opciones.titulo || '');
      $d.find('.sp-drawer__sub').html(opciones.subtitulo || '');
      $d.find('.sp-drawer__body').html(opciones.cuerpo || '');
      if (opciones.pie) { $d.find('.sp-drawer__foot').html(opciones.pie).prop('hidden', false); }
      SP.raiz().append($ov, $d);
      const ctx = { $el: $d, $overlay: $ov, alCerrar: opciones.alCerrar, previo: document.activeElement, puedeCerrar: opciones.puedeCerrar };
      pila.push(ctx);
      requestAnimationFrame(function () { $ov.addClass('is-open'); $d.addClass('is-open'); $d.trigger('focus'); });
      $ov.on('click', function () { SP.drawer.cerrar(ctx); });
      $d.on('click', '[data-cerrar]', function () { SP.drawer.cerrar(ctx); });
      $('body').css('overflow', 'hidden');
      ctx.titulo = function (t) { $d.find('.sp-drawer__title').text(t); };
      ctx.subtitulo = function (h) { $d.find('.sp-drawer__sub').html(h); };
      ctx.cuerpo = function (h) { $d.find('.sp-drawer__body').html(h); return $d.find('.sp-drawer__body'); };
      ctx.pie = function (h) { $d.find('.sp-drawer__foot').html(h || '').prop('hidden', !h); return $d.find('.sp-drawer__foot'); };
      ctx.cerrar = function () { SP.drawer.cerrar(ctx); };
      return ctx;
    },
    cerrar: function (ctx) {
      ctx = ctx || pila[pila.length - 1];
      if (!ctx) { return; }
      if (ctx.puedeCerrar && ctx.puedeCerrar() === false) { return; }
      const i = pila.indexOf(ctx);
      if (i >= 0) { pila.splice(i, 1); }
      ctx.$el.removeClass('is-open');
      ctx.$overlay.removeClass('is-open');
      setTimeout(function () { ctx.$el.remove(); ctx.$overlay.remove(); }, 230);
      if (!pila.length) { $('body').css('overflow', ''); }
      if (ctx.previo && ctx.previo.focus) { ctx.previo.focus(); }
      if (ctx.alCerrar) { ctx.alCerrar(); }
    },
    abierto: function () { return pila.length > 0; }
  };

  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && !$('.sp-modal').length && pila.length) { SP.drawer.cerrar(); }
  });

  /* ------------------------------------------------------------------
     Modal de confirmacion (opcionalmente con comentario obligatorio)
     ------------------------------------------------------------------ */
  SP.confirmar = function (opciones) {
    return new Promise(function (resolve) {
      const conTexto = !!opciones.campo;
      const $m = $(
        '<div class="sp-modal" role="alertdialog" aria-modal="true"><div class="sp-modal__box">' +
          '<div class="sp-modal__body"><h3 class="sp-modal__title"></h3><p class="sp-modal__text"></p>' +
          (conTexto ? '<div class="sp-field"><label class="' + (opciones.campo.requerido ? 'sp-req' : '') + '"></label><textarea class="sp-textarea" rows="4"></textarea></div>' : '') +
          '</div><div class="sp-modal__foot"><button type="button" class="sp-btn sp-btn--ghost" data-no>Cancelar</button>' +
          '<button type="button" class="sp-btn sp-btn--primary" data-si></button></div></div></div>'
      );
      $m.find('.sp-modal__title').text(opciones.titulo || 'Confirmar');
      $m.find('.sp-modal__text').text(opciones.mensaje || '');
      $m.find('[data-si]').text(opciones.ok || 'Confirmar');
      if (conTexto) {
        $m.find('label').text(opciones.campo.etiqueta);
        $m.find('textarea').attr('placeholder', opciones.campo.placeholder || '');
      }
      SP.raiz().append($m);
      const $txt = $m.find('textarea');
      ($txt.length ? $txt : $m.find('[data-si]')).trigger('focus');
      function fin(valor) { $m.remove(); resolve(valor); }
      $m.on('click', '[data-no]', function () { fin(null); });
      $m.on('click', function (e) { if (e.target === $m[0]) { fin(null); } });
      $m.on('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); fin(null); } });
      $m.on('click', '[data-si]', function () {
        const valor = conTexto ? $.trim($txt.val()) : true;
        if (conTexto && opciones.campo.requerido && valor.length < (opciones.campo.minimo || 1)) {
          $txt.closest('.sp-field').addClass('sp-field--invalid').find('.sp-field__error').remove().end()
            .append('<div class="sp-field__error">Escriba al menos ' + (opciones.campo.minimo || 1) + ' caracteres</div>');
          $txt.trigger('focus');
          return;
        }
        fin(valor);
      });
    });
  };

  /* ------------------------------------------------------------------
     Gestor de archivos pendientes por subir
     - Arrastrar y soltar, seleccionar y PEGAR (Ctrl+V) pantallazos
     - Sube con subirArchivos() de servicios.js (ServiceUploadFile.php -> FTP)
     ------------------------------------------------------------------ */
  let secuencia = 0;

  function Cargador(opciones) {
    this.opciones = $.extend({ extensiones: [], maxMb: 10, maxArchivos: 10, multiple: true, alCambiar: function () {} }, opciones);
    this.archivos = [];
  }

  Cargador.prototype.extension = function (file) {
    return String(file.name || '').split('.').pop().toLowerCase();
  };

  Cargador.prototype.agregar = function (files) {
    const self = this;
    const o = this.opciones;
    $.each(Array.prototype.slice.call(files || []), function (_, file) {
      const ext = self.extension(file);
      if (o.extensiones.length && o.extensiones.indexOf(ext) === -1) {
        SP.toast('"' + file.name + '" no es un tipo permitido. Use: ' + o.extensiones.join(', '), 'error');
        return;
      }
      if (file.size / 1048576 > o.maxMb) {
        SP.toast('"' + file.name + '" supera ' + o.maxMb + ' MB', 'error');
        return;
      }
      if (!o.multiple) { self.archivos = []; }
      if (self.archivos.length >= o.maxArchivos) {
        SP.toast('Maximo ' + o.maxArchivos + ' archivos', 'error');
        return false;
      }
      const item = { id: ++secuencia, file: file, nombre: file.name, extension: ext, tamano_kb: Math.ceil(file.size / 1024) };
      if (/^image\//.test(file.type) && window.URL) { item.preview = URL.createObjectURL(file); }
      self.archivos.push(item);
    });
    o.alCambiar(this.archivos);
  };

  Cargador.prototype.quitar = function (id) {
    this.archivos = this.archivos.filter(function (a) {
      if (a.id === +id && a.preview) { URL.revokeObjectURL(a.preview); }
      return a.id !== +id;
    });
    this.opciones.alCambiar(this.archivos);
  };

  Cargador.prototype.limpiar = function () {
    this.archivos.forEach(function (a) { if (a.preview) { URL.revokeObjectURL(a.preview); } });
    this.archivos = [];
    this.opciones.alCambiar(this.archivos);
  };

  Cargador.prototype.html = function () {
    return this.archivos.map(function (a) { return SP.archivoChip(a, { quitar: a.id }); }).join('');
  };

  /** Convierte imagenes del portapapeles en archivos 'pantallazo_AAAAMMDD_HHMMSS.png'. */
  Cargador.prototype.desdePortapapeles = function (evento) {
    const datos = (evento.originalEvent || evento).clipboardData;
    if (!datos || !datos.items) { return false; }
    const archivos = [];
    $.each(datos.items, function (i, item) {
      if (item.kind === 'file') {
        const f = item.getAsFile();
        if (!f) { return; }
        if (/^image\//.test(f.type)) {
          const d = new Date();
          const sello = d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0') + '_' +
            String(d.getHours()).padStart(2, '0') + String(d.getMinutes()).padStart(2, '0') + String(d.getSeconds()).padStart(2, '0');
          const ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
          archivos.push(new File([f], 'pantallazo_' + sello + (archivos.length ? '_' + archivos.length : '') + '.' + ext, { type: f.type }));
        } else {
          archivos.push(f);
        }
      }
    });
    if (archivos.length) { this.agregar(archivos); }
    return archivos.length > 0;
  };

  /** Habilita arrastrar/soltar, clic y pegar sobre un contenedor. */
  Cargador.prototype.conectar = function ($zona, $input, $pegar) {
    const self = this;
    $zona.on('dragover', function (e) { e.preventDefault(); $zona.addClass('is-over'); })
      .on('dragleave drop', function () { $zona.removeClass('is-over'); })
      .on('drop', function (e) { e.preventDefault(); self.agregar(e.originalEvent.dataTransfer.files); });
    if ($input) {
      $input.on('change', function () { self.agregar(this.files); this.value = ''; });
    }
    ($pegar || $zona).on('paste', function (e) {
      if (self.desdePortapapeles(e)) { e.preventDefault(); SP.toast('Imagen pegada como adjunto', 'ok', 2000); }
    });
  };

  /**
   * Sube todos los archivos pendientes al FTP.
   * @return Promise<[{nombre, ruta, url, tamano_kb}]>
   */
  Cargador.prototype.subir = function (ruta, alProgreso) {
    const lista = this.archivos.slice();
    const resultado = [];
    let cadena = Promise.resolve();
    lista.forEach(function (a, i) {
      cadena = cadena.then(function () {
        if (alProgreso) { alProgreso(i + 1, lista.length, a); }
        return SP.subirArchivo(a.file, ruta).then(function (r) {
          resultado.push({ nombre: a.nombre, ruta: r.ruta, url: r.url, tamano_kb: a.tamano_kb });
        });
      });
    });
    return cadena.then(function () { return resultado; });
  };

  SP.Cargador = Cargador;

  /** Sube un archivo con nombre unico usando subirArchivos() de servicios.js. */
  SP.subirArchivo = function (file, ruta) {
    const nombreUnico = 'sp_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    let promesa;
    try {
      promesa = subirArchivos(file, { validateSize: false, maxSize: 0, validateExt: false, typesFile: {}, ruta: ruta }, { nuevo_nombre: nombreUnico });
    } catch (e) {
      return Promise.reject({ message: e.message || 'Archivo no valido' });
    }
    return promesa.then(function (r) {
      if (!r || !r.ok) {
        return Promise.reject({ message: 'No fue posible subir "' + file.name + '"' + (r && r.mensaje ? ': ' + r.mensaje : '') });
      }
      return { ruta: r.nombre_archivo, url: r.archivo };
    }, function () {
      return Promise.reject({ message: 'No fue posible subir "' + file.name + '". Verifique su conexion.' });
    });
  };

  /* ------------------------------------------------------------------
     Paginador simple
     ------------------------------------------------------------------ */
  SP.paginador = function (total, pagina, porPagina) {
    const paginas = Math.max(1, Math.ceil(total / porPagina));
    const desde = total ? (pagina - 1) * porPagina + 1 : 0;
    const hasta = Math.min(total, pagina * porPagina);
    return '<div class="sp-pager"><span>' + desde + '–' + hasta + ' de ' + total + '</span><span class="sp-flex">' +
      '<button type="button" class="sp-btn sp-btn--sm" data-pagina="' + (pagina - 1) + '"' + (pagina <= 1 ? ' disabled' : '') + '>Anterior</button>' +
      '<span>Pagina ' + pagina + ' de ' + paginas + '</span>' +
      '<button type="button" class="sp-btn sp-btn--sm" data-pagina="' + (pagina + 1) + '"' + (pagina >= paginas ? ' disabled' : '') + '>Siguiente</button>' +
      '</span></div>';
  };

  SP.boton = {
    cargando: function ($btn, texto) {
      if (!$btn.data('sp-html')) { $btn.data('sp-html', $btn.html()); }
      $btn.prop('disabled', true).html('<span class="sp-spin"></span>' + SP.esc(texto || 'Procesando...'));
    },
    restaurar: function ($btn) {
      if ($btn.data('sp-html')) { $btn.html($btn.data('sp-html')).removeData('sp-html'); }
      $btn.prop('disabled', false);
    }
  };

})(window, jQuery);

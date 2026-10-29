/* =====================================================================
   SOLICITUDES DE PROVEEDORES - Detalle de una solicitud (drawer)
   Lo usan la vista del proveedor y la de mercadeo. Las acciones visibles
   dependen de "permisos", calculados en el servidor (PoliticaAcceso).
   ===================================================================== */
(function (window, $) {
  'use strict';

  const SP = window.SP;

  SP.detalle = {
    /**
     * @param {number} id
     * @param {object} op { catalogos, alCambiar(), alCerrar(), pestana }
     */
    abrir: function (id, op) {
      op = op || {};
      const estado = { id: id, pestana: op.pestana || 'detalle', datos: null, cargador: null, primeraCarga: true };
      const drawer = SP.drawer.abrir({
        completo: true,
        alCerrar: op.alCerrar,
        titulo: 'Solicitud #' + id,
        subtitulo: '<span class="sp-skeleton" style="width:160px;display:inline-block"></span>',
        cuerpo: '<div class="sp-skeleton" style="height:60px;margin-bottom:16px"></div><div class="sp-skeleton" style="width:70%;margin-bottom:10px"></div><div class="sp-skeleton" style="width:50%"></div>',
        puedeCerrar: function () {
          const texto = drawer.$el.find('[name=comentario_nuevo]').val();
          if ((texto && $.trim(texto)) || (estado.cargador && estado.cargador.archivos.length)) {
            return window.confirm('Tiene un comentario sin enviar. ¿Desea descartarlo?');
          }
          return true;
        }
      });
      estado.drawer = drawer;
      estado.op = op;
      cargar(estado);
      return drawer;
    }
  };

  function cargar(estado) {
    return SP.api('ver', { id: estado.id }).then(function (datos) {
      estado.datos = datos;
      marcarNovedades(estado);
      pintar(estado);
      estado.primeraCarga = false;
    }, function (err) {
      estado.drawer.cuerpo(SP.vacio('No fue posible cargar la solicitud', err.message, 'alert'));
    });
  }

  /**
   * Marca como "_nuevo" lo que hizo la contraparte despues de la ultima vez que el usuario abrio la solicitud.
   * Proveedor: lo que hizo mercadeo (sin notas internas). Mercadeo: lo que hizo el proveedor.
   */
  function marcarNovedades(estado) {
    const d = estado.datos;
    const actor = (estado.op.catalogos && estado.op.catalogos.actor) || {};
    const esProveedor = actor.perfil === 'PROVEEDOR';
    const lectura = d.lectura_anterior;
    const esNuevo = function (x) {
      if (+x.USUARIO_ID === +actor.id) { return false; }
      if (esProveedor ? +x.ES_PROVEEDOR === 1 : +x.ES_PROVEEDOR !== 1) { return false; }
      if (esProveedor && (x.EVENTO === 'COMENTARIO_INTERNO' || +x.INTERNO === 1)) { return false; }
      return !lectura || x.FECHA > lectura;
    };
    d.comentarios.forEach(function (c) { c._nuevo = esNuevo(c); });
    d.historial.forEach(function (h) { h._nuevo = esNuevo(h); });
    d.novedades = d.historial.filter(function (h) { return h._nuevo; });
    d.esProveedor = esProveedor;
    // Si hay mensajes nuevos, abrir directamente la conversacion
    if (estado.primeraCarga && !estado.op.pestana && d.comentarios.some(function (c) { return c._nuevo; })) {
      estado.pestana = 'conversacion';
    }
  }

  /** Aviso con lo que cambio desde la ultima visita (asignacion, estado, mensajes). */
  function avisoNovedades(d) {
    if (!d.novedades || !d.novedades.length) { return ''; }
    const hayMensajes = d.comentarios.some(function (c) { return c._nuevo; });
    return '<div class="sp-aviso-nuevo" role="status">' + SP.icono('chat') +
      '<div><b>' + (d.esProveedor ? 'Respuesta del departamento de mercadeo' : 'Novedades del proveedor') + '</b>' +
      '<ul>' + d.novedades.slice().reverse().map(function (h) {
        return '<li>' + textoEvento(h) + ' <span class="sp-muted">· ' + SP.fmt.relativo(h.FECHA) + '</span></li>';
      }).join('') + '</ul></div>' +
      (hayMensajes ? '<button type="button" class="sp-btn sp-btn--sm" data-ir-conversacion>' + SP.icono('chat') + 'Ver mensajes</button>' : '') +
      '</div>';
  }

  function recargar(estado, mensaje) {
    if (mensaje) { SP.toast(mensaje, 'ok'); }
    if (estado.op.alCambiar) { estado.op.alCambiar(); }
    return cargar(estado);
  }

  /* ------------------------------------------------------------------ */

  function pintar(estado) {
    const d = estado.datos;
    const s = d.solicitud;
    const drawer = estado.drawer;
    const comentarios = d.comentarios.length;

    drawer.titulo(s.ASUNTO);
    drawer.subtitulo('<span class="sp-id">#' + s.ID + '</span>' + SP.badge(s.ESTADO) + '<span class="sp-tag">' + SP.esc(s.TIPO) + '</span>' +
      '<span>' + SP.esc(s.PROVEEDOR || ('Proveedor ' + s.CODIGO_SAP_PROVEEDOR)) + '</span>');

    // El contenedor se reutiliza al recargar: quitar manejadores previos para no duplicarlos
    drawer.$el.find('.sp-drawer__body').off();
    const $body = drawer.cuerpo(
      avisoNovedades(d) +
      progreso(s) +
      barraAcciones(d) +
      '<nav class="sp-subnav" role="tablist">' +
        tab('detalle', 'Detalle', null, estado.pestana) +
        tab('conversacion', 'Conversacion', comentarios, estado.pestana) +
        tab('historial', 'Historial', d.historial.length, estado.pestana) +
      '</nav>' +
      '<section data-panel="detalle"' + (estado.pestana !== 'detalle' ? ' hidden' : '') + '>' + panelDetalle(d) + '</section>' +
      '<section data-panel="conversacion"' + (estado.pestana !== 'conversacion' ? ' hidden' : '') + '>' + panelConversacion(d) + '</section>' +
      '<section data-panel="historial"' + (estado.pestana !== 'historial' ? ' hidden' : '') + '>' + panelHistorial(d) + '</section>'
    );

    $body.on('click', '[data-ir-conversacion]', function () {
      $body.find('[data-tab=conversacion]').trigger('click');
    });
    $body.on('click', '[data-tab]', function () {
      estado.pestana = $(this).data('tab');
      $body.find('[data-tab]').attr('aria-selected', 'false');
      $(this).attr('aria-selected', 'true');
      $body.find('[data-panel]').prop('hidden', true).filter('[data-panel="' + estado.pestana + '"]').prop('hidden', false);
      if (estado.pestana === 'conversacion') { $body.find('[name=comentario_nuevo]').trigger('focus'); }
    });

    eventosAcciones(estado, $body);
    eventosDetalle(estado, $body);
    if (d.permisos.comentar) { eventosComposer(estado, $body); }
  }

  function tab(clave, texto, n, actual) {
    return '<button type="button" class="sp-nav__item" role="tab" data-tab="' + clave + '" aria-selected="' + (clave === actual) + '">' +
      texto + (n ? ' <span class="sp-count">' + n + '</span>' : '') + '</button>';
  }

  /** Linea de tiempo de estados: el proveedor ve en que va su solicitud. */
  function progreso(s) {
    const pasos = [
      { e: 'PENDIENTE', t: 'Recibida', f: s.FECHA_CREACION },
      { e: 'EN_CURSO', t: 'En gestion', f: s.FECHA_INICIO_GESTION },
      { e: 'FINALIZADO', t: 'Finalizada', f: s.FECHA_FINALIZACION }
    ];
    const actual = pasos.findIndex(function (p) { return p.e === s.ESTADO; });
    return '<div class="sp-steps" aria-label="Progreso de la solicitud">' + pasos.map(function (p, i) {
      return (i ? '<span class="sp-steps__sep"></span>' : '') +
        '<span class="sp-step-dot ' + (i <= actual ? 'is-active' : '') + '">' + (i < actual || s.ESTADO === 'FINALIZADO' ? SP.icono('check') : i + 1) + '</span>' +
        '<span>' + (i === actual ? '<b>' + p.t + '</b>' : p.t) + (p.f ? '<br><small class="sp-muted">' + SP.fmt.fecha(p.f) + '</small>' : '') + '</span>';
    }).join('') + '</div>';
  }

  function barraAcciones(d) {
    const p = d.permisos;
    const s = d.solicitud;
    if (!p.asignar_a_otros && !p.tomar && !(p.estados_destino || []).length) {
      return '';
    }
    let html = '<div class="sp-actionbar"><span class="sp-muted">Responsable:</span> ' + SP.persona(s.ASIGNADO) + '<span style="flex:1"></span>';

    if (p.asignar_a_otros) {
      html += '<label class="sp-sr" for="sp-asignar">Asignar a</label><select class="sp-select" id="sp-asignar"><option value="">Asignar a...</option>' +
        d.asignables.map(function (g) {
          return '<option value="' + g.ID + '"' + (+g.ID === +s.ASIGNADO_A ? ' selected' : '') + '>' + SP.esc(g.NOMBRE) + (g.ROL ? ' · ' + SP.esc(g.ROL) : '') + '</option>';
        }).join('') + '</select><button type="button" class="sp-btn sp-btn--sm" data-accion="asignar">Asignar</button>';
    }
    if (p.tomar) {
      html += '<button type="button" class="sp-btn sp-btn--sm" data-accion="tomar">' + SP.icono('user') + 'Asignarme</button>';
    }
    (p.estados_destino || []).forEach(function (e) {
      html += '<button type="button" class="sp-btn sp-btn--sm sp-btn--primary" data-accion="estado" data-estado="' + e + '">' +
        SP.icono(e === 'FINALIZADO' ? 'check' : 'arrow') + SP.esc(SP.ACCION_ESTADO[e] || SP.ESTADOS[e]) + '</button>';
    });
    return html + '</div>';
  }

  function eventosAcciones(estado, $body) {
    const s = estado.datos.solicitud;

    $body.on('click', '[data-accion=tomar]', function () {
      const $b = $(this);
      SP.boton.cargando($b, 'Asignando...');
      SP.api('asignar', { id: s.ID }).then(function () {
        recargar(estado, 'La solicitud quedo asignada a usted');
      }, function (err) { SP.boton.restaurar($b); SP.toast(err.message, 'error'); });
    });

    $body.on('click', '[data-accion=asignar]', function () {
      const $b = $(this);
      const usuario = $body.find('#sp-asignar').val();
      if (!usuario) { SP.toast('Seleccione a quien asignar', 'error'); return; }
      SP.boton.cargando($b, 'Asignando...');
      SP.api('asignar', { id: s.ID, usuario_id: usuario }).then(function (r) {
        const actor = (estado.op.catalogos && estado.op.catalogos.actor) || {};
        if (+r.asignado_a === +actor.id) {
          recargar(estado, 'La solicitud quedo asignada a usted');
          return;
        }
        // Ya no es responsable de la solicitud: se cierra el detalle y se actualiza la lista
        SP.toast('Solicitud #' + s.ID + ' asignada a ' + r.asignado_nombre, 'ok');
        if (estado.op.alCambiar) { estado.op.alCambiar(); }
        estado.drawer.cerrar();
      }, function (err) { SP.boton.restaurar($b); SP.toast(err.message, 'error'); });
    });

    $body.on('click', '[data-accion=estado]', function () {
      const $b = $(this);
      const destino = $b.data('estado');
      const finalizar = destino === 'FINALIZADO';
      SP.confirmar({
        titulo: finalizar ? 'Finalizar solicitud #' + s.ID : 'Iniciar gestion de la solicitud #' + s.ID,
        mensaje: finalizar
          ? 'El proveedor vera este comentario de cierre. La solicitud no se podra reabrir.'
          : (s.ASIGNADO_A ? 'La solicitud pasara a "En curso".' : 'La solicitud pasara a "En curso" y quedara asignada a usted.'),
        ok: finalizar ? 'Finalizar' : 'Iniciar gestion',
        campo: { etiqueta: finalizar ? 'Comentario de cierre' : 'Comentario (opcional)', requerido: finalizar, minimo: 5, placeholder: finalizar ? 'Ej.: Descuentos aplicados en SAP desde el 1 de noviembre.' : '' }
      }).then(function (comentario) {
        if (comentario === null) { return; }
        SP.boton.cargando($b, 'Guardando...');
        SP.api('cambiar_estado', { id: s.ID, estado: destino, comentario: comentario === true ? '' : comentario }).then(function () {
          recargar(estado, 'Estado actualizado a "' + SP.ESTADOS[destino] + '"');
        }, function (err) { SP.boton.restaurar($b); SP.toast(err.message, 'error'); });
      });
    });
  }

  /* ---------------------------- Detalle ------------------------------ */

  function panelDetalle(d) {
    const s = d.solicitud;
    const abiertos = s.ESTADO !== 'FINALIZADO';
    const adjuntosIniciales = d.adjuntos.filter(function (a) { return !a.COMENTARIO_ID; });
    const excel = adjuntosIniciales.filter(function (a) { return +a.ES_PLANTILLA === 1; });
    const otros = adjuntosIniciales.filter(function (a) { return +a.ES_PLANTILLA !== 1; });

    let html = '<dl class="sp-meta">' +
      meta('Proveedor', SP.esc(s.PROVEEDOR || '—') + '<div class="sp-cell-sub">SAP ' + SP.esc(s.CODIGO_SAP_PROVEEDOR) + '</div>') +
      meta('Creada por', SP.esc(s.USUARIO) + '<div class="sp-cell-sub">' + SP.esc(s.USUARIO_EMAIL || '') + '</div>') +
      meta('Fecha de creacion', SP.fmt.fecha(s.FECHA_CREACION)) +
      meta('Tipo / proceso', SP.esc(s.TIPO) + '<div class="sp-cell-sub">' + SP.esc(s.PROCESO || '') + '</div>') +
      meta('Responsable', SP.persona(s.ASIGNADO)) +
      (s.EVENTO ? meta('Evento comercial', SP.esc(s.EVENTO)) : '') +
      meta(abiertos ? 'Tiempo abierta' : 'Tiempo de resolucion', SP.fmt.duracion(s.MINUTOS)) +
      '</dl>';

    html += '<div class="sp-section"><h3 class="sp-section__title">Observacion</h3><p class="sp-prose">' + SP.esc(s.OBSERVACION || 'Sin observacion') + '</p></div>';

    if (excel.length || otros.length) {
      html += '<div class="sp-section"><h3 class="sp-section__title">Archivos adjuntos</h3><div class="sp-files">' +
        excel.concat(otros).map(function (a) { return SP.archivoChip(a); }).join('') + '</div></div>';
    }

    if (d.plantilla.codigo !== 'LIBRE') {
      html += '<div class="sp-section"><h3 class="sp-section__title"><span>Detalle de ' + SP.esc(s.TIPO.toLowerCase()) +
        ' <span class="sp-count">' + d.detalle.length + '</span></span>' +
        '<span class="sp-flex">' + (d.detalle.length > 15 ? '<input type="search" class="sp-input" style="height:30px;width:200px" placeholder="Buscar codigo o descripcion" data-filtro-detalle>' : '') +
        '<button type="button" class="sp-btn sp-btn--sm" data-exportar>' + SP.icono('download') + 'Exportar</button></span></h3>' +
        '<div data-tabla-detalle>' + SP.excel.tablaDetalle(d.plantilla.codigo, d.detalle) + '</div></div>';
    }
    return html;
  }

  function meta(titulo, valor) {
    return '<div class="sp-meta__item"><dt>' + titulo + '</dt><dd>' + valor + '</dd></div>';
  }

  function eventosDetalle(estado, $body) {
    const d = estado.datos;
    $body.on('click', '[data-exportar]', function () {
      SP.excel.exportar('solicitud_' + d.solicitud.ID + '_' + d.plantilla.codigo.toLowerCase() + '.xlsx',
        SP.excel.columnasDetalle(d.plantilla.codigo), d.detalle);
    });
    $body.on('input', '[data-filtro-detalle]', SP.debounce(function () {
      const q = $.trim($(this).val()).toUpperCase();
      const filas = !q ? d.detalle : d.detalle.filter(function (f) {
        return Object.keys(f).some(function (k) { return String(f[k] || '').toUpperCase().indexOf(q) !== -1; });
      });
      $body.find('[data-tabla-detalle]').html(SP.excel.tablaDetalle(d.plantilla.codigo, filas));
    }, 200));
  }

  /* ------------------------- Conversacion ---------------------------- */

  function panelConversacion(d) {
    const porComentario = {};
    d.adjuntos.forEach(function (a) {
      if (a.COMENTARIO_ID) { (porComentario[a.COMENTARIO_ID] = porComentario[a.COMENTARIO_ID] || []).push(a); }
    });

    let html = '<div class="sp-thread">';
    if (!d.comentarios.length) {
      html += '<div class="sp-muted" style="padding:6px 0 4px">Aun no hay comentarios.</div>';
    }
    d.comentarios.forEach(function (c) {
      const adj = porComentario[c.ID] || [];
      html += '<article class="sp-msg ' + (+c.INTERNO ? 'sp-msg--interno' : '') + (c._nuevo ? ' sp-msg--nuevo' : '') + '">' +
        '<span class="sp-avatar ' + (+c.ES_PROVEEDOR ? 'sp-avatar--prov' : '') + '">' + SP.esc(SP.iniciales(c.USUARIO)) + '</span>' +
        '<div class="sp-msg__body"><div class="sp-msg__head"><span class="sp-msg__author">' + SP.esc(c.USUARIO) + '</span>' +
        (+c.ES_PROVEEDOR ? '<span class="sp-tag">Proveedor</span>' : '<span class="sp-tag sp-tag--brand">Mercadeo</span>') +
        (+c.INTERNO ? '<span class="sp-tag sp-tag--warn">' + SP.icono('lock') + ' Nota interna</span>' : '') +
        (c._nuevo ? '<span class="sp-tag sp-tag--nuevo">Nuevo</span>' : '') +
        '<span class="sp-msg__time" title="' + SP.esc(SP.fmt.fecha(c.FECHA)) + '">' + SP.fmt.relativo(c.FECHA) + '</span></div>' +
        (c.COMENTARIO ? '<div class="sp-msg__text">' + SP.esc(c.COMENTARIO) + '</div>' : '') +
        (adj.length ? '<div class="sp-files">' + adj.map(function (a) { return SP.archivoChip(a); }).join('') + '</div>' : '') +
        '</div></article>';
    });
    html += '</div>';

    if (d.permisos.comentar) {
      html += '<div class="sp-composer" data-composer>' +
        '<label class="sp-sr" for="sp-comentario">Nuevo comentario</label>' +
        '<textarea id="sp-comentario" name="comentario_nuevo" maxlength="8000" placeholder="Escriba un comentario... Puede pegar un pantallazo con Ctrl+V"></textarea>' +
        '<div class="sp-files" data-archivos></div>' +
        '<div class="sp-composer__bar">' +
          '<label class="sp-btn sp-btn--ghost sp-btn--sm" title="Adjuntar archivos">' + SP.icono('clip') + 'Adjuntar<input type="file" multiple hidden data-input-archivos></label>' +
          (d.permisos.comentar_interno ? '<label class="sp-switch" title="Solo la ve el equipo de mercadeo"><input type="checkbox" data-interno><span></span>Nota interna</label>' : '') +
          '<span class="sp-composer__hint"><span class="sp-kbd">Ctrl</span> + <span class="sp-kbd">V</span> pega imagenes · <span class="sp-kbd">Ctrl</span> + <span class="sp-kbd">Enter</span> envia</span>' +
          '<button type="button" class="sp-btn sp-btn--primary sp-btn--sm" data-enviar>' + SP.icono('chat') + 'Enviar</button>' +
        '</div></div>';
    } else if (d.solicitud.ESTADO === 'FINALIZADO') {
      html += '<div class="sp-alert sp-alert--info">' + SP.icono('lock') + 'La solicitud esta finalizada; no admite mas comentarios.</div>';
    }
    return html;
  }

  function eventosComposer(estado, $body) {
    const cat = estado.op.catalogos || {};
    const adj = cat.adjuntos || {};
    const $composer = $body.find('[data-composer]');
    const $lista = $composer.find('[data-archivos]');

    estado.cargador = new SP.Cargador({
      extensiones: adj.ext_gestion || [],
      maxMb: adj.max_mb || 10,
      maxArchivos: adj.max_por_envio || 10,
      alCambiar: function () { $lista.html(estado.cargador.html()); }
    });
    estado.cargador.conectar($composer, $composer.find('[data-input-archivos]'), $composer.find('textarea'));
    $lista.on('click', '[data-quitar]', function () { estado.cargador.quitar($(this).data('quitar')); });

    $composer.find('[data-interno]').on('change', function () {
      $composer.find('textarea').attr('placeholder', this.checked
        ? 'Nota interna: solo la vera el equipo de mercadeo'
        : 'Escriba un comentario... Puede pegar un pantallazo con Ctrl+V');
    });

    const enviar = function () {
      const $b = $composer.find('[data-enviar]');
      const texto = $.trim($composer.find('textarea').val());
      if (!texto && !estado.cargador.archivos.length) {
        SP.toast('Escriba un comentario o adjunte un archivo', 'error');
        return;
      }
      SP.boton.cargando($b, estado.cargador.archivos.length ? 'Subiendo...' : 'Enviando...');
      estado.cargador.subir(adj.ruta_ftp, function (i, n) { SP.boton.cargando($b, 'Subiendo ' + i + '/' + n); })
        .then(function (subidos) {
          return SP.api('comentar', {
            id: estado.id,
            comentario: texto,
            interno: $composer.find('[data-interno]').is(':checked') ? 1 : 0,
            adjuntos: JSON.stringify(subidos)
          });
        })
        .then(function () {
          estado.cargador.limpiar();
          $composer.find('textarea').val('');
          estado.pestana = 'conversacion';
          recargar(estado, 'Comentario enviado');
        }, function (err) {
          SP.boton.restaurar($b);
          SP.toast(err.message, 'error');
        });
    };
    $composer.on('click', '[data-enviar]', enviar);
    $composer.find('textarea').on('keydown', function (e) {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); enviar(); }
    });
  }

  /* --------------------------- Historial ----------------------------- */

  function panelHistorial(d) {
    if (!d.historial.length) { return SP.vacio('Sin movimientos', '', 'history'); }
    return '<ol class="sp-timeline">' + d.historial.slice().reverse().map(function (h) {
      return '<li' + (h._nuevo ? ' class="is-nuevo"' : '') + '><span class="sp-timeline__dot ' + (h.ESTADO_NUEVO === 'FINALIZADO' ? 'sp-timeline__dot--fin' : (h.EVENTO === 'ESTADO' || h.EVENTO === 'CREADA' ? 'sp-timeline__dot--brand' : '')) + '"></span>' +
        '<div class="sp-timeline__text">' + textoEvento(h) + '</div>' +
        '<div class="sp-timeline__time">' + SP.fmt.fecha(h.FECHA) + (h.DETALLE ? ' · ' + SP.esc(h.DETALLE) : '') + '</div></li>';
    }).join('') + '</ol>';
  }

  function textoEvento(h) {
    const quien = '<b>' + SP.esc(h.USUARIO) + '</b>';
    switch (h.EVENTO) {
      case 'CREADA': return quien + ' creo la solicitud';
      case 'ASIGNADA':
        if (h.ASIGNADO_NUEVO === h.USUARIO && !h.ASIGNADO_ANTERIOR) { return quien + ' tomo la solicitud'; }
        return quien + (h.ASIGNADO_ANTERIOR ? ' reasigno de ' + SP.esc(h.ASIGNADO_ANTERIOR) + ' a ' : ' asigno a ') + '<b>' + SP.esc(h.ASIGNADO_NUEVO) + '</b>';
      case 'ESTADO': return quien + ' cambio el estado ' + SP.badge(h.ESTADO_ANTERIOR) + ' → ' + SP.badge(h.ESTADO_NUEVO);
      case 'COMENTARIO': return quien + ' agrego un comentario';
      case 'COMENTARIO_INTERNO': return quien + ' agrego una nota interna';
      default: return quien + ' ' + SP.esc(h.EVENTO.toLowerCase());
    }
  }

})(window, jQuery);

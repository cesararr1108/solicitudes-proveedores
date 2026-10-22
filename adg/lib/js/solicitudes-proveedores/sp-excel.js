/* =====================================================================
   SOLICITUDES DE PROVEEDORES - Lectura / generacion de Excel (SheetJS)
   Requiere: XLSX (https://cdn.sheetjs.com) y sp-core.js
   ===================================================================== */
(function (window, $) {
  'use strict';

  const SP = window.SP;

  /** Encabezados alternativos aceptados (normalizados: mayusculas, sin tildes ni simbolos). */
  const ALIAS = {
    codigo_material: ['CODIGO', 'MATERIAL', 'CODMATERIAL', 'CODIGOSAP'],
    descuento: ['DTO', 'DESC', 'PORCENTAJE', 'PORCENTAJEDESCUENTO', 'DESCUENTOPORCENTAJE'],
    codigo_regular: ['CODIGOREGULAR', 'MATERIALREGULAR', 'CODREGULAR', 'CODMATERIALREGULAR'],
    cantidad_regular: ['CANTREGULAR', 'CANTIDADREG'],
    codigo_bonificado: ['CODIGOBONIFICADO', 'MATERIALBONIFICADO', 'CODBONIFICADO', 'CODMATERIALBONIFICADO'],
    cantidad_bonificada: ['CANTBONIFICADA', 'CANTIDADBONIFICADO', 'CANTIDADAREGALAR', 'CANTIDADREGALO']
  };

  function normalizar(txt) {
    return String(txt || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  }

  function asegurarLibreria() {
    if (typeof window.XLSX === 'undefined') {
      throw new Error('No se cargo la libreria de Excel (SheetJS). Recargue la pagina.');
    }
  }

  /** Ubica la fila de encabezados (dentro de las primeras 10) y el indice de cada columna. */
  function mapearEncabezados(filas, columnas) {
    for (let r = 0; r < Math.min(10, filas.length); r++) {
      const normal = (filas[r] || []).map(normalizar);
      const indices = {};
      let encontrados = 0;
      columnas.forEach(function (col) {
        const candidatos = [normalizar(col.titulo), normalizar(col.clave)].concat(ALIAS[col.clave] || []);
        const idx = normal.findIndex(function (h) { return h && candidatos.indexOf(h) !== -1; });
        if (idx !== -1) { indices[col.clave] = idx; encontrados++; }
      });
      if (encontrados === columnas.length) { return { fila: r, indices: indices }; }
    }
    return null;
  }

  /** Convierte el valor de una celda segun el tipo de la columna. */
  function valorCelda(crudo, formateado, tipo) {
    if (tipo === 'texto') {
      if (typeof crudo === 'number' && Number.isSafeInteger(crudo)) { return String(crudo); }
      return String(formateado === undefined ? (crudo === undefined ? '' : crudo) : formateado).trim();
    }
    // Numericos: "10%" formateado se respeta como 10; si no, se usa el numero crudo
    if (typeof formateado === 'string' && /%\s*$/.test(formateado)) { return formateado.trim(); }
    if (typeof crudo === 'number') { return crudo; }
    return String(formateado === undefined ? '' : formateado).trim();
  }

  SP.excel = {
    /**
     * Lee la primera hoja y devuelve las filas mapeadas a las claves de la plantilla.
     * @return Promise<{filas: [{fila, clave: valor}], hoja}>
     */
    leer: function (file, columnas) {
      return new Promise(function (resolve, reject) {
        try { asegurarLibreria(); } catch (e) { reject(e); return; }
        const lector = new FileReader();
        lector.onerror = function () { reject(new Error('No fue posible leer el archivo')); };
        lector.onload = function (e) {
          try {
            const libro = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
            const nombreHoja = libro.SheetNames[0];
            const hoja = libro.Sheets[nombreHoja];
            const crudas = XLSX.utils.sheet_to_json(hoja, { header: 1, raw: true, defval: '', blankrows: true });
            const texto = XLSX.utils.sheet_to_json(hoja, { header: 1, raw: false, defval: '', blankrows: true });
            const mapa = mapearEncabezados(texto, columnas);
            if (!mapa) {
              reject(new Error('No se encontraron las columnas requeridas: ' + columnas.map(function (c) { return c.titulo; }).join(', ') +
                '. Descargue la plantilla y respete los encabezados.'));
              return;
            }
            const filas = [];
            for (let r = mapa.fila + 1; r < crudas.length; r++) {
              const f = { fila: r + 1 };
              let vacia = true;
              columnas.forEach(function (col) {
                const i = mapa.indices[col.clave];
                const v = valorCelda((crudas[r] || [])[i], (texto[r] || [])[i], col.tipo);
                if (v !== '' && v !== null) { vacia = false; }
                f[col.clave] = v;
              });
              if (!vacia) { filas.push(f); }
            }
            resolve({ filas: filas, hoja: nombreHoja });
          } catch (err) {
            reject(new Error('El archivo no es un Excel valido'));
          }
        };
        lector.readAsArrayBuffer(file);
      });
    },

    /** Descarga la plantilla vacia con instrucciones. */
    descargarPlantilla: function (plantilla) {
      asegurarLibreria();
      const titulos = plantilla.columnas.map(function (c) { return c.titulo; });
      const datos = XLSX.utils.aoa_to_sheet([titulos]);
      datos['!cols'] = titulos.map(function (t) { return { wch: Math.max(16, t.length + 4) }; });

      const instrucciones = [['Instrucciones - ' + plantilla.nombre], [''],
        ['1. Diligencie la hoja "Datos" desde la fila 2. No cambie los encabezados.'],
        ['2. Los codigos de material deben existir en el maestro de materiales.'],
        ['3. Formatee las columnas de codigo como Texto para conservar los ceros a la izquierda.'], ['']];
      plantilla.columnas.forEach(function (c) {
        instrucciones.push([c.titulo, c.tipo === 'texto' ? 'Texto' : (c.tipo === 'entero' ? 'Numero entero mayor que 0' : 'Numero (porcentaje de 0 a 100)'), 'Ejemplo: ' + c.ejemplo]);
      });
      const hojaInst = XLSX.utils.aoa_to_sheet(instrucciones);
      hojaInst['!cols'] = [{ wch: 34 }, { wch: 34 }, { wch: 18 }];

      const libro = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(libro, datos, 'Datos');
      XLSX.utils.book_append_sheet(libro, hojaInst, 'Instrucciones');
      XLSX.writeFile(libro, 'plantilla_' + plantilla.codigo.toLowerCase() + '.xlsx');
    },

    /** Exporta filas a Excel. columnas: [{clave, titulo}] */
    exportar: function (nombreArchivo, columnas, filas) {
      asegurarLibreria();
      const aoa = [columnas.map(function (c) { return c.titulo; })];
      filas.forEach(function (f) { aoa.push(columnas.map(function (c) { return f[c.clave] === null || f[c.clave] === undefined ? '' : f[c.clave]; })); });
      const hoja = XLSX.utils.aoa_to_sheet(aoa);
      hoja['!cols'] = columnas.map(function (c) { return { wch: /DESCRIPCION/.test(c.titulo) ? 42 : Math.max(14, c.titulo.length + 2) }; });
      const libro = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(libro, hoja, 'Detalle');
      XLSX.writeFile(libro, nombreArchivo);
    },

    /** Columnas visibles del detalle (agrega la descripcion de T_MATERIALES junto a cada codigo). */
    columnasDetalle: function (plantilla) {
      if (plantilla === 'DESCUENTOS') {
        return [
          { clave: 'fila', titulo: 'FILA', num: true },
          { clave: 'codigo_material', titulo: 'CODIGO_MATERIAL' },
          { clave: 'descripcion', titulo: 'DESCRIPCION' },
          { clave: 'descuento', titulo: 'DESCUENTO %', num: true, dec: 2 }
        ];
      }
      if (plantilla === 'BONIFICADOS') {
        return [
          { clave: 'fila', titulo: 'FILA', num: true },
          { clave: 'codigo_regular', titulo: 'CODIGO_REGULAR' },
          { clave: 'descripcion_regular', titulo: 'DESCRIPCION_REGULAR' },
          { clave: 'cantidad_regular', titulo: 'CANT_REGULAR', num: true, dec: 0 },
          { clave: 'codigo_bonificado', titulo: 'CODIGO_BONIFICADO' },
          { clave: 'descripcion_bonificado', titulo: 'DESCRIPCION_BONIFICADO' },
          { clave: 'cantidad_bonificada', titulo: 'CANT_BONIFICADA', num: true, dec: 0 }
        ];
      }
      return [];
    },

    /** Tabla HTML del detalle; marca filas con error/advertencia. */
    tablaDetalle: function (plantilla, filas, errores, advertencias) {
      const cols = SP.excel.columnasDetalle(plantilla);
      const porFila = {};
      (errores || []).forEach(function (e) { (porFila[e.fila] = porFila[e.fila] || { err: [], warn: [] }).err.push(e.mensaje); });
      (advertencias || []).forEach(function (e) { (porFila[e.fila] = porFila[e.fila] || { err: [], warn: [] }).warn.push(e.mensaje); });
      const conMarcas = (errores && errores.length) || (advertencias && advertencias.length);

      let html = '<div class="sp-scroll-y"><table class="sp-table sp-table--compact"><thead><tr>' +
        cols.map(function (c) { return '<th class="' + (c.num ? 'sp-num' : '') + '">' + SP.esc(c.titulo) + '</th>'; }).join('') +
        (conMarcas ? '<th>Observacion</th>' : '') + '</tr></thead><tbody>';
      filas.forEach(function (f) {
        const m = porFila[f.fila];
        html += '<tr class="' + (m && m.err.length ? 'sp-row-error' : (m && m.warn.length ? 'sp-row-warn' : '')) + '">' +
          cols.map(function (c) {
            const v = f[c.clave];
            return '<td class="' + (c.num ? 'sp-num' : '') + '">' + (c.num && v !== '' && v !== null ? SP.fmt.numero(v, c.dec) : SP.esc(v || '')) + '</td>';
          }).join('') +
          (conMarcas ? '<td class="sp-cell-err">' + (m ? SP.esc(m.err.concat(m.warn).join(' · ')) : '') + '</td>' : '') + '</tr>';
      });
      return html + '</tbody></table></div>';
    }
  };

})(window, jQuery);

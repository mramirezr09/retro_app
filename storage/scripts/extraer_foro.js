/* extraer_foro.js
 * Extrae las respuestas de los alumnos hacia el profesor desde una discusion de foro de Moodle.
 *
 * Uso:
 *   1) Abre GRUPO_1.html / GRUPO_2.html en el navegador, o la discusion del foro en Moodle con sesion iniciada.
 *   2) Abre la consola (F12) y pega todo este archivo. Enter.
 *   3) Descarga JSON + XLSX (o CSV si falla SheetJS).
 *      En Moodle logueado (https) ademas te pedira una carpeta para guardar imagenes y adjuntos.
 */
(async function () {
    'use strict';

    if (window.__extraerForo) {
        console.warn('[extraer_foro] Ya hay una extraccion en curso.');
        return;
    }
    window.__extraerForo = true;

    var CONFIG = {
        grupo: '',
        soloDirectasAlProfesor: true,
        descargarArchivos: true,
        incluirAdjuntos: true,
        carpetaImagenes: 'imagenes',
        carpetaAdjuntos: 'adjuntos',
        nombreBase: '',
        hoja: 'Respuestas',
        tambienJSON: false
    };

    var log = function () {
        var a = Array.prototype.slice.call(arguments);
        console.log.apply(console, ['%c[extraer_foro]', 'color:#0a7;font-weight:bold'].concat(a));
    };
    var warn = function () {
        var a = Array.prototype.slice.call(arguments);
        console.warn.apply(console, ['[extraer_foro]'].concat(a));
    };

    try {
        var articles = Array.prototype.slice.call(
            document.querySelectorAll('article.forum-post-container[data-post-id]')
        );
        if (!articles.length) {
            throw new Error('No se encontraron publicaciones. Abre una discusion de foro de Moodle (o su HTML) e intentalo de nuevo.');
        }

        var grupo = (CONFIG.grupo || '').trim();
        if (!grupo) {
            var base0 = (location.pathname.split('/').pop() || '').replace(/\.[^.]+$/, '');
            var sugerido = /grupo|foro/i.test(base0) ? base0.toUpperCase() : 'GRUPO_1';
            grupo = (window.prompt('Nombre del grupo (ej. GRUPO_1):', sugerido) || '').trim();
        }
        if (!grupo) {
            grupo = 'foro';
            warn('Sin nombre de grupo; se usara "foro".');
        }

        var firstInner = document.querySelector('.forumpost.firstpost, .forumpost.starter');
        var professorArticle = (firstInner && firstInner.closest('article.forum-post-container')) || articles[0];
        var professorId = professorArticle.getAttribute('data-post-id');
        var professorName = authorOf(professorArticle);

        function textOf(el) {
            return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
        }
        function normalizeName(s) {
            return (s || '').trim().toLocaleLowerCase('es').replace(/\s+/g, ' ');
        }
        function absolutizar(u) {
            try { return new URL(u, location.href).href; } catch (e) { return u; }
        }
        function nombreArchivo(u) {
            try {
                var p = new URL(u).pathname.split('/').filter(Boolean).pop() || 'archivo';
                return decodeURIComponent(p);
            } catch (e) { return 'archivo'; }
        }
        function uniqueBy(arr, keyFn) {
            var seen = {}, out = [];
            for (var i = 0; i < arr.length; i++) {
                var k = keyFn(arr[i]);
                if (seen[k]) { continue; }
                seen[k] = true;
                out.push(arr[i]);
            }
            return out;
        }
        function authorOf(article) {
            var link = article.querySelector('header a[href*="user/view.php"]');
            if (link && link.textContent.trim()) { return link.textContent.trim(); }
            var fp = article.querySelector('.forumpost');
            var label = fp ? (fp.getAttribute('aria-label') || '') : '';
            var m = label.match(/\bpor\s+(.+)$/i);
            return m ? m[1].trim() : '';
        }
        function subjectOf(article) {
            var el = article.querySelector('h3[data-region-content="forum-post-core-subject"]');
            return textOf(el);
        }
        var IMG_EXT = /\.(png|jpe?g|gif|webp|bmp|svg|heif?|avif|tiff?)$/i;
        function esImagen(u) {
            try { return IMG_EXT.test(new URL(u).pathname); } catch (e) { return IMG_EXT.test(String(u).split('?')[0]); }
        }
        function esActivoDelPost(u, id) {
            return u.indexOf('/mod_forum/post/' + id + '/') !== -1 ||
                u.indexOf('/mod_forum/attachment/' + id + '/') !== -1;
        }

        function parsePost(article) {
            var id = article.getAttribute('data-post-id');
            var forumpost = article.querySelector('.forumpost');
            var esProfesor = (article === professorArticle) ||
                !!(forumpost && forumpost.classList.contains('firstpost'));
            var timeEl = article.querySelector('header time');
            var srTexts = Array.prototype.slice.call(article.querySelectorAll('span.sr-only'))
                .map(function (s) { return s.textContent.trim(); });
            var respondeA = '';
            for (var i = 0; i < srTexts.length; i++) {
                if (/^En respuesta a /i.test(srTexts[i])) {
                    respondeA = srTexts[i].replace(/^En respuesta a /i, '').trim();
                    break;
                }
            }
            var content = article.querySelector('#post-content-' + id) ||
                article.querySelector('.post-content-container');

            var imagenes = uniqueBy(
                Array.prototype.slice.call(article.querySelectorAll('img[src]'))
                    .map(function (img) {
                        return { url: absolutizar(img.getAttribute('src') || ''), alt: img.getAttribute('alt') || '' };
                    })
                    .filter(function (x) { return x.url && esActivoDelPost(x.url, id) && esImagen(x.url); }),
                function (it) { return it.url; }
            ).map(function (it) {
                return { url: it.url, alt: it.alt, archivo: nombreArchivo(it.url), ruta_local: '' };
            });

            var urlsImagen = {};
            imagenes.forEach(function (it) { urlsImagen[it.url] = true; });

            var anclas = uniqueBy(
                Array.prototype.slice.call(article.querySelectorAll('a[href]'))
                    .map(function (a) {
                        return { url: absolutizar(a.getAttribute('href') || ''), etiqueta: a.textContent.replace(/\s+/g, ' ').trim() };
                    })
                    .filter(function (x) { return x.url && esActivoDelPost(x.url, id); }),
                function (it) { return it.url; }
            );

            anclas.forEach(function (it) {
                if (urlsImagen[it.url] || !esImagen(it.url)) { return; }
                urlsImagen[it.url] = true;
                imagenes.push({ url: it.url, alt: it.etiqueta, archivo: nombreArchivo(it.url), ruta_local: '' });
            });

            var adjuntos = anclas
                .filter(function (it) { return !urlsImagen[it.url] && !esImagen(it.url); })
                .map(function (it) {
                    return { url: it.url, etiqueta: it.etiqueta, archivo: nombreArchivo(it.url), ruta_local: '' };
                });

            return {
                post_id: id,
                alumno: authorOf(article),
                es_profesor: esProfesor,
                responde_a: respondeA,
                es_directa_al_profesor: !esProfesor && normalizeName(respondeA) === normalizeName(professorName),
                fecha: timeEl ? (timeEl.getAttribute('datetime') || '') : '',
                fecha_texto: textOf(timeEl),
                asunto: subjectOf(article),
                texto: content
                    ? content.innerText.replace(/\u00a0/g, ' ')
                        .replace(/[ \t]+\n/g, '\n')
                        .replace(/\n{3,}/g, '\n\n')
                        .trim()
                    : '',
                html: content ? content.innerHTML.trim() : '',
                imagenes: imagenes,
                adjuntos: adjuntos
            };
        }

        var allPosts = articles.map(parsePost);
        var profesorPost = null;
        for (var p = 0; p < allPosts.length; p++) {
            if (allPosts[p].post_id === professorId) { profesorPost = allPosts[p]; break; }
        }
        if (!profesorPost) { profesorPost = allPosts[0]; }

        var respuestas = allPosts.filter(function (post) {
            if (post.post_id === professorId) { return false; }
            if (CONFIG.soloDirectasAlProfesor) { return post.es_directa_al_profesor; }
            return true;
        });

        function sanitize(s) {
            return String(s).replace(/[\\/:*?"<>|]+/g, '_').replace(/\s+/g, ' ').trim() || 'archivo';
        }
        function sameOrigin(u) {
            try { return new URL(u, location.href).origin === location.origin; } catch (e) { return false; }
        }
        function descargarArchivo(blob, filename) {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
        }

        var dirRoot = null;
        var puedeDescargar = CONFIG.descargarArchivos &&
            location.protocol.indexOf('http') === 0 &&
            typeof window.showDirectoryPicker === 'function';

        if (CONFIG.descargarArchivos && !puedeDescargar) {
            if (location.protocol.indexOf('http') !== 0) {
                warn('Estas en file:// : no se descargan archivos, solo se listan las URLs. Ejecuta en Moodle logueado para bajarlos.');
            } else {
                warn('Este navegador no soporta showDirectoryPicker(); no se descargaran archivos.');
            }
        }

        async function pickDir() {
            try {
                return await window.showDirectoryPicker({ mode: 'readwrite' });
            } catch (e) {
                if (e && e.name === 'AbortError') { return null; }
                warn('showDirectoryPicker error:', e.message);
                return null;
            }
        }

        async function guardarEnDisco(parts, filename, blob) {
            var h = dirRoot;
            for (var i = 0; i < parts.length; i++) {
                h = await h.getDirectoryHandle(sanitize(parts[i]), { create: true });
            }
            var fh = await h.getFileHandle(sanitize(filename), { create: true });
            var w = await fh.createWritable();
            await w.write(blob);
            await w.close();
        }

        async function descargar(items, carpetaBase, postId) {
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                try {
                    if (!sameOrigin(it.url)) { warn('Se omite (otro origen):', it.url); continue; }
                    var res = await fetch(it.url, { credentials: 'include' });
                    var ct = (res.headers.get('content-type') || '').toLowerCase();
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    if (ct.indexOf('text/html') !== -1) { throw new Error('Sesion no activa (se recibio HTML de login).'); }
                    var blob = await res.blob();
                    var parts = [carpetaBase, grupo, String(postId)];
                    await guardarEnDisco(parts, it.archivo, blob);
                    it.ruta_local = carpetaBase + '/' + grupo + '/' + postId + '/' + it.archivo;
                } catch (e) {
                    warn('No se pudo descargar', it.url, '->', e.message);
                }
            }
        }

        if (puedeDescargar) {
            log('Elige la carpeta donde guardar imagenes y adjuntos...');
            dirRoot = await pickDir();
            if (!dirRoot) { warn('No se eligio carpeta: no se descargaran archivos.'); }
        }

        if (dirRoot) {
            log('Descargando recursos...');
            for (var r = 0; r < respuestas.length; r++) {
                await descargar(respuestas[r].imagenes, CONFIG.carpetaImagenes, respuestas[r].post_id);
                if (CONFIG.incluirAdjuntos) {
                    await descargar(respuestas[r].adjuntos, CONFIG.carpetaAdjuntos, respuestas[r].post_id);
                }
            }
            log('Recursos descargados.');
        }

        var totalImagenes = 0, totalAdjuntos = 0;
        respuestas.forEach(function (post) {
            totalImagenes += post.imagenes.length;
            if (CONFIG.incluirAdjuntos) { totalAdjuntos += post.adjuntos.length; }
        });

        var payload = {
            generado: new Date().toISOString(),
            origen: location.href,
            grupo: grupo,
            profesor: profesorPost ? {
                nombre: profesorPost.alumno,
                post_id: profesorPost.post_id,
                asunto: profesorPost.asunto,
                fecha: profesorPost.fecha,
                consigna_texto: profesorPost.texto,
                consigna_html: profesorPost.html
            } : null,
            totales: {
                publicaciones: allPosts.length,
                respuestas_incluidas: respuestas.length,
                imagenes: totalImagenes,
                adjuntos: totalAdjuntos
            },
            respuestas: respuestas
        };

        var base = (CONFIG.nombreBase || ('foro_' + grupo + '_respuestas')).replace(/\s+/g, '_');

        if (CONFIG.tambienJSON) {
            descargarArchivo(
                new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }),
                base + '.json'
            );
        }

        var headers = [
            'Grupo', 'Post ID', 'Alumno', 'Fecha', 'Asunto', 'Respuesta',
            'Responde a', '# Imagenes', 'Imagenes', '# Adjuntos', 'Adjuntos'
        ];
        var rows = respuestas.map(function (post) {
            return {
                'Grupo': grupo,
                'Post ID': post.post_id,
                'Alumno': post.alumno,
                'Fecha': post.fecha,
                'Asunto': post.asunto,
                'Respuesta': post.texto,
                'Responde a': post.responde_a,
                '# Imagenes': post.imagenes.length,
                'Imagenes': post.imagenes.map(function (i) { return i.ruta_local || i.url; }).join(' | '),
                '# Adjuntos': post.adjuntos.length,
                'Adjuntos': post.adjuntos.map(function (a) { return a.ruta_local || a.url; }).join(' | ')
            };
        });

        var matrix = [headers].concat(rows.map(function (row) {
            return headers.map(function (h) { return row[h]; });
        }));
        var colWidths = [10, 10, 28, 22, 40, 90, 28, 10, 70, 10, 70];

        var CRC_TABLE = (function () {
            var table = [], c, n, k;
            for (n = 0; n < 256; n++) {
                c = n;
                for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
                table[n] = c >>> 0;
            }
            return table;
        })();
        function crc32(bytes) {
            var c = 0xFFFFFFFF;
            for (var i = 0; i < bytes.length; i++) { c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8); }
            return (c ^ 0xFFFFFFFF) >>> 0;
        }
        function u16(n) { return [n & 0xFF, (n >>> 8) & 0xFF]; }
        function u32(n) { return [n & 0xFF, (n >>> 8) & 0xFF, (n >>> 16) & 0xFF, (n >>> 24) & 0xFF]; }
        function juntar(partes) {
            var total = 0, i;
            for (i = 0; i < partes.length; i++) { total += partes[i].length; }
            var out = new Uint8Array(total), pos = 0;
            for (i = 0; i < partes.length; i++) {
                var p = partes[i] instanceof Uint8Array ? partes[i] : new Uint8Array(partes[i]);
                out.set(p, pos);
                pos += p.length;
            }
            return out;
        }
        function zipSync(files) {
            var te = new TextEncoder();
            var locales = [], centrales = [], offset = 0;
            files.forEach(function (f) {
                var name = te.encode(f.name);
                var data = f.data;
                var crc = crc32(data);
                var lfh = [].concat(u32(0x04034b50), u16(20), u16(0x0800), u16(0), u16(0), u16(0),
                    u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0));
                locales.push(new Uint8Array(lfh), name, data);
                var cdh = [].concat(u32(0x02014b50), u16(20), u16(20), u16(0x0800), u16(0), u16(0), u16(0),
                    u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0), u16(0), u16(0), u16(0), u32(0), u32(offset));
                centrales.push(new Uint8Array(cdh), name);
                offset += lfh.length + name.length + data.length;
            });
            var centralSize = 0;
            centrales.forEach(function (p) { centralSize += p.length; });
            var eocd = [].concat(u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length),
                u32(centralSize), u32(offset), u16(0));
            return juntar(locales.concat(centrales, [new Uint8Array(eocd)]));
        }
        function escXml(s) {
            return String(s === null || s === undefined ? '' : s)
                .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&apos;');
        }
        function colName(n) {
            var s = '';
            n += 1;
            while (n > 0) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); }
            return s;
        }
        function buildXlsx(nombreHoja, data, widths) {
            var te = new TextEncoder();
            var filas = '';
            for (var r = 0; r < data.length; r++) {
                var celdas = '';
                for (var c = 0; c < data[r].length; c++) {
                    var ref = colName(c) + (r + 1);
                    var v = data[r][c];
                    if (typeof v === 'number' && isFinite(v)) {
                        celdas += '<c r="' + ref + '"><v>' + v + '</v></c>';
                    } else {
                        celdas += '<c r="' + ref + '" t="inlineStr"><is><t xml:space="preserve">' + escXml(v) + '</t></is></c>';
                    }
                }
                filas += '<row r="' + (r + 1) + '">' + celdas + '</row>';
            }
            var cols = '';
            if (widths) {
                cols = '<cols>';
                for (var w = 0; w < widths.length; w++) {
                    cols += '<col min="' + (w + 1) + '" max="' + (w + 1) + '" width="' + widths[w] + '" customWidth="1"/>';
                }
                cols += '</cols>';
            }
            var header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
            var hoja = header +
                '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
                cols + '<sheetData>' + filas + '</sheetData></worksheet>';
            var contentTypes = header +
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
                '<Default Extension="xml" ContentType="application/xml"/>' +
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' +
                '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' +
                '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' +
                '</Types>';
            var rels = header +
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' +
                '</Relationships>';
            var workbook = header +
                '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' +
                'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
                '<sheets><sheet name="' + escXml(nombreHoja) + '" sheetId="1" r:id="rId1"/></sheets></workbook>';
            var wbRels = header +
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' +
                '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' +
                '</Relationships>';
            var styles = header +
                '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
                '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>' +
                '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' +
                '<borders count="1"><border/></borders>' +
                '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
                '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' +
                '</styleSheet>';
            return zipSync([
                { name: '[Content_Types].xml', data: te.encode(contentTypes) },
                { name: '_rels/.rels', data: te.encode(rels) },
                { name: 'xl/workbook.xml', data: te.encode(workbook) },
                { name: 'xl/_rels/workbook.xml.rels', data: te.encode(wbRels) },
                { name: 'xl/styles.xml', data: te.encode(styles) },
                { name: 'xl/worksheets/sheet1.xml', data: te.encode(hoja) }
            ]);
        }

        var xlsxBytes = buildXlsx(CONFIG.hoja.substring(0, 31), matrix, colWidths);
        descargarArchivo(
            new Blob([xlsxBytes], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
            base + '.xlsx'
        );

        log('Grupo:', grupo);
        log('Profesor:', profesorPost && profesorPost.alumno ? profesorPost.alumno : '(no detectado)');
        log('Respuestas incluidas:', respuestas.length, 'de', allPosts.length - 1);
        log('Imagenes:', totalImagenes, '- Adjuntos:', totalAdjuntos);
        if (dirRoot) { log('Archivos guardados en las carpetas elegidas.'); }
        log('Listo. XLSX descargado: ' + base + '.xlsx' + (CONFIG.tambienJSON ? ' (+ JSON)' : ''));
    } catch (e) {
        console.error('[extraer_foro] Error:', e);
    } finally {
        window.__extraerForo = false;
    }
})();

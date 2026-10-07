/*
 * Botón "Extraer auto" (bookmarklet).
 * Se ejecuta dentro de la página del auto que el usuario ya abrió, por eso
 * no lo bloquean los sistemas anti-robots. Junta los datos y los envía al
 * servidor. __DESTINO__ lo reemplaza la página "Instalar botón".
 */
(function () {
  var DESTINO = '__DESTINO__';

  if (location.origin === new URL(DESTINO).origin) {
    alert('Usa este botón estando en la página del auto (BE FORWARD, Copart o IAAI).');
    return;
  }

  var aviso = document.createElement('div');
  aviso.textContent = 'Extrayendo datos del auto…';
  aviso.style.cssText = 'position:fixed;top:16px;right:16px;z-index:2147483647;background:#111;color:#fff;'
    + 'padding:14px 18px;border-radius:10px;font:15px/1.4 Arial,sans-serif;box-shadow:0 4px 20px rgba(0,0,0,.4)';
  document.body.appendChild(aviso);

  var txt = function (el) { return el ? (el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim() : ''; };

  /* ---- pares etiqueta / valor ---- */
  var pares = [];
  var add = function (a, b) {
    a = (a || '').trim(); b = (b || '').trim();
    if (a && b && a.length <= 45 && b.length <= 200 && a !== b) pares.push([a, b]);
  };
  document.querySelectorAll('tr').forEach(function (tr) {
    var c = Array.prototype.filter.call(tr.children, function (x) { return x.tagName === 'TH' || x.tagName === 'TD'; });
    for (var i = 0; i + 1 < c.length; i += 2) add(txt(c[i]), txt(c[i + 1]));
  });
  document.querySelectorAll('dt').forEach(function (dt) {
    var dd = dt.nextElementSibling;
    if (dd && dd.tagName === 'DD') add(txt(dt), txt(dd));
  });
  document.querySelectorAll('label, span, div, strong, b, p, li, h4, h5, h6').forEach(function (el) {
    if (el.children.length > 2) return;
    var t = txt(el);
    if (!t || t.length > 45 || t.slice(-1) !== ':') return;
    var v = el.nextElementSibling ? txt(el.nextElementSibling) : '';
    if (!v && el.parentElement) {
      var p = txt(el.parentElement);
      if (p.indexOf(t) === 0) v = p.slice(t.length);
    }
    add(t, v);
  });
  document.querySelectorAll('li, p, span, div').forEach(function (el) {
    if (el.children.length > 3) return;
    var m = txt(el).match(/^([A-Za-z][A-Za-z #./&()-]{1,40}):\s*(.{1,120})$/);
    if (m) add(m[1] + ':', m[2]);
  });

  /* ---- imágenes ---- */
  var imgs = [];
  var push = function (u) {
    if (u && u.indexOf('data:') !== 0) { try { imgs.push(new URL(u, location.href).href); } catch (e) {} }
  };
  var mayorSrcset = function (s) {
    if (!s) return null;
    var mejor = null, ancho = -1;
    s.split(',').forEach(function (p) {
      var x = p.trim().split(/\s+/), n = parseFloat(x[1]) || 0;
      if (n >= ancho) { ancho = n; mejor = x[0]; }
    });
    return mejor;
  };
  document.querySelectorAll('img, source').forEach(function (im) {
    ['data-zoom-image', 'data-large', 'data-full', 'data-original', 'data-src', 'data-lazy', 'src']
      .forEach(function (a) { push(im.getAttribute(a)); });
    push(im.currentSrc);
    push(mayorSrcset(im.getAttribute('srcset') || im.getAttribute('data-srcset')));
  });
  document.querySelectorAll('a[href]').forEach(function (a) {
    if (/\.(jpe?g|png|webp)(\?|$)/i.test(a.getAttribute('href'))) push(a.getAttribute('href'));
  });
  document.querySelectorAll('meta[property="og:image"], meta[name="twitter:image"]')
    .forEach(function (m) { push(m.content); });
  try {
    performance.getEntriesByType('resource').forEach(function (r) {
      if (r.initiatorType === 'img' || /\.(jpe?g|png|webp)(\?|$)/i.test(r.name) || /resizer/i.test(r.name)) push(r.name);
    });
  } catch (e) {}
  imgs = imgs.filter(function (u, i) { return imgs.indexOf(u) === i; });

  /* ---- datos JSON incrustados ---- */
  var jsons = [], total = 0;  /* tope total ~3,5 MB: el servidor acepta hasta ~5 MB */
  document.querySelectorAll('script[type="application/ld+json"], script[type="application/json"]').forEach(function (s) {
    var t = s.textContent;
    if (total + t.length < 3500000) { jsons.push(t); total += t.length; }
  });

  /* ---- Copart: consulta su API interna desde la misma página ---- */
  var extras = [];
  var lote = location.hostname.indexOf('copart.') >= 0 && location.pathname.match(/\/lot\/(\d+)/);
  if (lote) {
    extras.push('/public/data/lotdetails/solr/' + lote[1]);
    extras.push('/public/data/lotdetails/solr/lotImages/' + lote[1] + '/USA');
  }
  var pendientes = extras.map(function (u) {
    return fetch(u, { credentials: 'include', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.text(); })
      .then(function (t) { if (total + t.length < 3500000) { jsons.push(t); total += t.length; } })
      .catch(function () {});
  });

  var og = document.querySelector('meta[property="og:title"]');

  Promise.all(pendientes).then(function () {
    var datos = JSON.stringify({
      url: location.href, titulo: document.title, h1: txt(document.querySelector('h1')),
      og_titulo: og ? og.content : '', pares: pares.slice(0, 2000), imagenes: imgs.slice(0, 3000), jsons: jsons
    });
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = DESTINO;
    f.acceptCharset = 'UTF-8';
    f.enctype = 'multipart/form-data';
    f.style.display = 'none';
    [['datos', datos]].forEach(function (c) {
      var i = document.createElement('textarea');
      i.name = c[0]; i.value = c[1]; f.appendChild(i);
    });
    document.body.appendChild(f);
    aviso.textContent = 'Enviando al extractor…';
    var enviado = false;
    window.addEventListener('beforeunload', function () { enviado = true; });
    f.submit();

    /* Si el sitio no permite enviar el formulario, se muestran los datos para copiarlos a mano */
    setTimeout(function () {
      if (enviado) return;
      aviso.innerHTML = '';
      aviso.style.maxWidth = '420px';
      var p = document.createElement('p');
      p.textContent = 'Este sitio no dejó enviar los datos. Copia este texto y pégalo en el extractor, '
        + 'en "Pegar datos del botón":';
      var ta = document.createElement('textarea');
      ta.value = datos; ta.style.cssText = 'width:100%;height:120px;margin-top:8px';
      var b = document.createElement('button');
      b.textContent = 'Copiar'; b.style.cssText = 'margin-top:8px;padding:6px 14px';
      b.onclick = function () { ta.select(); document.execCommand('copy'); b.textContent = '¡Copiado!'; };
      aviso.appendChild(p); aviso.appendChild(ta); aviso.appendChild(b);
    }, 8000);
  });
})();

"""Abre la página en un navegador real (Edge/Chrome del PC) y captura su contenido.

Se usa un navegador real porque Copart e IAAI arman la página con JavaScript y
tienen protección anti-bots. Se guarda un perfil propio para que las cookies
se mantengan entre usos (eso reduce los bloqueos).
"""

import json
import os
import time
from dataclasses import dataclass, field

from playwright.sync_api import sync_playwright

# Recolecta del DOM: pares etiqueta/valor, imágenes, JSON incrustados y títulos.
JS_EXTRAER = r"""
() => {
  const txt = el => (el ? (el.innerText || el.textContent || '') : '').replace(/\s+/g, ' ').trim();
  const pares = [];
  const add = (a, b) => { a = (a || '').trim(); b = (b || '').trim();
    if (a && b && a.length <= 45 && b.length <= 200 && a !== b) pares.push([a, b]); };

  // Tablas: <th>/<td> en pares consecutivos (sirve para tablas de 2 y 4 columnas)
  document.querySelectorAll('tr').forEach(tr => {
    const celdas = Array.from(tr.children).filter(c => c.tagName === 'TH' || c.tagName === 'TD');
    for (let i = 0; i + 1 < celdas.length; i += 2) add(txt(celdas[i]), txt(celdas[i + 1]));
  });
  // Listas de definición
  document.querySelectorAll('dt').forEach(dt => {
    const dd = dt.nextElementSibling;
    if (dd && dd.tagName === 'DD') add(txt(dt), txt(dd));
  });
  // Elementos tipo "Etiqueta:" seguidos del valor
  document.querySelectorAll('label, span, div, strong, b, p, li, h4, h5, h6').forEach(el => {
    if (el.children.length > 2) return;
    const t = txt(el);
    if (!t || t.length > 45 || !t.endsWith(':')) return;
    let valor = '';
    let sig = el.nextElementSibling;
    if (sig) valor = txt(sig);
    if (!valor && el.parentElement) {
      const padre = txt(el.parentElement);
      if (padre.startsWith(t)) valor = padre.slice(t.length);
    }
    add(t, valor);
  });
  // "Etiqueta: valor" en un mismo elemento simple
  document.querySelectorAll('li, p, span, div').forEach(el => {
    if (el.children.length > 3) return;
    const t = txt(el);
    const m = t.match(/^([A-Za-z][A-Za-z #./&()-]{1,40}):\s*(.{1,120})$/);
    if (m) add(m[1] + ':', m[2]);
  });

  // Imágenes
  const imgs = [];
  const push = u => { if (u && !u.startsWith('data:')) { try { imgs.push(new URL(u, location.href).href); } catch (e) {} } };
  const mayorSrcset = s => {
    if (!s) return null;
    let mejor = null, ancho = -1;
    s.split(',').forEach(p => { const [u, w] = p.trim().split(/\s+/); const n = parseFloat(w) || 0;
      if (n >= ancho) { ancho = n; mejor = u; } });
    return mejor;
  };
  document.querySelectorAll('img, source').forEach(im => {
    ['data-zoom-image', 'data-large', 'data-full', 'data-original', 'data-src', 'data-lazy', 'src']
      .forEach(a => push(im.getAttribute(a)));
    push(im.currentSrc);
    push(mayorSrcset(im.getAttribute('srcset') || im.getAttribute('data-srcset')));
  });
  document.querySelectorAll('a[href]').forEach(a => {
    if (/\.(jpe?g|png|webp)(\?|$)/i.test(a.getAttribute('href'))) push(a.getAttribute('href'));
  });
  document.querySelectorAll('meta[property="og:image"], meta[name="twitter:image"]').forEach(m => push(m.content));

  const scripts = [];
  document.querySelectorAll('script[type="application/ld+json"], script[type="application/json"]')
    .forEach(s => { if (s.textContent.length < 3000000) scripts.push(s.textContent); });

  const meta = n => { const m = document.querySelector(`meta[property="${n}"]`); return m ? m.content : ''; };
  return {
    titulo: document.title || '',
    h1: txt(document.querySelector('h1')),
    og_titulo: meta('og:title'),
    pares, imagenes: imgs, scripts,
  };
}
"""

BLOQUEOS = ("pardon our interruption", "access denied", "incapsula", "request unsuccessful",
            "are you a robot", "just a moment", "attention required", "verify you are human")


@dataclass
class Captura:
    url: str
    url_final: str = ""
    titulo: str = ""
    h1: str = ""
    og_titulo: str = ""
    pares: list = field(default_factory=list)
    imagenes_dom: list = field(default_factory=list)
    imagenes_red: list = field(default_factory=list)
    jsons: list = field(default_factory=list)  # [(url, data)]


def carpeta_perfil():
    base = os.environ.get("LOCALAPPDATA") or os.path.expanduser("~/.local/share")
    ruta = os.path.join(base, "ExtractorAutos", "perfil-navegador")
    os.makedirs(ruta, exist_ok=True)
    return ruta


def _abrir_contexto(p, headless):
    opciones = dict(
        headless=headless,
        viewport={"width": 1366, "height": 900},
        locale="en-US",
        args=["--disable-blink-features=AutomationControlled"],
    )
    ejecutable = os.environ.get("EXTRACTOR_NAVEGADOR")
    if ejecutable:
        return p.chromium.launch_persistent_context(carpeta_perfil(), executable_path=ejecutable, **opciones)
    ultimo_error = None
    for canal in ("msedge", "chrome", None):
        try:
            if canal:
                return p.chromium.launch_persistent_context(carpeta_perfil(), channel=canal, **opciones)
            return p.chromium.launch_persistent_context(carpeta_perfil(), **opciones)
        except Exception as e:  # el canal no está instalado: probar el siguiente
            ultimo_error = e
    raise RuntimeError(
        "No se encontró Microsoft Edge ni Google Chrome en este equipo. "
        f"Instala uno de los dos e inténtalo de nuevo.\n\nDetalle: {ultimo_error}"
    )


def _esta_bloqueada(page):
    try:
        titulo = (page.title() or "").lower()
        cuerpo = (page.inner_text("body", timeout=3000) or "")[:2000].lower()
    except Exception:
        return False
    return any(b in titulo or b in cuerpo[:600] for b in BLOQUEOS)


def _scroll_completo(page):
    """Baja por la página para que carguen las imágenes diferidas."""
    try:
        alto = page.evaluate("document.body.scrollHeight")
        y = 0
        while y < min(alto, 15000):
            y += 700
            page.mouse.wheel(0, 700)
            page.wait_for_timeout(150)
        page.evaluate("window.scrollTo(0, 0)")
    except Exception:
        pass


def capturar(url, procesar_imagenes, log=print, headless=False, espera_bloqueo=120):
    """Carga la página, extrae su contenido y llama procesar_imagenes(captura, contexto)
    mientras el navegador sigue abierto (para descargar fotos con la misma sesión)."""
    cap = Captura(url=url)
    respuestas = []

    with sync_playwright() as p:
        log("Abriendo el navegador…")
        ctx = _abrir_contexto(p, headless)
        try:
            page = ctx.pages[0] if ctx.pages else ctx.new_page()
            page.on("response", lambda r: respuestas.append(r))

            log("Cargando la página…")
            page.goto(url, wait_until="domcontentloaded", timeout=60000)

            if _esta_bloqueada(page):
                log("⚠️ La página pidió verificación anti-robots. "
                    "Resuélvela en la ventana del navegador (tienes 2 minutos)…")
                limite = time.time() + espera_bloqueo
                while time.time() < limite and _esta_bloqueada(page):
                    page.wait_for_timeout(2000)
                if _esta_bloqueada(page):
                    raise RuntimeError("La página bloqueó el acceso (verificación anti-robots sin resolver).")

            try:
                page.wait_for_load_state("networkidle", timeout=20000)
            except Exception:
                pass
            _scroll_completo(page)
            page.wait_for_timeout(1500)

            datos = page.evaluate(JS_EXTRAER)
            cap.url_final = page.url
            cap.titulo, cap.h1, cap.og_titulo = datos["titulo"], datos["h1"], datos["og_titulo"]
            cap.pares = [tuple(x) for x in datos["pares"]]
            cap.imagenes_dom = datos["imagenes"]

            for s in datos["scripts"]:
                try:
                    cap.jsons.append(("script", json.loads(s)))
                except Exception:
                    pass

            for r in respuestas:
                try:
                    tipo = (r.headers.get("content-type") or "").lower()
                    if "json" in tipo:
                        cap.jsons.append((r.url, r.json()))
                    elif tipo.startswith("image/"):
                        cap.imagenes_red.append(r.url)
                except Exception:
                    pass

            log(f"Página leída: {len(cap.pares)} datos y {len(set(cap.imagenes_dom + cap.imagenes_red))} imágenes encontradas.")
            resultado = procesar_imagenes(cap, ctx)
        finally:
            ctx.close()
    return cap, resultado

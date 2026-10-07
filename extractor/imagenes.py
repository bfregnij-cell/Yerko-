"""Selección y descarga de las fotos del vehículo."""

import hashlib
import io
import os
import re
from collections import Counter
from urllib.parse import urlparse

from PIL import Image

from . import sites

EXTENSIONES = re.compile(r"\.(jpe?g|png|webp)(\?|$)", re.I)
DESCARTAR = re.compile(r"logo|icon|sprite|banner|flag|avatar|badge|placeholder|loading|blank|"
                       r"spinner|button|arrow|social|facebook|twitter|whatsapp|youtube|instagram|"
                       r"payment|captcha|pixel|tracking|\.svg", re.I)

ANCHO_MINIMO = 480
ALTO_MINIMO = 320


def _es_candidata(url, sitio):
    if not url.startswith("http") or DESCARTAR.search(url):
        return False
    host = (urlparse(url).hostname or "").lower()
    dominios = sites.DOMINIOS_FOTOS.get(sitio) or ()
    if dominios and not any(d in host for d in dominios):
        return False
    return bool(EXTENSIONES.search(url)) or "resizer" in url or "image" in host


def _carpeta_de(url):
    p = urlparse(url)
    return f"{p.hostname}{p.path.rsplit('/', 1)[0]}"


def candidatas(cap, sitio):
    """Devuelve una lista ordenada de [url_alta_res, url_original] por foto."""
    oficiales = sites.imagenes_oficiales(sitio, cap.jsons)
    if oficiales:
        urls = oficiales
    else:
        vistas = []
        for u in cap.imagenes_dom + cap.imagenes_red:
            if _es_candidata(u, sitio) and u not in vistas:
                vistas.append(u)
        # Las fotos de un mismo auto suelen estar en la misma carpeta del servidor:
        # si una carpeta concentra la mayoría, nos quedamos con esa (evita "autos similares").
        conteo = Counter(_carpeta_de(u) for u in vistas)
        if conteo and sitio != "iaai":
            carpeta, n = conteo.most_common(1)[0]
            if n >= 3:
                vistas = [u for u in vistas if _carpeta_de(u) == carpeta]
        urls = vistas

    resultado, vistas_alta = [], set()
    for u in urls:
        alta = sites.version_alta_res(sitio, u)
        if alta in vistas_alta:
            continue
        vistas_alta.add(alta)
        resultado.append([alta] if alta == u else [alta, u])
    return resultado


def _descargar(request, url, referer):
    try:
        r = request.get(url, headers={"Referer": referer}, timeout=30000)
        if r.ok and (r.headers.get("content-type", "").startswith("image/") or EXTENSIONES.search(url)):
            return r.body()
    except Exception:
        pass
    return None


def descargar_fotos(ctx, lista, carpeta, referer, log=print, maximo=60):
    os.makedirs(carpeta, exist_ok=True)
    guardadas, hashes = [], set()
    for opciones in lista:
        if len(guardadas) >= maximo:
            break
        for url in opciones:
            datos = _descargar(ctx.request, url, referer)
            if not datos:
                continue
            try:
                img = Image.open(io.BytesIO(datos))
                img.load()
            except Exception:
                continue
            if img.width < ANCHO_MINIMO or img.height < ALTO_MINIMO:
                break  # miniatura, logo, etc.
            h = hashlib.md5(datos).hexdigest()
            if h in hashes:
                break
            hashes.add(h)
            ruta = os.path.join(carpeta, f"foto_{len(guardadas) + 1:02d}.jpg")
            if img.mode not in ("RGB", "L"):
                img = img.convert("RGB")
            img.save(ruta, "JPEG", quality=92)
            guardadas.append(ruta)
            log(f"  Foto {len(guardadas)} guardada ({img.width}x{img.height})")
            break
    return guardadas

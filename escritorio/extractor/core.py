"""Proceso completo: link -> fotos + especificaciones + texto para publicar."""

import json
import os
import re
from dataclasses import dataclass, field
from datetime import datetime

from . import imagenes, navegador, sites, specs
from .formatter import generar_texto


@dataclass
class Resultado:
    sitio: str
    datos: dict
    texto: str
    carpeta: str
    fotos: list = field(default_factory=list)


def combinar_specs(sitio, cap):
    crudo = {}
    # Prioridad: datos internos del sitio > tabla de la página > título
    for fuente in (sites.specs_desde_json(sitio, cap.jsons),
                   specs.specs_desde_pares(cap.pares),
                   specs.specs_desde_titulo(cap.h1 or cap.og_titulo or cap.titulo)):
        for campo, valor in fuente.items():
            crudo.setdefault(campo, valor)
    datos = specs.normalizar(crudo)
    titulo = " ".join(datos[c] for c in ("marca", "modelo", "anio") if datos.get(c))
    datos["titulo"] = titulo or (cap.h1 or cap.og_titulo or cap.titulo).strip()
    datos["fuente"] = sites.SITIOS[sitio]
    datos["link"] = cap.url
    return datos


def _nombre_carpeta(datos):
    base = datos.get("titulo") or "auto"
    if datos.get("referencia"):
        base += f" - {datos['referencia']}"
    base = re.sub(r'[<>:"/\\|?*\x00-\x1f]', "", base).strip(" .")[:80] or "auto"
    return f"{base} ({datetime.now():%Y-%m-%d %H%M})"


def carpeta_salida_por_defecto():
    docs = os.path.join(os.path.expanduser("~"), "Documents")
    return os.path.join(docs if os.path.isdir(docs) else os.path.expanduser("~"), "Autos extraidos")


def extraer(url, carpeta_base=None, plantilla=None, log=print, headless=False):
    url = url.strip()
    if not re.match(r"https?://", url, re.I):
        url = "https://" + url
    sitio = sites.detectar_sitio(url)
    log(f"Sitio detectado: {sites.SITIOS[sitio]}")
    carpeta_base = carpeta_base or carpeta_salida_por_defecto()

    def procesar(cap, ctx):
        datos = combinar_specs(sitio, cap)
        carpeta = os.path.join(carpeta_base, _nombre_carpeta(datos))
        lista = imagenes.candidatas(cap, sitio)
        log(f"Descargando fotos ({len(lista)} candidatas)…")
        fotos = imagenes.descargar_fotos(ctx, lista, carpeta, cap.url_final or url, log=log)
        return datos, carpeta, fotos

    cap, (datos, carpeta, fotos) = navegador.capturar(url, procesar, log=log, headless=headless)

    texto = generar_texto(datos, plantilla)
    os.makedirs(carpeta, exist_ok=True)
    with open(os.path.join(carpeta, "publicacion.txt"), "w", encoding="utf-8") as f:
        f.write(texto)
    with open(os.path.join(carpeta, "datos.json"), "w", encoding="utf-8") as f:
        json.dump({"datos": datos, "pares_encontrados": cap.pares}, f, ensure_ascii=False, indent=2)

    log(f"✅ Listo: {len(fotos)} fotos y {len([k for k in datos if k in specs.CAMPOS])} datos técnicos.")
    return Resultado(sitio=sitio, datos=datos, texto=texto, carpeta=carpeta, fotos=fotos)

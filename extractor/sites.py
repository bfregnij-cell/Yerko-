"""Detección del sitio y reglas específicas de cada proveedor.

Cada sitio aporta:
  - especificaciones desde los datos internos (JSON) que la página descarga
  - la lista "oficial" de fotos del vehículo, si se puede obtener
  - una forma de pedir la versión en alta resolución de cada foto
"""

import re
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

SITIOS = {
    "beforward": "BE FORWARD",
    "copart": "Copart",
    "iaai": "IAAI",
    "generico": "Sitio genérico",
}


def detectar_sitio(url: str) -> str:
    host = (urlparse(url).hostname or "").lower()
    if host.endswith("beforward.jp") or "beforward" in host:
        return "beforward"
    if host.endswith("copart.com") or ".copart." in host:
        return "copart"
    if host.endswith("iaai.com") or ".iaai." in host:
        return "iaai"
    return "generico"


def recorrer_json(obj):
    """Recorre recursivamente un JSON y entrega cada dict que contiene."""
    pila = [obj]
    while pila:
        actual = pila.pop()
        if isinstance(actual, dict):
            yield actual
            pila.extend(actual.values())
        elif isinstance(actual, list):
            pila.extend(actual)


# ---------------------------------------------------------------- Copart

# Claves internas de la API de Copart (lotdetails) -> campo canónico
COPART_CLAVES = {
    "lcy": "anio",
    "mkn": "marca",
    "lm": "modelo",
    "ltd": "version",
    "orr": "kilometraje",
    "egn": "motor",
    "cy": "cilindros",
    "tmtp": "transmision",
    "drv": "traccion",
    "ft": "combustible",
    "clr": "color",
    "bstl": "carroceria",
    "dd": "danio_principal",
    "sdd": "danio_secundario",
    "hk": "llaves",
    "lcd": "estado",
    "fv": "vin",
    "td": "documento",
    "yn": "ubicacion",
    "ln": "referencia",
}


def _copart_specs(jsons):
    specs = {}
    for _, data in jsons:
        for d in recorrer_json(data):
            if "mkn" in d and ("lcy" in d or "lm" in d):
                for clave, campo in COPART_CLAVES.items():
                    v = d.get(clave)
                    if v not in (None, "", [], {}) and campo not in specs:
                        specs[campo] = str(v)
                if "orr" in d:
                    # Copart informa el odómetro en millas
                    specs["kilometraje"] = f"{d['orr']} mi"
                return specs
    return specs


def _copart_imagenes(jsons):
    preferidas, completas = [], []
    for _, data in jsons:
        for d in recorrer_json(data):
            lista = d.get("imagesList")
            if not isinstance(lista, dict):
                continue
            for tipo, destino in (("HIGH_RESOLUTION_IMAGE", preferidas), ("FULL_IMAGE", completas)):
                for item in lista.get(tipo) or []:
                    if isinstance(item, dict) and item.get("url"):
                        destino.append(item["url"])
    return preferidas or completas


def _copart_alta_res(url):
    return re.sub(r"_(thb|ful|tmb)\.(jpe?g)$", r"_hrs.\2", url, flags=re.I)


# ---------------------------------------------------------------- IAAI

IAAI_RESIZER = "https://vis.iaai.com/resizer"


def _iaai_imagenes(jsons):
    claves = []
    for _, data in jsons:
        for d in recorrer_json(data):
            k = d.get("K")
            if isinstance(k, str) and "~" in k and k not in claves:
                claves.append(k)
    return [f"{IAAI_RESIZER}?{urlencode({'imageKeys': k, 'width': 1920, 'height': 1440})}" for k in claves]


def _iaai_alta_res(url):
    p = urlparse(url)
    if "vis.iaai.com" not in (p.hostname or ""):
        return url
    q = dict(parse_qsl(p.query))
    if "imageKeys" not in q:
        return url
    q["width"], q["height"] = "1920", "1440"
    return urlunparse(p._replace(query=urlencode(q)))


# ---------------------------------------------------------------- BE FORWARD

def _beforward_alta_res(url):
    return re.sub(r"/(small|medium|thumb|thumbnail|s|m)/", "/large/", url, flags=re.I)


# ---------------------------------------------------------------- interfaz

def specs_desde_json(sitio, jsons):
    if sitio == "copart":
        return _copart_specs(jsons)
    return {}


def imagenes_oficiales(sitio, jsons):
    if sitio == "copart":
        return _copart_imagenes(jsons)
    if sitio == "iaai":
        return _iaai_imagenes(jsons)
    return []


def version_alta_res(sitio, url):
    if sitio == "copart":
        return _copart_alta_res(url)
    if sitio == "iaai":
        return _iaai_alta_res(url)
    if sitio == "beforward":
        return _beforward_alta_res(url)
    return url


# Dominios desde donde se aceptan fotos del vehículo (vacío = cualquiera)
DOMINIOS_FOTOS = {
    "beforward": ("beforward",),
    "copart": ("copart",),
    "iaai": ("iaai",),
    "generico": (),
}

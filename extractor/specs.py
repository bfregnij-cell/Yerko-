"""Normalización de especificaciones: etiquetas -> campos y traducción al español."""

import re
import unicodedata

CAMPOS = [
    "marca", "modelo", "anio", "version", "kilometraje", "motor", "cilindros",
    "transmision", "traccion", "combustible", "color", "carroceria", "puertas",
    "asientos", "llaves", "estado", "danio_principal", "danio_secundario",
    "vin", "documento", "ubicacion", "precio", "referencia",
]

# Etiqueta (en minúsculas, sin acentos ni ":") -> campo canónico
SINONIMOS = {
    "marca": ["make", "marca", "maker", "manufacturer"],
    "modelo": ["model", "modelo"],
    "anio": ["year", "model year", "ano", "registration year", "registration year/month",
             "manufacture year", "manufacture year/month", "reg. year", "reg year"],
    "version": ["series", "trim", "grade", "version", "sub model"],
    "kilometraje": ["mileage", "odometer", "kilometraje", "km", "odometer reading", "odo"],
    "motor": ["engine", "engine type", "engine size", "engine capacity", "displacement", "motor", "cc"],
    "cilindros": ["cylinders", "cylinder", "cilindros"],
    "transmision": ["transmission", "trans", "transmision", "gearbox"],
    "traccion": ["drive", "drive type", "drivetrain", "drive line type", "drive line", "traccion", "drive train"],
    "combustible": ["fuel", "fuel type", "combustible"],
    "color": ["color", "colour", "exterior color", "ext color", "exterior colour", "ext. color"],
    "carroceria": ["body style", "body type", "body", "carroceria", "type"],
    "puertas": ["doors", "door", "puertas"],
    "asientos": ["seats", "seating capacity", "asientos", "seating"],
    "llaves": ["keys", "key", "keys available", "llaves"],
    "estado": ["highlights", "start code", "run & drive", "run and drive", "condition", "vehicle condition"],
    "danio_principal": ["primary damage", "damage", "loss", "dano principal"],
    "danio_secundario": ["secondary damage", "dano secundario"],
    "vin": ["vin", "vin (status)", "chassis no.", "chassis no", "chassis number", "chassis", "vin #"],
    "documento": ["title code", "doc type", "title/sale doc", "title state/type", "sale document", "title"],
    "ubicacion": ["location", "sale location", "selling branch", "yard", "branch"],
    "precio": ["price", "fob price", "total price", "vehicle price", "buy it now", "buy now price"],
    "referencia": ["lot number", "lot #", "lot", "stock #", "stock", "ref no.", "ref no", "stock no.",
                   "stock no", "item #", "item number", "stock number"],
}

_ETIQUETA_A_CAMPO = {s: campo for campo, lista in SINONIMOS.items() for s in lista}


def _sin_acentos(texto):
    return "".join(c for c in unicodedata.normalize("NFD", texto) if unicodedata.category(c) != "Mn")


def limpiar_etiqueta(etiqueta):
    e = _sin_acentos(etiqueta).lower().strip()
    e = re.sub(r"\s+", " ", e).strip(" :.-*")
    return e


def limpiar_valor(valor):
    v = re.sub(r"\s+", " ", str(valor or "")).strip(" :|")
    return v


def campo_de_etiqueta(etiqueta):
    return _ETIQUETA_A_CAMPO.get(limpiar_etiqueta(etiqueta))


# ---------------------------------------------------------------- traducciones

TRADUCCIONES = {
    # transmisión
    "automatic": "Automática", "auto": "Automática", "at": "Automática", "manual": "Manual",
    "mt": "Manual", "cvt": "Automática CVT", "semi-automatic": "Semiautomática",
    # combustible
    "gas": "Bencina", "gasoline": "Bencina", "petrol": "Bencina", "diesel": "Diésel",
    "hybrid": "Híbrido", "hybrid engine": "Híbrido", "electric": "Eléctrico",
    "flexible fuel": "Flex", "flex fuel": "Flex", "plug-in hybrid": "Híbrido enchufable",
    "lpg": "Gas licuado", "cng": "GNC",
    # tracción
    "front-wheel drive": "Delantera (FWD)", "front wheel drive": "Delantera (FWD)",
    "fwd": "Delantera (FWD)", "2wd": "2WD",
    "rear-wheel drive": "Trasera (RWD)", "rear wheel drive": "Trasera (RWD)", "rwd": "Trasera (RWD)",
    "all wheel drive": "Integral (AWD)", "all-wheel drive": "Integral (AWD)", "awd": "Integral (AWD)",
    "4x4 w/front whl drv": "4x4", "4x4 w/rear wheel drv": "4x4", "4x4": "4x4", "4wd": "4x4",
    "four by four": "4x4",
    # colores
    "black": "Negro", "white": "Blanco", "silver": "Plateado", "gray": "Gris", "grey": "Gris",
    "red": "Rojo", "blue": "Azul", "green": "Verde", "gold": "Dorado", "beige": "Beige",
    "brown": "Café", "orange": "Naranjo", "yellow": "Amarillo", "purple": "Morado",
    "maroon": "Burdeo", "burgundy": "Burdeo", "pearl": "Perla", "charcoal": "Gris oscuro",
    "tan": "Beige", "pink": "Rosado", "turquoise": "Turquesa", "teal": "Verde azulado",
    # daños
    "front end": "Parte delantera", "rear end": "Parte trasera", "side": "Lateral",
    "left side": "Lateral izquierdo", "right side": "Lateral derecho",
    "left front": "Delantero izquierdo", "right front": "Delantero derecho",
    "left rear": "Trasero izquierdo", "right rear": "Trasero derecho",
    "all over": "Daño general", "minor dent/scratches": "Abolladuras/rayones menores",
    "hail": "Granizo", "water/flood": "Inundación", "flood": "Inundación",
    "mechanical": "Mecánico", "normal wear": "Desgaste normal", "vandalism": "Vandalismo",
    "undercarriage": "Parte inferior", "rollover": "Volcamiento", "burn": "Quemado",
    "burn - engine": "Quemado (motor)", "burn - interior": "Quemado (interior)",
    "top/roof": "Techo", "roof": "Techo", "frame damage": "Daño de chasis",
    "stripped": "Desmantelado", "biohazard/chemical": "Riesgo biológico/químico",
    "rejected repair": "Reparación rechazada", "replaced vin": "VIN reemplazado",
    "damage history": "Historial de daños", "partial repair": "Reparación parcial",
    "missing/altered vin": "VIN faltante/alterado", "electrical": "Eléctrico",
    "collision": "Colisión", "theft": "Robo", "none": "Ninguno", "unknown": "Desconocido",
    "suspension": "Suspensión", "cosmetic": "Estético", "repossession": "Embargo",
    # estado
    "run and drive": "Arranca y anda", "run & drive": "Arranca y anda", "runs and drives": "Arranca y anda",
    "engine start program": "Motor enciende", "starts": "Enciende", "stationary": "Enciende (no se movió)",
    "won't start": "No arranca", "does not start": "No arranca", "enhanced vehicles": "Vehículo mejorado",
    # carrocería
    "sedan 4d": "Sedán 4 puertas", "sedan 2d": "Sedán 2 puertas", "sedan": "Sedán",
    "4dr spor": "SUV", "sport utility": "SUV", "suv": "SUV", "suv 4d": "SUV",
    "hatchbac": "Hatchback", "hatchback": "Hatchback", "hatchback 4d": "Hatchback 5 puertas",
    "coupe": "Coupé", "coupe 2d": "Coupé", "convertible": "Convertible", "conv": "Convertible",
    "wagon": "Station wagon", "station wagon": "Station wagon", "wagon 4d": "Station wagon",
    "pickup": "Camioneta (pickup)", "crew pic": "Camioneta doble cabina", "crew cab": "Camioneta doble cabina",
    "extended": "Camioneta cabina extendida", "minivan": "Minivan", "van": "Furgón", "truck": "Camión",
    "mini vehicle": "Mini vehículo", "motorcycle": "Motocicleta",
    # varios
    "yes": "Sí", "no": "No", "present": "Sí", "missing": "No",
}


def traducir(valor):
    clave = valor.lower().strip()
    if clave in TRADUCCIONES:
        return TRADUCCIONES[clave]
    # "SILVER / BLACK", "FRONT END, SIDE"
    partes = [p.strip() for p in re.split(r"[/,]", valor)]
    if len(partes) > 1 and all(p.lower() in TRADUCCIONES for p in partes):
        separador = " / " if "/" in valor else ", "
        return separador.join(TRADUCCIONES[p.lower()] for p in partes)
    return valor


def _numero(texto):
    digitos = re.sub(r"[^\d]", "", texto)
    return int(digitos) if digitos else None


def _miles(n):
    return f"{n:,}".replace(",", ".")


def formatear_kilometraje(valor):
    n = _numero(valor.split("(")[0])
    if n is None:
        return valor
    estado = ""
    m = re.search(r"\(([^)]+)\)|\b(actual|exempt|not actual|exceeds mechanical limits)\b", valor, re.I)
    if m:
        txt = (m.group(1) or m.group(2)).strip().lower()
        estado = {"actual": " (real)", "not actual": " (no real)", "exempt": " (exento)",
                  "exceeds mechanical limits": " (excede límite mecánico)"}.get(txt, "")
    if re.search(r"\b(mi|miles|millas)\b", valor, re.I):
        km = round(n * 1.609344)
        return f"{_miles(km)} km ({_miles(n)} millas){estado}"
    return f"{_miles(n)} km{estado}"


def formatear_motor(valor):
    # "1,490cc" -> "1.490 cc"; "2.5L 4" -> "2.5L 4 cilindros"
    m = re.fullmatch(r"\s*([\d.,]+)\s*cc\s*", valor, re.I)
    if m:
        n = _numero(m.group(1))
        return f"{_miles(n)} cc" if n else valor
    m = re.fullmatch(r"\s*(\d+(?:\.\d+)?)\s*L\s+(\d+)\s*", valor, re.I)
    if m:
        return f"{m.group(1)}L {m.group(2)} cilindros"
    return valor


def _capitalizar_nombre(valor):
    """'MAZDA CX-5 GRAND TOURING' -> 'Mazda CX-5 Grand Touring' (respeta siglas y códigos)."""
    if not valor.isupper():
        return valor
    return " ".join(p.capitalize() if p.isalpha() and len(p) > 3 else p for p in valor.split())


def normalizar(crudo):
    """Recibe {campo: valor_crudo} y devuelve valores limpios y traducidos."""
    final = {}
    for campo, valor in crudo.items():
        v = limpiar_valor(valor)
        if not v or v.lower() in ("-", "n/a", "na", "null", "none", "--", "ask"):
            continue
        if campo == "kilometraje":
            v = formatear_kilometraje(v)
        elif campo == "motor":
            v = formatear_motor(v)
        elif campo == "anio":
            m = re.search(r"(19|20)\d{2}", v)
            if not m:
                continue
            v = m.group(0)
        elif campo in ("marca", "modelo", "version"):
            v = _capitalizar_nombre(v)
        elif campo in ("vin", "referencia", "precio", "ubicacion", "documento"):
            pass
        else:
            v = traducir(v)
            if v.isupper() and len(v) > 3:
                v = v.capitalize()
        final[campo] = v
    return final


def specs_desde_pares(pares):
    """Convierte pares (etiqueta, valor) del HTML en {campo: valor}. Gana el primero."""
    specs = {}
    for etiqueta, valor in pares:
        campo = campo_de_etiqueta(etiqueta)
        valor = limpiar_valor(valor)
        if campo and valor and campo not in specs and len(valor) < 200:
            specs[campo] = valor
    return specs


_TITULO = re.compile(r"\b((?:19|20)\d{2})\s+([A-Za-z][\w\-]*)\s+(.+)")


def specs_desde_titulo(titulo):
    """'2015 TOYOTA PRIUS S' -> anio, marca, modelo (como respaldo)."""
    m = _TITULO.search(titulo or "")
    if not m:
        return {}
    resto = m.group(3).split("|")[0].split(" - ")[0].strip()
    palabras = resto.split()
    datos = {"anio": m.group(1), "marca": m.group(2)}
    if palabras:
        datos["modelo"] = palabras[0]
        if len(palabras) > 1:
            datos["version"] = " ".join(palabras[1:6])
    return datos

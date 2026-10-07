"""Arma el texto de la publicación a partir de la plantilla.

Reglas de la plantilla (plantilla.txt):
  - {campo} se reemplaza por el dato correspondiente.
  - Si una línea tiene algún {campo} sin dato, la línea completa se omite.
  - Se eliminan líneas en blanco repetidas.
"""

import re

PLANTILLA_POR_DEFECTO = """🚗 {titulo}
✨ Versión: {version}

📋 FICHA TÉCNICA
📅 Año: {anio}
🛣️ Kilometraje: {kilometraje}
⚙️ Motor: {motor}
🔩 Cilindros: {cilindros}
🕹️ Transmisión: {transmision}
🚙 Tracción: {traccion}
⛽ Combustible: {combustible}
🎨 Color: {color}
🚘 Carrocería: {carroceria}
🚪 Puertas: {puertas}
💺 Asientos: {asientos}
🔑 Llaves: {llaves}
✅ Estado: {estado}
💥 Daño principal: {danio_principal}
💥 Daño secundario: {danio_secundario}
🔢 VIN / Chasis: {vin}

📩 Escríbeme por interno para más información.
"""

_MARCADOR = re.compile(r"\{(\w+)\}")


def generar_texto(datos, plantilla=None):
    plantilla = plantilla or PLANTILLA_POR_DEFECTO
    lineas = []
    for linea in plantilla.splitlines():
        campos = _MARCADOR.findall(linea)
        if any(not datos.get(c) for c in campos):
            continue
        lineas.append(_MARCADOR.sub(lambda m: str(datos.get(m.group(1), m.group(0))), linea).rstrip())
    texto = "\n".join(lineas)
    texto = re.sub(r"\n{3,}", "\n\n", texto).strip()
    return texto + "\n"

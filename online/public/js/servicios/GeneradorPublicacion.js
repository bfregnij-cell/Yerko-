/**
 * Arma el texto de la publicación a partir de la plantilla.
 *   - {campo} se reemplaza por el dato.
 *   - Si una línea tiene algún {campo} sin dato, la línea se omite.
 */
export const PLANTILLA_POR_DEFECTO = `🚗 {titulo}
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

📩 Escríbeme por interno para más información.`;

export class GeneradorPublicacion {
  constructor(plantilla = PLANTILLA_POR_DEFECTO) {
    this.plantilla = plantilla || PLANTILLA_POR_DEFECTO;
  }

  generar(datos) {
    const lineas = [];
    for (const linea of this.plantilla.split(/\r?\n/)) {
      const campos = [...linea.matchAll(/\{(\w+)\}/g)].map((m) => m[1]);
      if (campos.some((c) => !datos[c])) continue;
      lineas.push(linea.replace(/\{(\w+)\}/g, (_, c) => datos[c]).trimEnd());
    }
    return lineas.join("\n").replace(/\n{3,}/g, "\n\n").trim() + "\n";
  }
}

/** La plantilla se guarda en este navegador. */
export class RepositorioPlantilla {
  static CLAVE = "extractor.plantilla";

  static leer() {
    try {
      return localStorage.getItem(RepositorioPlantilla.CLAVE) || PLANTILLA_POR_DEFECTO;
    } catch {
      return PLANTILLA_POR_DEFECTO;
    }
  }

  static guardar(texto) {
    localStorage.setItem(RepositorioPlantilla.CLAVE, texto);
  }

  static restaurar() {
    localStorage.removeItem(RepositorioPlantilla.CLAVE);
  }
}

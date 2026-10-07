import { Extractor } from "./Extractor.js";

const RESIZER = "https://vis.iaai.com/resizer";

/**
 * IAAI (iaai.com): la ficha está en la página como "Etiqueta: valor" y las
 * fotos se piden a vis.iaai.com/resizer con una clave por imagen.
 */
export class IaaiExtractor extends Extractor {
  nombre() { return "IAAI"; }
  clave() { return "iaai"; }
  coincide(host) { return host.includes("iaai."); }
  dominiosFotos() { return ["iaai"]; }
  agruparPorCarpeta() { return false; } // todas las fotos pasan por la misma ruta /resizer

  imagenesOficiales(captura) {
    const claves = new Set();
    for (const json of captura.jsons) {
      for (const d of Extractor.recorrerJson(json)) {
        if (typeof d.K === "string" && d.K.includes("~")) claves.add(d.K);
      }
    }
    return [...claves].map(
      (k) => `${RESIZER}?${new URLSearchParams({ imageKeys: k, width: 1920, height: 1440 })}`
    );
  }

  altaResolucion(url) {
    let u;
    try {
      u = new URL(url);
    } catch {
      return url;
    }
    if (!u.hostname.includes("vis.iaai.com") || !u.searchParams.has("imageKeys")) return url;
    u.searchParams.set("width", "1920");
    u.searchParams.set("height", "1440");
    return u.href;
  }
}

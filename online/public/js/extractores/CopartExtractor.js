import { Extractor } from "./Extractor.js";

/** Claves internas de Copart => campo canónico */
const CLAVES = {
  lcy: "anio", mkn: "marca", lm: "modelo", ltd: "version", egn: "motor", cy: "cilindros",
  tmtp: "transmision", drv: "traccion", ft: "combustible", clr: "color", bstl: "carroceria",
  dd: "danio_principal", sdd: "danio_secundario", hk: "llaves", lcd: "estado", fv: "vin",
  td: "documento", yn: "ubicacion", ln: "referencia",
};

/**
 * Copart (copart.com): los datos vienen de su API interna (lotdetails),
 * que el botón consulta desde la propia página.
 */
export class CopartExtractor extends Extractor {
  nombre() { return "Copart"; }
  clave() { return "copart"; }
  coincide(host) { return host.includes("copart."); }
  dominiosFotos() { return ["copart"]; }

  specsDesdeJson(captura) {
    for (const json of captura.jsons) {
      for (const d of Extractor.recorrerJson(json)) {
        if (!("mkn" in d) || !("lcy" in d || "lm" in d)) continue;
        const specs = {};
        for (const [clave, campo] of Object.entries(CLAVES)) {
          const v = d[clave];
          if ((typeof v === "string" || typeof v === "number") && String(v) !== "") specs[campo] ??= String(v);
        }
        if (typeof d.orr === "string" || typeof d.orr === "number") specs.kilometraje = `${d.orr} mi`; // Copart informa millas
        return specs;
      }
    }
    return {};
  }

  imagenesOficiales(captura) {
    const alta = [];
    const completas = [];
    for (const json of captura.jsons) {
      for (const d of Extractor.recorrerJson(json)) {
        const lista = d.imagesList;
        if (!lista || typeof lista !== "object") continue;
        for (const i of lista.HIGH_RESOLUTION_IMAGE ?? []) if (typeof i?.url === "string") alta.push(i.url);
        for (const i of lista.FULL_IMAGE ?? []) if (typeof i?.url === "string") completas.push(i.url);
      }
    }
    return alta.length ? alta : completas;
  }

  altaResolucion(url) {
    return url.replace(/_(thb|ful|tmb)\.(jpe?g)$/i, "_hrs.$2");
  }
}

import { Extractor } from "./Extractor.js";

/** BE FORWARD (beforward.jp): página simple, la ficha está en tablas. */
export class BeForwardExtractor extends Extractor {
  nombre() { return "BE FORWARD"; }
  clave() { return "beforward"; }
  coincide(host) { return host.includes("beforward"); }
  dominiosFotos() { return ["beforward"]; }

  altaResolucion(url) {
    return url.replace(/\/(small|medium|thumb|thumbnail|s|m)\//i, "/large/");
  }
}

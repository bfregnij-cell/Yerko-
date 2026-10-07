import { Extractor } from "./Extractor.js";

/** Cualquier otro sitio: usa solo las reglas comunes. */
export class GenericoExtractor extends Extractor {
  nombre() { return "Sitio genérico"; }
  clave() { return "generico"; }
  coincide() { return true; }
}

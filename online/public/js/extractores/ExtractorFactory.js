import { BeForwardExtractor } from "./BeForwardExtractor.js";
import { CopartExtractor } from "./CopartExtractor.js";
import { GenericoExtractor } from "./GenericoExtractor.js";
import { IaaiExtractor } from "./IaaiExtractor.js";

/** Detecta el sitio por el dominio del link y entrega su extractor. */
export class ExtractorFactory {
  static paraUrl(url) {
    let host = "";
    try {
      host = new URL(url).hostname.toLowerCase();
    } catch {
      /* link inválido: genérico */
    }
    for (const extractor of [new BeForwardExtractor(), new CopartExtractor(), new IaaiExtractor()]) {
      if (host && extractor.coincide(host)) return extractor;
    }
    return new GenericoExtractor();
  }
}

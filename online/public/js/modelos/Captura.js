/**
 * Contenido crudo de la página de un auto, venga del servidor o del botón del
 * navegador. Todo lo que llega del botón se trata como dato no confiable.
 */
export class Captura {
  constructor({ url = "", titulo = "", h1 = "", ogTitulo = "", pares = [], imagenes = [], jsons = [] } = {}) {
    this.url = url;
    this.titulo = titulo;
    this.h1 = h1;
    this.ogTitulo = ogTitulo;
    this.pares = pares;
    this.imagenes = imagenes;
    this.jsons = jsons;
  }

  static desdeMarcador(d) {
    const texto = (v, max = 500) => (typeof v === "string" ? v.trim().slice(0, max) : "");
    const lista = (v, max) => (Array.isArray(v) ? v.slice(0, max) : []);

    const pares = lista(d.pares, 2000)
      .filter((p) => Array.isArray(p) && typeof p[0] === "string" && typeof p[1] === "string")
      .map((p) => [texto(p[0], 60), texto(p[1], 300)]);

    const imagenes = [...new Set(
      lista(d.imagenes, 3000).filter((u) => typeof u === "string" && /^https?:\/\//i.test(u) && u.length < 2000)
    )];

    const jsons = [];
    for (const j of lista(d.jsons, 50)) {
      try {
        const dec = typeof j === "string" ? JSON.parse(j) : j;
        if (dec && typeof dec === "object") jsons.push(dec);
      } catch {
        /* JSON inválido: se ignora */
      }
    }

    return new Captura({
      url: texto(d.url, 2000),
      titulo: texto(d.titulo, 300),
      h1: texto(d.h1, 300),
      ogTitulo: texto(d.og_titulo, 300),
      pares,
      imagenes,
      jsons,
    });
  }

  tituloPagina() {
    return [this.h1, this.ogTitulo, this.titulo].map((t) => (t ?? "").trim()).find(Boolean) ?? "";
  }
}

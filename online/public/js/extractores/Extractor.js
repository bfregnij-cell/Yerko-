/**
 * Lógica común de extracción. Cada sitio hereda y sobrescribe solo lo que
 * cambia: sus datos internos (JSON), su lista oficial de fotos y cómo pedir
 * cada foto en alta resolución.
 */
const DESCARTAR = /logo|icon|sprite|banner|flag|avatar|badge|placeholder|loading|blank|spinner|button|arrow|social|facebook|twitter|whatsapp|youtube|instagram|payment|captcha|pixel|tracking|\.svg/i;
const EXTENSIONES = /\.(jpe?g|png|webp)(\?|$)/i;

export class Extractor {
  /** @abstract */ nombre() { throw new Error("nombre() no implementado"); }
  /** @abstract */ clave() { throw new Error("clave() no implementado"); }
  /** @abstract */ coincide(_host) { throw new Error("coincide() no implementado"); }

  /** Dominios desde donde se aceptan fotos (vacío = cualquiera). */
  dominiosFotos() { return []; }

  /** Especificaciones desde los datos internos del sitio. */
  specsDesdeJson(_captura) { return {}; }

  /** Lista oficial de fotos del vehículo, si el sitio la entrega. */
  imagenesOficiales(_captura) { return []; }

  /** Versión en alta resolución de una foto. */
  altaResolucion(url) { return url; }

  /** Si las fotos del auto comparten carpeta en el servidor (sirve para descartar "autos similares"). */
  agruparPorCarpeta() { return true; }

  // ------------------------------------------------------------------ especificaciones

  especificaciones(captura, normalizador) {
    const crudo = {};
    // Prioridad: datos internos del sitio > tabla de la página > título
    for (const fuente of [
      this.specsDesdeJson(captura),
      normalizador.desdePares(captura.pares),
      this.#desdeTitulos(captura, normalizador),
    ]) {
      for (const [campo, valor] of Object.entries(fuente)) crudo[campo] ??= valor;
    }
    const datos = normalizador.normalizar(crudo);
    const titulo = ["marca", "modelo", "anio"].map((c) => datos[c]).filter(Boolean).join(" ");
    datos.titulo = titulo || captura.tituloPagina();
    datos.fuente = this.nombre();
    datos.link = captura.url;
    return datos;
  }

  /** Prueba h1, og:title y <title>: usa el primero que trae marca y modelo. */
  #desdeTitulos(captura, normalizador) {
    const candidatos = [captura.h1, captura.ogTitulo, captura.titulo].map((t) => normalizador.desdeTitulo(t));
    return candidatos.find((d) => d.modelo) ?? candidatos.find((d) => d.anio) ?? {};
  }

  // ------------------------------------------------------------------ fotos

  /** @returns {string[][]} Por foto: [url_alta_res, url_original] */
  candidatas(captura) {
    let urls = this.imagenesOficiales(captura);
    if (!urls.length) {
      urls = [...new Set(captura.imagenes.filter((u) => this.#esCandidata(u)))];
      if (this.agruparPorCarpeta() && urls.length) {
        const conteo = new Map();
        for (const u of urls) conteo.set(this.#carpetaDe(u), (conteo.get(this.#carpetaDe(u)) ?? 0) + 1);
        const [carpeta, n] = [...conteo.entries()].sort((a, b) => b[1] - a[1])[0];
        if (n >= 3) urls = urls.filter((u) => this.#carpetaDe(u) === carpeta);
      }
    }
    const vistas = new Set();
    const resultado = [];
    for (const u of urls) {
      const alta = this.altaResolucion(u);
      if (vistas.has(alta)) continue;
      vistas.add(alta);
      resultado.push(alta === u ? [alta] : [alta, u]);
    }
    return resultado;
  }

  #esCandidata(url) {
    if (!/^https?:\/\//i.test(url) || DESCARTAR.test(url)) return false;
    let host;
    try {
      host = new URL(url).hostname.toLowerCase();
    } catch {
      return false;
    }
    const dominios = this.dominiosFotos();
    if (dominios.length && !dominios.some((d) => host.includes(d))) return false;
    return EXTENSIONES.test(url) || url.includes("resizer") || host.includes("image");
  }

  #carpetaDe(url) {
    const u = new URL(this.altaResolucion(url));
    return u.host + u.pathname.slice(0, u.pathname.lastIndexOf("/"));
  }

  // ------------------------------------------------------------------ utilidades

  /** Recorre un JSON y entrega cada objeto que contiene. */
  static *recorrerJson(dato) {
    const pila = [dato];
    while (pila.length) {
      const actual = pila.pop();
      if (actual && typeof actual === "object") {
        if (!Array.isArray(actual)) yield actual;
        for (const v of Object.values(actual)) if (v && typeof v === "object") pila.push(v);
      }
    }
  }
}

/**
 * Descarga las fotos candidatas a través del servidor (/api/imagen), descarta
 * miniaturas, logos y repetidas, y avisa el avance y cada foto que guarda.
 */
const ANCHO_MINIMO = 480;
const ALTO_MINIMO = 320;
const INTENTOS = 3;
const EXTENSIONES = { "image/jpeg": "jpg", "image/png": "png", "image/webp": "webp", "image/gif": "gif" };

export class Foto {
  constructor(blob, ancho, alto, original, orden) {
    this.blob = blob;
    this.ancho = ancho;
    this.alto = alto;
    this.original = original;
    this.orden = orden;
    this.url = URL.createObjectURL(blob);
    this.extension = EXTENSIONES[blob.type] ?? "jpg";
  }
}

export class DescargadorFotos {
  constructor({ maximo = 60, simultaneas = 4, espera = (ms) => new Promise((r) => setTimeout(r, ms)) } = {}) {
    this.maximo = maximo;
    this.simultaneas = simultaneas;
    this.espera = espera;
  }

  /**
   * @param {string[][]} candidatas Por foto: [url_alta_res, url_original]
   * @param {string} referer Página del auto
   * @param {{alGuardar?: (foto: Foto) => void, alAvanzar?: (hechas: number, total: number) => void}} avisos
   * @returns {Promise<{fotos: Foto[], fallidas: string[]}>} fotos en el orden de la página
   */
  async descargar(candidatas, referer, { alGuardar = () => {}, alAvanzar = () => {} } = {}) {
    const fotos = [];
    const fallidas = [];
    const hashes = new Set();
    let siguiente = 0;
    let hechas = 0;

    const trabajador = async () => {
      while (siguiente < candidatas.length) {
        const i = siguiente++;
        const r = await this.#probarOpciones(candidatas[i], referer, i);
        if (r === "fallo") {
          fallidas.push(candidatas[i][candidatas[i].length - 1]);
        } else if (r && !hashes.has(r.hash) && fotos.length < this.maximo) {
          hashes.add(r.hash);
          fotos.push(r.foto);
          alGuardar(r.foto);
        }
        alAvanzar(++hechas, candidatas.length);
      }
    };
    await Promise.all(Array.from({ length: this.simultaneas }, trabajador));

    fotos.sort((a, b) => a.orden - b.orden);
    return { fotos, fallidas };
  }

  async #probarOpciones(opciones, referer, orden) {
    let alguna = false;
    for (const url of opciones) {
      const blob = await this.#bajar(url, referer);
      if (!blob) continue;
      alguna = true;
      const medidas = await this.#medidas(blob);
      if (!medidas) continue;
      if (medidas.ancho < ANCHO_MINIMO || medidas.alto < ALTO_MINIMO) return null; // miniatura, logo…
      const hash = await this.#hash(blob);
      return { foto: new Foto(blob, medidas.ancho, medidas.alto, url, orden), hash };
    }
    return alguna ? null : "fallo";
  }

  /** Pide la foto al servidor; reintenta si falla por red o por un error temporal. */
  async #bajar(url, referer) {
    for (let intento = 1; intento <= INTENTOS; intento++) {
      try {
        const r = await fetch(`/api/imagen?${new URLSearchParams({ u: url, r: referer })}`);
        if (r.ok) {
          const blob = await r.blob();
          return blob.size ? blob : null;
        }
        if (r.status < 500) return null; // no es una imagen: no tiene sentido reintentar
      } catch {
        /* error de red: se reintenta */
      }
      if (intento < INTENTOS) await this.espera(800 * intento);
    }
    return null;
  }

  async #medidas(blob) {
    try {
      const bmp = await createImageBitmap(blob);
      const m = { ancho: bmp.width, alto: bmp.height };
      bmp.close();
      return m;
    } catch {
      return null;
    }
  }

  async #hash(blob) {
    const digest = await crypto.subtle.digest("SHA-1", await blob.arrayBuffer());
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, "0")).join("");
  }
}

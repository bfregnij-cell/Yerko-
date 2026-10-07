/**
 * Descarga las fotos candidatas a través del servidor (/api/imagen), descarta
 * miniaturas, logos y repetidas, y avisa cada vez que guarda una.
 */
const ANCHO_MINIMO = 480;
const ALTO_MINIMO = 320;
const EXTENSIONES = { "image/jpeg": "jpg", "image/png": "png", "image/webp": "webp", "image/gif": "gif" };

export class Foto {
  constructor(blob, ancho, alto, original) {
    this.blob = blob;
    this.ancho = ancho;
    this.alto = alto;
    this.original = original;
    this.url = URL.createObjectURL(blob);
    this.extension = EXTENSIONES[blob.type] ?? "jpg";
  }
}

export class DescargadorFotos {
  constructor({ maximo = 60, simultaneas = 4 } = {}) {
    this.maximo = maximo;
    this.simultaneas = simultaneas;
  }

  /**
   * @param {string[][]} candidatas Por foto: [url_alta_res, url_original]
   * @param {string} referer Página del auto
   * @param {(foto: Foto) => void} alGuardar
   * @returns {Promise<{fotos: Foto[], fallidas: string[]}>}
   */
  async descargar(candidatas, referer, alGuardar = () => {}) {
    const resultados = new Array(candidatas.length);
    let siguiente = 0;
    const trabajador = async () => {
      while (siguiente < candidatas.length) {
        const i = siguiente++;
        resultados[i] = await this.#probarOpciones(candidatas[i], referer);
      }
    };
    await Promise.all(Array.from({ length: this.simultaneas }, trabajador));

    // Se conserva el orden original y se descartan repetidas
    const fotos = [];
    const fallidas = [];
    const hashes = new Set();
    for (let i = 0; i < candidatas.length; i++) {
      const r = resultados[i];
      if (r === "fallo") {
        fallidas.push(candidatas[i][candidatas[i].length - 1]);
        continue;
      }
      if (!r || hashes.has(r.hash) || fotos.length >= this.maximo) continue;
      hashes.add(r.hash);
      fotos.push(r.foto);
      alGuardar(r.foto);
    }
    return { fotos, fallidas };
  }

  async #probarOpciones(opciones, referer) {
    let alguna = false;
    for (const url of opciones) {
      const blob = await this.#bajar(url, referer);
      if (!blob) continue;
      alguna = true;
      const medidas = await this.#medidas(blob);
      if (!medidas) continue;
      if (medidas.ancho < ANCHO_MINIMO || medidas.alto < ALTO_MINIMO) return null; // miniatura, logo…
      const hash = await this.#hash(blob);
      return { foto: new Foto(blob, medidas.ancho, medidas.alto, url), hash };
    }
    return alguna ? null : "fallo";
  }

  async #bajar(url, referer) {
    try {
      const r = await fetch(`/api/imagen?${new URLSearchParams({ u: url, r: referer })}`);
      if (!r.ok) return null;
      const blob = await r.blob();
      return blob.size ? blob : null;
    } catch {
      return null;
    }
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

import { ExtractorFactory } from "../extractores/ExtractorFactory.js";
import { Captura } from "../modelos/Captura.js";
import { DescargadorFotos } from "../servicios/DescargadorFotos.js";
import { GeneradorPublicacion, RepositorioPlantilla } from "../servicios/GeneradorPublicacion.js";
import { LectorHtml } from "../servicios/LectorHtml.js";
import { NormalizadorSpecs } from "../servicios/NormalizadorSpecs.js";

class SitioBloqueadoError extends Error {}

/**
 * Página de resultado: obtiene la captura (del botón o del link), extrae los
 * datos, arma el texto y descarga las fotos.
 */
export class ResultadoController {
  constructor(doc = document) {
    this.$ = (sel) => doc.querySelector(sel);
    this.doc = doc;
    this.fotos = [];
  }

  async iniciar() {
    const p = new URLSearchParams(location.search);
    try {
      const captura = p.get("id") ? await this.#desdeBoton(p.get("id")) : await this.#desdeLink(p.get("link") ?? "");
      await this.#procesar(captura);
    } catch (e) {
      this.#mostrarError(e);
    }
  }

  async #desdeBoton(id) {
    this.#estado("Leyendo los datos enviados por el botón…");
    const r = await fetch(`/api/captura?id=${encodeURIComponent(id)}`);
    const d = await r.json();
    if (!r.ok) throw new Error(d.error ?? "No se encontraron los datos.");
    return Captura.desdeMarcador(d);
  }

  async #desdeLink(link) {
    if (!/^https?:\/\//i.test(link)) link = "https://" + link.trim();
    this.#estado("Descargando la página del auto…");
    const r = await fetch(`/api/pagina?u=${encodeURIComponent(link)}`);
    const d = await r.json();
    if (!r.ok) throw new Error(d.error ?? "No se pudo leer la página.");
    if (d.bloqueado) {
      throw new SitioBloqueadoError(
        `${ExtractorFactory.paraUrl(link).nombre()} no permitió que el servidor leyera la página (código ${d.codigo}).`
      );
    }
    return new LectorHtml().leer(d.html, d.url);
  }

  async #procesar(captura) {
    const extractor = ExtractorFactory.paraUrl(captura.url);
    const datos = extractor.especificaciones(captura, new NormalizadorSpecs());
    const texto = new GeneradorPublicacion(RepositorioPlantilla.leer()).generar(datos);
    this.titulo = datos.titulo || "auto";
    this.nombreArchivo = ResultadoController.nombreUnico(datos, captura.url);

    document.title = `${this.titulo} · Extractor de Autos`;
    this.$("#titulo").textContent = this.titulo;
    this.$("#fuente").textContent = `Fuente: ${datos.fuente} · Archivo: ${this.nombreArchivo}`;
    this.$("#original").href = captura.url;
    this.$("#texto").value = texto;
    this.#tabla(this.$("#tabla-datos"), Object.entries(datos));
    this.#tabla(this.$("#tabla-pares"), captura.pares.slice(0, 300));
    this.$("#cargando").hidden = true;
    this.$("#resultado").hidden = false;

    const candidatas = extractor.candidatas(captura);
    const zip = this.$("#zip");
    if (!candidatas.length) {
      this.$("#estado-fotos").textContent = "No se encontraron fotos en la página.";
      zip.hidden = true;
      return;
    }
    zip.disabled = true;
    zip.textContent = `⏳ Preparando fotos 0/${candidatas.length}…`;

    const { fotos, fallidas } = await new DescargadorFotos().descargar(candidatas, captura.url, {
      alGuardar: (f) => this.#agregarFoto(f),
      alAvanzar: (hechas, total) => (zip.textContent = `⏳ Preparando fotos ${hechas}/${total}…`),
    });
    this.fotos = fotos;
    this.#ordenarGaleria();
    this.$("#cantidad").textContent = String(fotos.length);
    if (fotos.length) {
      zip.disabled = false;
      zip.textContent = `⬇️ Descargar todo en ZIP (${fotos.length} fotos + texto)`;
      this.$("#estado-fotos").textContent = "Toca una foto para descargarla sola.";
    } else {
      zip.hidden = true;
      this.$("#estado-fotos").textContent = "No se pudieron descargar fotos.";
    }
    if (fallidas.length) this.#mostrarRemotas(fallidas);
  }

  /**
   * Nombre único para el ZIP y las fotos: "Mazda CX-5 2022 Negro COD CF116428".
   * El código es la referencia del sitio (Ref No, lote, stock); si no hay, el número del link.
   */
  static nombreUnico(datos, url) {
    const codigo =
      (datos.referencia ?? "").replace(/[^\w-]/g, "") ||
      (ResultadoController.#ruta(url).match(/\d{5,}/g) ?? []).pop() ||
      [...url].reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 7).toString(36).toUpperCase();
    const partes = [datos.marca, datos.modelo, datos.anio, datos.color]
      .filter(Boolean)
      .join(" ") || datos.titulo || "Auto";
    return `${partes} COD ${codigo}`.replace(/[\\/:*?"<>|]+/g, "").replace(/\s+/g, " ").trim().slice(0, 100);
  }

  static #ruta(url) {
    try {
      return new URL(url).pathname;
    } catch {
      return "";
    }
  }

  #nombreBase() {
    return this.nombreArchivo || "Auto";
  }

  #nombreFoto(n, foto) {
    return `${this.#nombreBase()} - ${String(n).padStart(2, "0")}.${foto.extension}`;
  }

  #agregarFoto(foto) {
    const a = this.doc.createElement("a");
    a.href = foto.url;
    a.title = "Descargar esta foto";
    a.dataset.orden = String(foto.orden);
    const img = this.doc.createElement("img");
    img.src = foto.url;
    img.alt = "";
    a.append(img);
    this.$("#galeria").append(a);
  }

  /** Al terminar, deja la galería en el orden de la página y numera las fotos. */
  #ordenarGaleria() {
    const galeria = this.$("#galeria");
    const porOrden = new Map([...galeria.children].map((a) => [a.dataset.orden, a]));
    galeria.replaceChildren();
    this.fotos.forEach((foto, i) => {
      const a = porOrden.get(String(foto.orden));
      if (!a) return;
      a.download = this.#nombreFoto(i + 1, foto);
      a.querySelector("img").alt = a.download;
      galeria.append(a);
    });
  }

  #mostrarRemotas(urls) {
    this.$("#remotas").hidden = false;
    this.$("#remotas-cantidad").textContent = String(urls.length);
    for (const u of urls) {
      const a = this.doc.createElement("a");
      a.href = u;
      a.target = "_blank";
      a.rel = "noopener noreferrer";
      const img = this.doc.createElement("img");
      img.src = u;
      img.referrerPolicy = "no-referrer";
      img.loading = "lazy";
      a.append(img);
      this.$("#galeria-remota").append(a);
    }
  }

  async descargarZip() {
    const boton = this.$("#zip");
    const texto = boton.textContent;
    boton.disabled = true;
    boton.textContent = "⏳ Armando ZIP…";
    try {
      const zip = new window.JSZip();
      const carpeta = zip.folder(this.#nombreBase());
      this.fotos.forEach((f, i) => carpeta.file(this.#nombreFoto(i + 1, f), f.blob));
      carpeta.file("publicacion.txt", this.$("#texto").value);
      const blob = await zip.generateAsync({ type: "blob" });
      const a = this.doc.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = `${this.#nombreBase()}.zip`;
      this.doc.body.append(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 60000);
    } finally {
      boton.disabled = false;
      boton.textContent = texto;
    }
  }

  #tabla(tabla, filas) {
    for (const [k, v] of filas) {
      const tr = tabla.insertRow();
      const th = this.doc.createElement("th");
      th.textContent = k;
      tr.append(th);
      tr.insertCell().textContent = v;
    }
  }

  #estado(msg) {
    this.$("#cargando-texto").textContent = msg;
  }

  #mostrarError(e) {
    this.$("#cargando").hidden = true;
    this.$("#fallo").hidden = false;
    this.$("#fallo-mensaje").textContent = e.message || "Error desconocido.";
    this.$("#fallo-bloqueo").hidden = !(e instanceof SitioBloqueadoError);
  }
}

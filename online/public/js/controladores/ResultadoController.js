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

    document.title = `${this.titulo} · Extractor de Autos`;
    this.$("#titulo").textContent = this.titulo;
    this.$("#fuente").textContent = `Fuente: ${datos.fuente}`;
    this.$("#original").href = captura.url;
    this.$("#texto").value = texto;
    this.#tabla(this.$("#tabla-datos"), Object.entries(datos));
    this.#tabla(this.$("#tabla-pares"), captura.pares.slice(0, 300));
    this.$("#cargando").hidden = true;
    this.$("#resultado").hidden = false;

    const candidatas = extractor.candidatas(captura);
    this.$("#estado-fotos").textContent = candidatas.length
      ? `Descargando ${candidatas.length} fotos…`
      : "No se encontraron fotos en la página.";
    const { fotos, fallidas } = await new DescargadorFotos().descargar(candidatas, captura.url, (f) =>
      this.#agregarFoto(f)
    );
    this.fotos = fotos;
    this.$("#cantidad").textContent = String(fotos.length);
    this.$("#estado-fotos").textContent = fotos.length ? "" : "No se pudieron descargar fotos.";
    this.$("#zip").hidden = fotos.length === 0;
    if (fallidas.length) this.#mostrarRemotas(fallidas);
  }

  #agregarFoto(foto) {
    const n = this.$("#galeria").children.length + 1;
    const a = this.doc.createElement("a");
    a.href = foto.url;
    a.download = `foto_${String(n).padStart(2, "0")}.${foto.extension}`;
    a.title = "Descargar";
    const img = this.doc.createElement("img");
    img.src = foto.url;
    img.alt = a.download;
    a.append(img);
    this.$("#galeria").append(a);
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
    boton.disabled = true;
    boton.textContent = "Armando ZIP…";
    const zip = new window.JSZip();
    this.fotos.forEach((f, i) => zip.file(`foto_${String(i + 1).padStart(2, "0")}.${f.extension}`, f.blob));
    zip.file("publicacion.txt", this.$("#texto").value);
    const blob = await zip.generateAsync({ type: "blob" });
    const a = this.doc.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `${this.titulo.replace(/[^\w -]/g, "") || "auto"} - fotos.zip`;
    a.click();
    boton.disabled = false;
    boton.textContent = "⬇️ Descargar todas (ZIP)";
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

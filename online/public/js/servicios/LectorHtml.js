import { Captura } from "../modelos/Captura.js";

/**
 * Lee el HTML descargado por el servidor y arma la Captura (lo mismo que hace
 * el botón del navegador dentro de la página del auto).
 */
export class LectorHtml {
  leer(html, url) {
    const doc = new DOMParser().parseFromString(html, "text/html");
    const txt = (el) => (el ? (el.textContent || "").replace(/\s+/g, " ").trim() : "");
    const meta = (p) => doc.querySelector(`meta[property="${p}"]`)?.getAttribute("content")?.trim() ?? "";

    return new Captura({
      url,
      titulo: txt(doc.querySelector("title")),
      h1: txt(doc.querySelector("h1")),
      ogTitulo: meta("og:title"),
      pares: this.#pares(doc, txt),
      imagenes: this.#imagenes(doc, url),
      jsons: this.#jsons(doc),
    });
  }

  #pares(doc, txt) {
    const pares = [];
    const add = (a, b) => {
      a = (a || "").trim();
      b = (b || "").trim();
      if (a && b && a.length <= 45 && b.length <= 200 && a !== b) pares.push([a, b]);
    };
    doc.querySelectorAll("tr").forEach((tr) => {
      const c = [...tr.children].filter((x) => x.tagName === "TH" || x.tagName === "TD");
      for (let i = 0; i + 1 < c.length; i += 2) add(txt(c[i]), txt(c[i + 1]));
    });
    doc.querySelectorAll("dt").forEach((dt) => {
      const dd = dt.nextElementSibling;
      if (dd && dd.tagName === "DD") add(txt(dt), txt(dd));
    });
    doc.querySelectorAll("label, span, div, strong, b, p, li, h4, h5, h6").forEach((el) => {
      if (el.children.length > 2) return;
      const t = txt(el);
      if (!t || t.length > 45 || !t.endsWith(":")) return;
      let v = el.nextElementSibling ? txt(el.nextElementSibling) : "";
      if (!v && el.parentElement) {
        const p = txt(el.parentElement);
        if (p.startsWith(t)) v = p.slice(t.length);
      }
      add(t, v);
    });
    doc.querySelectorAll("li, p, span, div").forEach((el) => {
      if (el.children.length > 3) return;
      const m = /^([A-Za-z][A-Za-z #./&()-]{1,40}):\s*(.{1,120})$/.exec(txt(el));
      if (m) add(m[1] + ":", m[2]);
    });
    return pares;
  }

  #imagenes(doc, base) {
    const urls = [];
    const push = (u) => {
      if (u && !u.startsWith("data:")) {
        try {
          urls.push(new URL(u, base).href);
        } catch {
          /* URL inválida */
        }
      }
    };
    const mayorSrcset = (s) => {
      if (!s) return null;
      let mejor = null;
      let ancho = -1;
      s.split(",").forEach((p) => {
        const [u, w] = p.trim().split(/\s+/);
        const n = parseFloat(w) || 0;
        if (n >= ancho) {
          ancho = n;
          mejor = u;
        }
      });
      return mejor;
    };
    doc.querySelectorAll("img, source").forEach((im) => {
      ["data-zoom-image", "data-large", "data-full", "data-original", "data-src", "data-lazy", "src"]
        .forEach((a) => push(im.getAttribute(a)));
      push(mayorSrcset(im.getAttribute("srcset") || im.getAttribute("data-srcset")));
    });
    doc.querySelectorAll("a[href]").forEach((a) => {
      if (/\.(jpe?g|png|webp)(\?|$)/i.test(a.getAttribute("href"))) push(a.getAttribute("href"));
    });
    doc.querySelectorAll('meta[property="og:image"], meta[name="twitter:image"]')
      .forEach((m) => push(m.getAttribute("content")));
    return [...new Set(urls)];
  }

  #jsons(doc) {
    const jsons = [];
    doc.querySelectorAll('script[type="application/ld+json"], script[type="application/json"]').forEach((s) => {
      try {
        const d = JSON.parse(s.textContent);
        if (d && typeof d === "object") jsons.push(d);
      } catch {
        /* JSON inválido */
      }
    });
    return jsons;
  }
}

/*
 * Servidor local que imita Netlify para las pruebas: sirve public/ y enruta
 * /api/* a las funciones, con un almacén en memoria en vez de Netlify Blobs.
 *   PUERTO=8800 node tests/servidor-local.mjs
 */
import { readFile } from "node:fs/promises";
import http from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";

const RAIZ = path.join(path.dirname(fileURLToPath(import.meta.url)), "..");
const PUBLICO = path.join(RAIZ, "public");

const memoria = new Map();
globalThis.__almacenPrueba = {
  async set(k, v, { metadata } = {}) { memoria.set(k, { data: v, metadata }); },
  async getWithMetadata(k) { return memoria.get(k) ?? null; },
  async delete(k) { memoria.delete(k); },
};

const funciones = {};
for (const nombre of ["recibir", "captura", "pagina", "imagen"]) {
  const mod = await import(path.join(RAIZ, "netlify", "functions", `${nombre}.mjs`));
  funciones[mod.config.path] = mod.default;
}

const TIPOS = { ".html": "text/html; charset=utf-8", ".js": "text/javascript", ".css": "text/css" };

http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  try {
    if (funciones[url.pathname]) {
      const cuerpo = ["GET", "HEAD"].includes(req.method) ? undefined : await new Promise((ok) => {
        const partes = [];
        req.on("data", (p) => partes.push(p)).on("end", () => ok(Buffer.concat(partes)));
      });
      const respuesta = await funciones[url.pathname](new Request(url, { method: req.method, headers: req.headers, body: cuerpo }));
      res.writeHead(respuesta.status, Object.fromEntries(respuesta.headers));
      res.end(Buffer.from(await respuesta.arrayBuffer()));
      return;
    }
    const archivo = path.join(PUBLICO, url.pathname === "/" ? "index.html" : url.pathname);
    if (!archivo.startsWith(PUBLICO)) throw new Error("ruta inválida");
    const datos = await readFile(archivo);
    res.writeHead(200, { "content-type": TIPOS[path.extname(archivo)] ?? "application/octet-stream" });
    res.end(datos);
  } catch {
    res.writeHead(404).end("No encontrado");
  }
}).listen(Number(process.env.PUERTO ?? 8800), "127.0.0.1");

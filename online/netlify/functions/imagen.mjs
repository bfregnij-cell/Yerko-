/*
 * Descarga una foto a través del servidor (para poder armar el ZIP):
 * GET /api/imagen?u=<url de la foto>&r=<página del auto>
 */
import { leerConLimite, obtener } from "../lib/red.mjs";

/** Reconoce JPEG, PNG, GIF y WEBP por sus primeros bytes. */
export function tipoDeImagen(b) {
  if (b.length < 12) return null;
  if (b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) return "image/jpeg";
  if (b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4e && b[3] === 0x47) return "image/png";
  if (b[0] === 0x47 && b[1] === 0x49 && b[2] === 0x46) return "image/gif";
  if (String.fromCharCode(...b.slice(0, 4)) === "RIFF" && String.fromCharCode(...b.slice(8, 12)) === "WEBP") return "image/webp";
  return null;
}

const MAX_BYTES = 4 * 1024 * 1024; // límite de respuesta de Netlify Functions (~4,5 MB binario)

export default async (req) => {
  const p = new URL(req.url).searchParams;
  const u = p.get("u") ?? "";
  const referer = p.get("r") ?? "";
  try {
    const cabeceras = /^https?:\/\//i.test(referer) ? { Referer: referer } : {};
    const { respuesta } = await obtener(u, { cabeceras, tiempo: 15000 });
    if (respuesta.status >= 500) {
      return new Response(`El sitio respondió con error (código ${respuesta.status}).`, { status: 502 });
    }
    if (!respuesta.ok) {
      return new Response(`No se pudo obtener la imagen (código ${respuesta.status}).`, { status: 404 });
    }
    const cuerpo = await leerConLimite(respuesta, MAX_BYTES);
    // Algunos CDN (ej. BE FORWARD) entregan fotos como "binary/octet-stream":
    // se reconoce la imagen por su contenido, no por la cabecera.
    const tipo = tipoDeImagen(cuerpo);
    if (!tipo) return new Response("No es una imagen.", { status: 404 });
    return new Response(cuerpo, {
      headers: { "content-type": tipo, "cache-control": "public, max-age=86400" },
    });
  } catch (e) {
    return new Response(e.message, { status: 502 });
  }
};

export const config = { path: "/api/imagen" };

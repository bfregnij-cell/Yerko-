/*
 * Descarga una foto a través del servidor (para poder armar el ZIP):
 * GET /api/imagen?u=<url de la foto>&r=<página del auto>
 */
import { leerConLimite, obtener } from "../lib/red.mjs";

const MAX_BYTES = 4 * 1024 * 1024; // límite de respuesta de Netlify Functions (~4,5 MB binario)

export default async (req) => {
  const p = new URL(req.url).searchParams;
  const u = p.get("u") ?? "";
  const referer = p.get("r") ?? "";
  try {
    const cabeceras = /^https?:\/\//i.test(referer) ? { Referer: referer } : {};
    const { respuesta } = await obtener(u, { cabeceras, tiempo: 15000 });
    const tipo = (respuesta.headers.get("content-type") ?? "").toLowerCase();
    if (!respuesta.ok || !tipo.startsWith("image/")) {
      return new Response(`No es una imagen (código ${respuesta.status}).`, { status: 502 });
    }
    const cuerpo = await leerConLimite(respuesta, MAX_BYTES);
    return new Response(cuerpo, {
      headers: { "content-type": tipo, "cache-control": "public, max-age=86400" },
    });
  } catch (e) {
    return new Response(e.message, { status: 502 });
  }
};

export const config = { path: "/api/imagen" };

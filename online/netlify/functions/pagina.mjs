/*
 * Descarga la página de un auto desde el servidor: GET /api/pagina?u=<link>
 * Funciona con sitios sin anti-robots (BE FORWARD). Si el sitio bloquea,
 * responde { bloqueado: true } para que la interfaz sugiera usar el botón.
 */
import { json, leerConLimite, obtener } from "../lib/red.mjs";

const BLOQUEOS = [
  "pardon our interruption", "access denied", "incapsula", "request unsuccessful", "are you a robot",
  "just a moment", "attention required", "verify you are human", "_incapsula_resource",
];

export default async (req) => {
  const u = new URL(req.url).searchParams.get("u") ?? "";
  try {
    const { respuesta, url } = await obtener(u);
    const html = new TextDecoder().decode(await leerConLimite(respuesta, 4 * 1024 * 1024));
    const inicio = html.slice(0, 20000).toLowerCase();
    const bloqueado = !respuesta.ok || BLOQUEOS.some((b) => inicio.includes(b));
    return json({ codigo: respuesta.status, url, bloqueado, html: bloqueado ? "" : html });
  } catch (e) {
    return json({ error: e.message }, 400);
  }
};

export const config = { path: "/api/pagina" };

/*
 * Entrega los datos guardados de un auto: GET /api/captura?id=...
 */
import { idValido, leerCaptura } from "../lib/almacen.mjs";
import { json } from "../lib/red.mjs";

export default async (req) => {
  const id = new URL(req.url).searchParams.get("id");
  if (!idValido(id)) return json({ error: "Identificador inválido." }, 400);
  const datos = await leerCaptura(id);
  if (!datos) return json({ error: "No se encontró ese auto (los datos se borran después de 7 días)." }, 404);
  return json(datos);
};

export const config = { path: "/api/captura" };

/*
 * Recibe los datos que envía el botón "Extraer auto" (o que se pegan a mano),
 * los guarda y redirige a la página de resultado.
 */
import { guardarCaptura } from "../lib/almacen.mjs";

const MAX_DATOS = 5 * 1024 * 1024;

const error = (mensaje) =>
  new Response(null, { status: 303, headers: { location: "/?error=" + encodeURIComponent(mensaje) } });

export default async (req) => {
  if (req.method !== "POST") return Response.redirect(new URL("/", req.url), 303);
  let datos;
  try {
    const form = await req.formData();
    const texto = String(form.get("datos") ?? "");
    if (texto.length > MAX_DATOS) return error("Los datos son demasiado grandes.");
    datos = JSON.parse(texto);
  } catch {
    return error("Los datos recibidos no son válidos.");
  }
  if (!datos || typeof datos !== "object" || !/^https?:\/\//i.test(String(datos.url ?? ""))) {
    return error("Los datos recibidos no son válidos.");
  }
  const id = await guardarCaptura(datos);
  return new Response(null, { status: 303, headers: { location: `/resultado.html?id=${id}` } });
};

export const config = { path: "/api/recibir" };

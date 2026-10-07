/*
 * Guarda los datos que envía el botón del navegador (Netlify Blobs).
 * En las pruebas locales se reemplaza por un almacén en memoria.
 */
import { getStore } from "@netlify/blobs";

const DIAS_RETENCION = 7;

function store() {
  return globalThis.__almacenPrueba ?? getStore({ name: "capturas", consistency: "strong" });
}

export function nuevoId() {
  return [...crypto.getRandomValues(new Uint8Array(8))].map((b) => b.toString(16).padStart(2, "0")).join("");
}

export const idValido = (id) => /^[a-f0-9]{16}$/.test(id ?? "");

export async function guardarCaptura(datos) {
  const id = nuevoId();
  await store().set(id, JSON.stringify(datos), { metadata: { creado: Date.now() } });
  return id;
}

export async function leerCaptura(id) {
  if (!idValido(id)) return null;
  const entrada = await store().getWithMetadata(id);
  if (!entrada) return null;
  if (Date.now() - (entrada.metadata?.creado ?? 0) > DIAS_RETENCION * 86400000) {
    await store().delete(id);
    return null;
  }
  return JSON.parse(entrada.data);
}

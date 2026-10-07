/*
 * Descargas seguras desde las funciones: solo http/https hacia direcciones
 * públicas (para que nadie use el servidor para entrar a redes internas),
 * revisando cada redirección y limitando el tamaño de la respuesta.
 */
import { lookup } from "node:dns/promises";
import net from "node:net";

export const AGENTE =
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36";

export function esIpPrivada(ip) {
  if (net.isIPv4(ip)) {
    const [a, b] = ip.split(".").map(Number);
    return (
      a === 0 || a === 10 || a === 127 || (a === 100 && b >= 64 && b <= 127) ||
      (a === 169 && b === 254) || (a === 172 && b >= 16 && b <= 31) ||
      (a === 192 && b === 168) || (a === 198 && (b === 18 || b === 19)) || a >= 224
    );
  }
  const x = ip.toLowerCase();
  if (x.startsWith("::ffff:")) return esIpPrivada(x.slice(7));
  return x === "::" || x === "::1" || x.startsWith("fc") || x.startsWith("fd") || x.startsWith("fe80");
}

async function validar(url) {
  let u;
  try {
    u = new URL(url);
  } catch {
    throw new Error("Link inválido.");
  }
  if (!["http:", "https:"].includes(u.protocol)) throw new Error("El link debe empezar con http:// o https://");
  if (process.env.EXTRACTOR_RED_LOCAL === "1") return u;
  const host = u.hostname.replace(/^\[|\]$/g, "");
  const ips = net.isIP(host) ? [{ address: host }] : await lookup(host, { all: true }).catch(() => []);
  if (!ips.length) throw new Error(`No se encontró el sitio ${host}.`);
  if (ips.some((r) => esIpPrivada(r.address))) throw new Error(`Dirección no permitida: ${host}`);
  return u;
}

/** fetch seguro: valida cada salto de redirección. Devuelve { respuesta, url }. */
export async function obtener(url, { cabeceras = {}, tiempo = 20000 } = {}) {
  let actual = url;
  for (let saltos = 0; saltos <= 5; saltos++) {
    await validar(actual);
    const respuesta = await fetch(actual, {
      redirect: "manual",
      headers: {
        "User-Agent": AGENTE,
        Accept: "text/html,application/xhtml+xml,application/json,image/*,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9,es;q=0.8",
        ...cabeceras,
      },
      signal: AbortSignal.timeout(tiempo),
    });
    const destino = respuesta.headers.get("location");
    if ([301, 302, 303, 307, 308].includes(respuesta.status) && destino) {
      actual = new URL(destino, actual).href;
      continue;
    }
    return { respuesta, url: actual };
  }
  throw new Error("Demasiadas redirecciones.");
}

/** Lee el cuerpo con un tope de bytes. */
export async function leerConLimite(respuesta, maxBytes) {
  const lector = respuesta.body?.getReader();
  if (!lector) return new Uint8Array();
  const partes = [];
  let total = 0;
  for (;;) {
    const { done, value } = await lector.read();
    if (done) break;
    total += value.length;
    if (total > maxBytes) {
      await lector.cancel();
      throw new Error("Respuesta demasiado grande.");
    }
    partes.push(value);
  }
  const salida = new Uint8Array(total);
  let pos = 0;
  for (const p of partes) {
    salida.set(p, pos);
    pos += p.length;
  }
  return salida;
}

export const json = (datos, estado = 200) =>
  new Response(JSON.stringify(datos), {
    status: estado,
    headers: { "content-type": "application/json; charset=utf-8", "cache-control": "no-store" },
  });

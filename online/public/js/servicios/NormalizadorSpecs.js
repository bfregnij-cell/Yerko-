/**
 * Convierte etiquetas de cualquier sitio en campos fijos, limpia los valores
 * y los traduce al español.
 */
export const CAMPOS = [
  "marca", "modelo", "anio", "version", "kilometraje", "motor", "cilindros",
  "transmision", "traccion", "combustible", "color", "carroceria", "puertas",
  "asientos", "llaves", "estado", "danio_principal", "danio_secundario",
  "vin", "documento", "ubicacion", "precio", "referencia",
];

/** Campo => etiquetas posibles (minúsculas, sin acentos ni ":") */
const SINONIMOS = {
  marca: ["make", "marca", "maker", "manufacturer"],
  modelo: ["model", "modelo"],
  anio: ["year", "model year", "ano", "registration year", "registration year/month",
    "manufacture year", "manufacture year/month", "reg. year", "reg year", "registrationyear/month"],
  version: ["series", "trim", "grade", "version", "sub model", "version/class"],
  kilometraje: ["mileage", "odometer", "kilometraje", "km", "odometer reading", "odo"],
  motor: ["engine", "engine type", "engine size", "engine capacity", "displacement", "motor", "cc"],
  cilindros: ["cylinders", "cylinder", "cilindros"],
  transmision: ["transmission", "trans", "transmision", "gearbox"],
  traccion: ["drive", "drive type", "drivetrain", "drive line type", "drive line", "traccion", "drive train"],
  combustible: ["fuel", "fuel type", "combustible"],
  color: ["color", "colour", "exterior color", "ext color", "exterior colour", "ext. color"],
  carroceria: ["body style", "body type", "body", "carroceria", "type"],
  puertas: ["doors", "door", "puertas"],
  asientos: ["seats", "seating capacity", "asientos", "seating"],
  llaves: ["keys", "key", "keys available", "llaves"],
  estado: ["highlights", "start code", "run & drive", "run and drive", "condition", "vehicle condition"],
  danio_principal: ["primary damage", "damage", "loss", "dano principal"],
  danio_secundario: ["secondary damage", "dano secundario"],
  vin: ["vin", "vin (status)", "chassis no.", "chassis no", "chassis number", "chassis", "vin #"],
  documento: ["title code", "doc type", "title/sale doc", "title state/type", "sale document", "title"],
  ubicacion: ["location", "sale location", "selling branch", "yard", "branch"],
  precio: ["price", "fob price", "total price", "vehicle price", "buy it now", "buy now price"],
  referencia: ["lot number", "lot #", "lot", "stock #", "stock", "ref no.", "ref no", "stock no.",
    "stock no", "item #", "item number", "stock number"],
};

const TRADUCCIONES = {
  // transmisión
  automatic: "Automática", auto: "Automática", at: "Automática", manual: "Manual",
  "automatic transmission": "Automática", "manual transmission": "Manual", "cvt transmission": "Automática CVT",
  mt: "Manual", cvt: "Automática CVT", "semi-automatic": "Semiautomática",
  // combustible
  gas: "Bencina", gasoline: "Bencina", petrol: "Bencina", diesel: "Diésel",
  hybrid: "Híbrido", "hybrid engine": "Híbrido", electric: "Eléctrico",
  "flexible fuel": "Flex", "flex fuel": "Flex", "plug-in hybrid": "Híbrido enchufable",
  lpg: "Gas licuado", cng: "GNC",
  // tracción
  "front-wheel drive": "Delantera (FWD)", "front wheel drive": "Delantera (FWD)",
  fwd: "Delantera (FWD)", "2wd": "2WD",
  "rear-wheel drive": "Trasera (RWD)", "rear wheel drive": "Trasera (RWD)", rwd: "Trasera (RWD)",
  "all wheel drive": "Integral (AWD)", "all-wheel drive": "Integral (AWD)", awd: "Integral (AWD)",
  "4x4 w/front whl drv": "4x4", "4x4 w/rear wheel drv": "4x4", "4x4": "4x4", "4wd": "4x4",
  "four by four": "4x4", "4x4 drive": "4x4", "4wd drive": "4x4", "awd drive": "Integral (AWD)",
  "front wheel drive (fwd)": "Delantera (FWD)", "rear wheel drive (rwd)": "Trasera (RWD)", "all wheel drive (awd)": "Integral (AWD)",
  // colores
  black: "Negro", white: "Blanco", silver: "Plateado", gray: "Gris", grey: "Gris",
  red: "Rojo", blue: "Azul", green: "Verde", gold: "Dorado", beige: "Beige",
  brown: "Café", orange: "Naranjo", yellow: "Amarillo", purple: "Morado",
  maroon: "Burdeo", burgundy: "Burdeo", pearl: "Perla", charcoal: "Gris oscuro",
  tan: "Beige", pink: "Rosado", turquoise: "Turquesa", teal: "Verde azulado",
  // daños
  "front end": "Parte delantera", "rear end": "Parte trasera", side: "Lateral",
  "left side": "Lateral izquierdo", "right side": "Lateral derecho",
  "left front": "Delantero izquierdo", "right front": "Delantero derecho",
  "left rear": "Trasero izquierdo", "right rear": "Trasero derecho",
  "all over": "Daño general", "minor dent/scratches": "Abolladuras/rayones menores",
  hail: "Granizo", "water/flood": "Inundación", flood: "Inundación",
  mechanical: "Mecánico", "normal wear": "Desgaste normal", vandalism: "Vandalismo",
  undercarriage: "Parte inferior", rollover: "Volcamiento", burn: "Quemado",
  "burn - engine": "Quemado (motor)", "burn - interior": "Quemado (interior)",
  "top/roof": "Techo", roof: "Techo", "frame damage": "Daño de chasis",
  stripped: "Desmantelado", "biohazard/chemical": "Riesgo biológico/químico",
  "rejected repair": "Reparación rechazada", "replaced vin": "VIN reemplazado",
  "damage history": "Historial de daños", "partial repair": "Reparación parcial",
  "missing/altered vin": "VIN faltante/alterado", electrical: "Eléctrico",
  collision: "Colisión", theft: "Robo", none: "Ninguno", unknown: "Desconocido",
  suspension: "Suspensión", cosmetic: "Estético", repossession: "Embargo",
  // estado
  "run and drive": "Arranca y anda", "run & drive": "Arranca y anda", "runs and drives": "Arranca y anda",
  "engine start program": "Motor enciende", starts: "Enciende", stationary: "Enciende (no se movió)",
  "won't start": "No arranca", "does not start": "No arranca",
  // carrocería
  "sedan 4d": "Sedán 4 puertas", "sedan 2d": "Sedán 2 puertas", sedan: "Sedán",
  "4dr spor": "SUV", "sport utility": "SUV", suv: "SUV", "suv 4d": "SUV",
  hatchbac: "Hatchback", hatchback: "Hatchback", "hatchback 4d": "Hatchback 5 puertas",
  coupe: "Coupé", "coupe 2d": "Coupé", convertible: "Convertible", conv: "Convertible",
  wagon: "Station wagon", "station wagon": "Station wagon", "wagon 4d": "Station wagon",
  pickup: "Camioneta (pickup)", "crew pic": "Camioneta doble cabina", "crew cab": "Camioneta doble cabina",
  extended: "Camioneta cabina extendida", minivan: "Minivan", van: "Furgón", truck: "Camión",
  "mini vehicle": "Mini vehículo", motorcycle: "Motocicleta",
  // varios
  yes: "Sí", no: "No", present: "Sí", missing: "No",
};

const SIN_TRADUCIR = new Set(["vin", "referencia", "precio", "ubicacion", "documento"]);
const VACIOS = new Set(["-", "n/a", "na", "null", "none", "--", "ask"]);

const miles = (n) => n.toLocaleString("de-DE"); // 123.456
const numero = (t) => {
  const d = String(t).replace(/\D/g, "");
  return d === "" ? null : parseInt(d, 10);
};
const esMayusculas = (v) => v.toUpperCase() === v && v.toLowerCase() !== v;

export class NormalizadorSpecs {
  #etiquetaACampo = new Map();

  constructor() {
    for (const [campo, lista] of Object.entries(SINONIMOS)) {
      for (const s of lista) this.#etiquetaACampo.set(s, campo);
    }
  }

  campoDeEtiqueta(etiqueta) {
    return this.#etiquetaACampo.get(this.#limpiarEtiqueta(etiqueta)) ?? null;
  }

  desdePares(pares) {
    const specs = {};
    for (const [etiqueta, valorCrudo] of pares) {
      const campo = this.campoDeEtiqueta(etiqueta);
      const valor = this.#limpiarValor(valorCrudo);
      if (campo && valor && !(campo in specs) && valor.length < 200) specs[campo] = valor;
    }
    return specs;
  }

  /** '2015 TOYOTA PRIUS S' -> año, marca, modelo, versión (como respaldo). */
  desdeTitulo(titulo) {
    const m = /\b((?:19|20)\d{2})\s+([A-Za-z][\w-]*)\s+(.+)/u.exec(titulo ?? "");
    if (!m) return {};
    const resto = m[3].split(/\s+for sale\b|\||\s-\s/i)[0].trim();
    const palabras = resto.split(/\s+/).filter(Boolean);
    const datos = { anio: m[1], marca: m[2] };
    if (palabras.length) {
      datos.modelo = palabras[0];
      if (palabras.length > 1) datos.version = palabras.slice(1, 6).join(" ");
    }
    return datos;
  }

  normalizar(crudo) {
    const final = {};
    for (const [campo, valor] of Object.entries(crudo)) {
      let v = this.#limpiarValor(String(valor));
      if (!v || VACIOS.has(v.toLowerCase())) continue;
      if (campo === "kilometraje") v = this.formatearKilometraje(v);
      else if (campo === "motor") v = this.formatearMotor(v);
      else if (campo === "cilindros") v = /(\d+)\s*cyl/i.exec(v)?.[1] ?? v;
      else if (campo === "anio") {
        const m = /(19|20)\d{2}/.exec(v);
        if (!m) continue;
        v = m[0];
      } else if (["marca", "modelo", "version"].includes(campo)) v = this.#capitalizarNombre(v);
      else if (!SIN_TRADUCIR.has(campo)) {
        v = this.traducir(v);
        if (esMayusculas(v) && v.length > 3) v = v[0] + v.slice(1).toLowerCase();
      }
      final[campo] = v;
    }
    return final;
  }

  traducir(valor) {
    const clave = valor.toLowerCase().trim();
    if (clave in TRADUCCIONES) return TRADUCCIONES[clave];
    // "SILVER / BLACK", "FRONT END, SIDE"
    const partes = valor.split(/[/,]/).map((p) => p.trim());
    if (partes.length > 1 && partes.every((p) => p.toLowerCase() in TRADUCCIONES)) {
      const sep = valor.includes("/") ? " / " : ", ";
      return partes.map((p) => TRADUCCIONES[p.toLowerCase()]).join(sep);
    }
    return valor;
  }

  formatearKilometraje(valor) {
    const n = numero(valor.split("(")[0]);
    if (n === null) return valor;
    let estado = "";
    const m = /\(([^)]+)\)|\b(actual|exempt|not actual|exceeds mechanical limits)\b/i.exec(valor);
    if (m) {
      const txt = (m[1] || m[2] || "").trim().toLowerCase();
      estado = {
        actual: " (real)", "not actual": " (no real)", exempt: " (exento)",
        "exceeds mechanical limits": " (excede límite mecánico)",
      }[txt] ?? "";
    }
    if (/\b(mi|miles|millas)\b/i.test(valor)) {
      return `${miles(Math.round(n * 1.609344))} km (${miles(n)} millas)${estado}`;
    }
    return `${miles(n)} km${estado}`;
  }

  formatearMotor(valor) {
    let m = /^\s*([\d.,]+)\s*cc\s*$/i.exec(valor);
    if (m) {
      const n = numero(m[1]);
      return n ? `${miles(n)} cc` : valor;
    }
    m = /^\s*(\d+(?:\.\d+)?)\s*L\s+(\d+)\s*$/i.exec(valor);
    if (m) return `${m[1]}L ${m[2]} cilindros`;
    return valor;
  }

  // ------------------------------------------------------------------ privados

  #limpiarEtiqueta(e) {
    return String(e)
      .toLowerCase()
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "")
      .replace(/\s+/g, " ")
      .replace(/^[\s:.\-*]+|[\s:.\-*]+$/g, "");
  }

  #limpiarValor(v) {
    const t = String(v ?? "").replace(/\s+/g, " ").replace(/^[\s:|]+|[\s:|]+$/g, "");
    // "Present Present" (el sitio repite el dato para móvil y escritorio)
    const mitad = (t.length - 1) / 2;
    return Number.isInteger(mitad) && t[mitad] === " " && t.slice(0, mitad) === t.slice(mitad + 1) ? t.slice(0, mitad) : t;
  }

  /** 'MAZDA CX-5 GRAND TOURING' -> 'Mazda CX-5 Grand Touring' (respeta siglas y códigos). */
  #capitalizarNombre(v) {
    if (!esMayusculas(v)) return v;
    return v
      .split(/\s+/)
      .filter(Boolean)
      .map((p) => (/^[A-Za-z]+$/.test(p) && p.length > 3 ? p[0] + p.slice(1).toLowerCase() : p))
      .join(" ");
  }
}

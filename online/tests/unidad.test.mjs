/* Pruebas unitarias (node --test). La lectura de HTML se prueba en e2e con navegador real. */
import assert from "node:assert/strict";
import { test } from "node:test";

import { ExtractorFactory } from "../public/js/extractores/ExtractorFactory.js";
import { Captura } from "../public/js/modelos/Captura.js";
import { GeneradorPublicacion } from "../public/js/servicios/GeneradorPublicacion.js";
import { NormalizadorSpecs } from "../public/js/servicios/NormalizadorSpecs.js";
import { esIpPrivada } from "../netlify/lib/red.mjs";

const n = new NormalizadorSpecs();

test("detecta el sitio por el dominio", () => {
  assert.equal(ExtractorFactory.paraUrl("https://www.beforward.jp/toyota/prius/bf123/id/456/").clave(), "beforward");
  assert.equal(ExtractorFactory.paraUrl("https://www.copart.com/lot/12345678/x").clave(), "copart");
  assert.equal(ExtractorFactory.paraUrl("https://www.iaai.com/VehicleDetail/41234567~US").clave(), "iaai");
  assert.equal(ExtractorFactory.paraUrl("https://ejemplo.cl/auto/1").clave(), "generico");
});

test("kilometraje en millas y km", () => {
  assert.equal(n.formatearKilometraje("123,456 mi (ACTUAL)"), "198.683 km (123.456 millas) (real)");
  assert.equal(n.formatearKilometraje("85,000 km"), "85.000 km");
  assert.equal(n.formatearKilometraje("45000"), "45.000 km");
});

test("traducciones", () => {
  assert.equal(n.traducir("AUTOMATIC"), "Automática");
  assert.equal(n.traducir("Front-wheel Drive"), "Delantera (FWD)");
  assert.equal(n.traducir("SILVER / BLACK"), "Plateado / Negro");
  assert.equal(n.traducir("Algo raro"), "Algo raro");
});

test("tabla estilo BE FORWARD", () => {
  const d = n.normalizar(n.desdePares([
    ["Ref No.", "BF123456"], ["Mileage", "85,000 km"], ["Registration Year/month", "2015/3"],
    ["Engine", "1,790cc"], ["Transmission", "AT"], ["Fuel", "Hybrid"], ["Drive", "2WD"],
    ["Ext. Color", "Pearl"], ["Doors", "5"], ["Chassis No.", "ZVW30-1234***"], ["Steering", "Right"],
  ]));
  assert.equal(d.anio, "2015");
  assert.equal(d.kilometraje, "85.000 km");
  assert.equal(d.motor, "1.790 cc");
  assert.equal(d.transmision, "Automática");
  assert.equal(d.combustible, "Híbrido");
  assert.equal(d.color, "Perla");
  assert.equal(d.referencia, "BF123456");
  assert.equal(d.vin, "ZVW30-1234***");
});

test("Copart: datos de la API y fotos en alta resolución", () => {
  const api = { data: { lotDetails: {
    ln: 12345678, mkn: "TOYOTA", lm: "CAMRY", lcy: 2018, orr: 54321, egn: "2.5L 4", tmtp: "AUTOMATIC",
    drv: "Front-wheel Drive", ft: "GAS", clr: "SILVER", bstl: "SEDAN 4D", dd: "FRONT END",
    sdd: "MINOR DENT/SCRATCHES", hk: "YES", lcd: "RUN AND DRIVE",
  } } };
  const fotos = { data: { imagesList: {
    FULL_IMAGE: [{ url: "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_ful.jpg" }],
    HIGH_RESOLUTION_IMAGE: [
      { url: "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_hrs.jpg" },
      { url: "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/b_hrs.jpg" },
    ],
  } } };
  const c = new Captura({ url: "https://www.copart.com/lot/12345678", titulo: "2018 TOYOTA CAMRY L", jsons: [api, fotos] });
  const e = ExtractorFactory.paraUrl(c.url);
  const d = e.especificaciones(c, n);
  assert.equal(d.titulo, "Toyota Camry 2018");
  assert.equal(d.kilometraje, "87.421 km (54.321 millas)");
  assert.equal(d.motor, "2.5L 4 cilindros");
  assert.equal(d.combustible, "Bencina");
  assert.equal(d.carroceria, "Sedán 4 puertas");
  assert.equal(d.danio_principal, "Parte delantera");
  assert.equal(d.danio_secundario, "Abolladuras/rayones menores");
  assert.equal(d.estado, "Arranca y anda");
  assert.equal(d.llaves, "Sí");
  assert.deepEqual(e.candidatas(c).map((x) => x[0]), [
    "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_hrs.jpg",
    "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/b_hrs.jpg",
  ]);
  assert.equal(e.altaResolucion("https://cs.copart.com/x/abc_thb.jpg"), "https://cs.copart.com/x/abc_hrs.jpg");
});

test("IAAI: claves de imagen y pares de la página", () => {
  const vm = { inventoryView: { imageDimensions: { keys: { $values: [
    { K: "36123456~SID~B123~I1~RW2576~H1932~TH0", AR: 1.33 },
    { K: "36123456~SID~B123~I2~RW2576~H1932~TH0", AR: 1.33 },
  ] } } } };
  const c = new Captura({
    url: "https://www.iaai.com/VehicleDetail/41234567~US",
    h1: "2020 HONDA CIVIC EX",
    pares: [["Odometer:", "45,210 mi (Actual)"], ["Primary Damage:", "Rear End"],
      ["Start Code:", "Run & Drive"], ["Key:", "Present"], ["Fuel Type:", "Gasoline"]],
    jsons: [vm],
  });
  const e = ExtractorFactory.paraUrl(c.url);
  const d = e.especificaciones(c, n);
  assert.equal(d.titulo, "Honda Civic 2020");
  assert.equal(d.version, "EX");
  assert.equal(d.danio_principal, "Parte trasera");
  assert.equal(d.estado, "Arranca y anda");
  assert.equal(d.llaves, "Sí");
  const f = e.candidatas(c);
  assert.equal(f.length, 2);
  assert.match(f[0][0], /imageKeys=36123456/);
  assert.match(f[0][0], /width=1920/);
});

test("fotos de la página: filtra logos, otros dominios y otros autos", () => {
  const c = new Captura({ url: "https://www.beforward.jp/x", imagenes: [
    "https://image-cdn.beforward.jp/large/202401/1234/a.jpg",
    "https://image-cdn.beforward.jp/large/202401/1234/b.jpg",
    "https://image-cdn.beforward.jp/large/202401/1234/c.jpg",
    "https://image-cdn.beforward.jp/small/202401/9999/otro_auto.jpg",
    "https://www.beforward.jp/img/logo.png",
    "https://www.facebook.com/tr.jpg",
  ] });
  assert.deepEqual(ExtractorFactory.paraUrl(c.url).candidatas(c).map((x) => x[0]), [
    "https://image-cdn.beforward.jp/large/202401/1234/a.jpg",
    "https://image-cdn.beforward.jp/large/202401/1234/b.jpg",
    "https://image-cdn.beforward.jp/large/202401/1234/c.jpg",
  ]);
});

test("nombres: respeta siglas y códigos de modelo", () => {
  const d = n.normalizar({ marca: "MAZDA", modelo: "CX-5", version: "GRAND TOURING", carroceria: "SUV" });
  assert.deepEqual([d.marca, d.modelo, d.version, d.carroceria], ["Mazda", "CX-5", "Grand Touring", "SUV"]);
});

test("plantilla omite líneas sin dato", () => {
  const t = new GeneradorPublicacion().generar({ titulo: "Toyota Prius 2015", anio: "2015", motor: "1.790 cc" });
  assert.match(t, /🚗 Toyota Prius 2015/);
  assert.match(t, /⚙️ Motor: 1\.790 cc/);
  assert.doesNotMatch(t, /Kilometraje|\{/);
});

test("captura desde el botón: descarta datos con formato incorrecto", () => {
  const c = Captura.desdeMarcador({
    url: "https://www.iaai.com/VehicleDetail/1~US", h1: ["no es texto"],
    pares: [["Odometer:", "1 mi"], ["solo uno"], [1, 2]],
    imagenes: ["https://vis.iaai.com/a.jpg", "javascript:alert(1)", "https://vis.iaai.com/a.jpg"],
    jsons: ['{"K":"a~b"}', "no es json"],
  });
  assert.equal(c.h1, "");
  assert.deepEqual(c.pares, [["Odometer:", "1 mi"]]);
  assert.deepEqual(c.imagenes, ["https://vis.iaai.com/a.jpg"]);
  assert.deepEqual(c.jsons, [{ K: "a~b" }]);
});

test("el servidor no descarga desde redes internas", () => {
  for (const ip of ["127.0.0.1", "10.0.0.5", "192.168.1.1", "172.16.0.1", "169.254.169.254", "::1", "fd00::1", "::ffff:127.0.0.1"]) {
    assert.equal(esIpPrivada(ip), true, ip);
  }
  for (const ip of ["8.8.8.8", "104.16.1.1", "2606:4700::1"]) assert.equal(esIpPrivada(ip), false, ip);
});

test("valores reales vistos en IAAI y BE FORWARD", () => {
  const d = n.normalizar({
    transmision: "Automatic Transmission", traccion: "4X4 Drive", cilindros: "6 Cylinders",
    llaves: "Present Present", estado: "Run & Drive Run & Drive",
  });
  assert.deepEqual(d, { transmision: "Automática", traccion: "4x4", cilindros: "6", llaves: "Sí", estado: "Arranca y anda" });
  const t = n.desdeTitulo("Used 2019 MAZDA CX-5 XD PROACTIVE/3DA-KF2P for Sale CE451821 - BE FORWARD");
  assert.equal(t.marca, "MAZDA");
  assert.equal(t.modelo, "CX-5");
  const c = new Captura({ url: "https://www.beforward.jp/mazda/cx-5/ce451821/id/16337108/", h1: "2019 MAZDA",
    titulo: "Used 2019 MAZDA CX-5 XD PROACTIVE/3DA-KF2P for Sale CE451821 - BE FORWARD",
    pares: [["Version/Class", "XD PROACTIVE"], ["Mileage", "68,803 km"]] });
  const datos = ExtractorFactory.paraUrl(c.url).especificaciones(c, n);
  assert.equal(datos.titulo, "Mazda CX-5 2019");
  assert.equal(datos.version, "XD Proactive");
});

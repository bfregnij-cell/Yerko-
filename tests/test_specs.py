from extractor import sites, specs
from extractor.core import combinar_specs
from extractor.formatter import generar_texto
from extractor.imagenes import candidatas
from extractor.navegador import Captura


def test_detectar_sitio():
    assert sites.detectar_sitio("https://www.beforward.jp/toyota/prius/bf123/id/456/") == "beforward"
    assert sites.detectar_sitio("https://www.copart.com/lot/12345678/clean-title-2018-toyota-camry") == "copart"
    assert sites.detectar_sitio("https://www.iaai.com/VehicleDetail/41234567~US") == "iaai"
    assert sites.detectar_sitio("https://ejemplo.cl/auto/1") == "generico"


def test_kilometraje_millas_y_km():
    assert specs.formatear_kilometraje("123,456 mi (ACTUAL)") == "198.683 km (123.456 millas) (real)"
    assert specs.formatear_kilometraje("85,000 km") == "85.000 km"
    assert specs.formatear_kilometraje("45000") == "45.000 km"


def test_traducciones():
    assert specs.traducir("AUTOMATIC") == "Automática"
    assert specs.traducir("Front-wheel Drive") == "Delantera (FWD)"
    assert specs.traducir("SILVER / BLACK") == "Plateado / Negro"
    assert specs.traducir("Algo raro") == "Algo raro"


def test_specs_desde_pares_tabla_beforward():
    pares = [
        ("Ref No.", "BF123456"), ("Mileage", "85,000 km"),
        ("Registration Year/month", "2015/3"), ("Engine", "1,790cc"),
        ("Transmission", "AT"), ("Fuel", "Hybrid"), ("Drive", "2WD"),
        ("Ext. Color", "Pearl"), ("Doors", "5"), ("Seats", "5"),
        ("Chassis No.", "ZVW30-1234***"), ("Steering", "Right"),
    ]
    crudo = specs.specs_desde_pares(pares)
    datos = specs.normalizar(crudo)
    assert datos["anio"] == "2015"
    assert datos["kilometraje"] == "85.000 km"
    assert datos["motor"] == "1.790 cc"
    assert datos["transmision"] == "Automática"
    assert datos["combustible"] == "Híbrido"
    assert datos["color"] == "Perla"
    assert datos["referencia"] == "BF123456"
    assert datos["vin"] == "ZVW30-1234***"


def test_copart_json_y_fotos():
    api = {"data": {"lotDetails": {
        "ln": 12345678, "mkn": "TOYOTA", "lm": "CAMRY", "lcy": 2018, "orr": 54321, "egn": "2.5L 4",
        "tmtp": "AUTOMATIC", "drv": "Front-wheel Drive", "ft": "GAS", "clr": "SILVER",
        "dd": "FRONT END", "sdd": "MINOR DENT/SCRATCHES", "hk": "YES", "lcd": "RUN AND DRIVE",
    }}}
    fotos = {"data": {"imagesList": {
        "FULL_IMAGE": [{"url": "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_ful.jpg"}],
        "HIGH_RESOLUTION_IMAGE": [{"url": "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_hrs.jpg"},
                                  {"url": "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/b_hrs.jpg"}],
    }}}
    cap = Captura(url="https://www.copart.com/lot/12345678", jsons=[("api1", api), ("api2", fotos)],
                  titulo="2018 TOYOTA CAMRY L for Sale")
    datos = combinar_specs("copart", cap)
    assert datos["titulo"] == "Toyota Camry 2018"
    assert datos["kilometraje"] == "87.421 km (54.321 millas)"
    assert datos["motor"] == "2.5L 4 cilindros"
    assert datos["combustible"] == "Bencina"
    assert datos["danio_principal"] == "Parte delantera"
    assert datos["danio_secundario"] == "Abolladuras/rayones menores"
    assert datos["estado"] == "Arranca y anda"
    assert datos["llaves"] == "Sí"
    assert [c[0] for c in candidatas(cap, "copart")] == [
        "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_hrs.jpg",
        "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/b_hrs.jpg",
    ]


def test_copart_sube_miniaturas_a_alta_resolucion():
    url = "https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/abc_thb.jpg"
    assert sites.version_alta_res("copart", url).endswith("abc_hrs.jpg")


def test_iaai_claves_de_imagen_y_pares():
    vm = {"inventoryView": {"imageDimensions": {"keys": {"$values": [
        {"K": "36123456~SID~B123~I1~RW2576~H1932~TH0", "AR": 1.33},
        {"K": "36123456~SID~B123~I2~RW2576~H1932~TH0", "AR": 1.33},
    ]}}}}
    pares = [("Odometer:", "45,210 mi (Actual)"), ("Primary Damage:", "Rear End"),
             ("Start Code:", "Run & Drive"), ("Key:", "Present"), ("Fuel Type:", "Gasoline")]
    cap = Captura(url="https://www.iaai.com/VehicleDetail/41234567~US", jsons=[("script", vm)],
                  pares=pares, h1="2020 HONDA CIVIC EX")
    datos = combinar_specs("iaai", cap)
    assert datos["titulo"] == "Honda Civic 2020"
    assert datos["version"] == "EX"
    assert datos["danio_principal"] == "Parte trasera"
    assert datos["estado"] == "Arranca y anda"
    assert datos["llaves"] == "Sí"
    fotos = candidatas(cap, "iaai")
    assert len(fotos) == 2
    assert "imageKeys=36123456" in fotos[0][0] and "width=1920" in fotos[0][0]


def test_imagenes_dom_filtra_logos_y_otros_dominios():
    cap = Captura(url="https://www.beforward.jp/x", imagenes_dom=[
        "https://image-cdn.beforward.jp/large/202401/1234/a.jpg",
        "https://image-cdn.beforward.jp/large/202401/1234/b.jpg",
        "https://image-cdn.beforward.jp/large/202401/1234/c.jpg",
        "https://image-cdn.beforward.jp/small/202401/9999/otro_auto.jpg",
        "https://www.beforward.jp/img/logo.png",
        "https://www.facebook.com/tr.jpg",
    ])
    urls = [c[0] for c in candidatas(cap, "beforward")]
    assert urls == [
        "https://image-cdn.beforward.jp/large/202401/1234/a.jpg",
        "https://image-cdn.beforward.jp/large/202401/1234/b.jpg",
        "https://image-cdn.beforward.jp/large/202401/1234/c.jpg",
    ]


def test_plantilla_omite_lineas_sin_dato():
    texto = generar_texto({"titulo": "Toyota Prius 2015", "anio": "2015", "motor": "1.790 cc"})
    assert "🚗 Toyota Prius 2015" in texto
    assert "⚙️ Motor: 1.790 cc" in texto
    assert "Kilometraje" not in texto
    assert "{" not in texto

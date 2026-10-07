"""Prueba de punta a punta de la versión online con un navegador real.

Levanta el servidor local que imita Netlify y un sitio de autos falso, y prueba:
  1. Pegar el link.
  2. El botón del navegador (bookmarklet) sobre la página del auto.
  3. El aviso cuando el sitio bloquea al servidor.

Ejecutar:  python -m pytest tests/e2e_test.py
Requiere: node, php (sitio falso), playwright y Chromium (ruta en CHROMIUM).
"""

import os
import shutil
import socket
import subprocess
import time
import urllib.parse

import pytest
from playwright.sync_api import sync_playwright

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITIO_FALSO = os.path.join(RAIZ, "..", "web", "tests", "sitio_falso.php")
CHROMIUM = os.environ.get("CHROMIUM", "/opt/pw-browsers/chromium-1194/chrome-linux/chrome")


def _puerto_libre():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def _esperar(puerto):
    for _ in range(80):
        try:
            socket.create_connection(("127.0.0.1", puerto), timeout=0.2).close()
            return
        except OSError:
            time.sleep(0.1)
    raise RuntimeError(f"El servidor en {puerto} no arrancó")


@pytest.fixture(scope="module")
def servidores():
    if not shutil.which("php") or not shutil.which("node") or not os.path.exists(CHROMIUM):
        pytest.skip("Falta php, node o Chromium")
    env = {k: v for k, v in os.environ.items() if "proxy" not in k.lower()}
    env["EXTRACTOR_RED_LOCAL"] = "1"
    p_app, p_sitio = _puerto_libre(), _puerto_libre()
    env["PUERTO"] = str(p_app)
    procesos = [
        subprocess.Popen(["node", os.path.join(RAIZ, "tests", "servidor-local.mjs")], env=env),
        subprocess.Popen(["php", "-S", f"127.0.0.1:{p_sitio}", SITIO_FALSO], env=env,
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL),
    ]
    _esperar(p_app)
    _esperar(p_sitio)
    # La app en "localhost" y el sitio en "127.0.0.1": son sitios distintos, como en la realidad
    yield f"http://localhost:{p_app}", f"http://127.0.0.1:{p_sitio}"
    for p in procesos:
        p.terminate()


@pytest.fixture()
def navegador():
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=CHROMIUM, headless=True)
        yield b
        b.close()


def _verificar_resultado(page):
    page.wait_for_url("**/resultado.html**", timeout=30000)
    page.wait_for_selector("#resultado:not([hidden])", timeout=30000)
    texto = page.input_value("#texto")
    for linea in ["🚗 Mazda CX-5 2019", "🛣️ Kilometraje: 32.000 km", "🕹️ Transmisión: Automática",
                  "⛽ Combustible: Bencina", "🎨 Color: Rojo", "⚙️ Motor: 2.5L 4 cilindros",
                  "🚙 Tracción: Integral (AWD)"]:
        assert linea in texto, f"falta «{linea}» en:\n{texto}"
    # 3 fotos: sin el logo ni la miniatura repetida
    page.wait_for_function("document.querySelector('#cantidad').textContent === '3'", timeout=30000)
    assert page.locator("#galeria img").count() == 3
    with page.expect_download() as descarga:
        page.click("#zip")
    ruta = descarga.value.path()
    with open(ruta, "rb") as f:
        assert f.read(2) == b"PK"


def test_pegar_link(servidores, navegador):
    app, sitio = servidores
    page = navegador.new_page()
    page.goto(app)
    page.fill("input[name=url]", f"{sitio}/auto")
    page.click("#form-link button")
    _verificar_resultado(page)


def test_boton_del_navegador(servidores, navegador):
    app, sitio = servidores
    page = navegador.new_page()
    page.goto(f"{app}/instalar.html")
    page.wait_for_function("document.querySelector('#marcador').href.startsWith('javascript:')")
    enlace = page.get_attribute("#marcador", "href")
    codigo = urllib.parse.unquote(enlace[len("javascript:"):])
    assert f"{app}/api/recibir" in codigo

    otra = navegador.new_context().new_page()  # otro navegador, sin nada guardado
    otra.goto(f"{sitio}/auto")
    otra.wait_for_load_state("networkidle")
    otra.evaluate(codigo)  # lo mismo que apretar el botón en la barra de favoritos
    _verificar_resultado(otra)


def test_sitio_que_bloquea_al_servidor(servidores, navegador):
    app, sitio = servidores
    page = navegador.new_page()
    page.goto(app)
    page.fill("input[name=url]", f"{sitio}/bloqueado")
    page.click("#form-link button")
    page.wait_for_selector("#fallo:not([hidden])", timeout=30000)
    assert "no permitió" in page.inner_text("#fallo-mensaje")
    assert page.is_visible("#fallo-bloqueo")


def test_plantilla_personalizada(servidores, navegador):
    app, sitio = servidores
    page = navegador.new_page()
    page.goto(f"{app}/plantilla.html")
    page.fill("#plantilla", "AUTO: {titulo}\nKM: {kilometraje}\nSIN DATO: {vin}\nLlama al +56 9 1234 5678")
    assert "AUTO: Toyota Corolla 2018" in page.inner_text("#previa")
    page.click("#guardar")
    page.goto(f"{app}/resultado.html?link=" + urllib.parse.quote(f"{sitio}/auto"))
    page.wait_for_selector("#resultado:not([hidden])", timeout=30000)
    assert page.input_value("#texto") == "AUTO: Mazda CX-5 2019\nKM: 32.000 km\nLlama al +56 9 1234 5678\n"

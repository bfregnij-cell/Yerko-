"""Prueba de punta a punta de la app PHP con un navegador real.

Levanta dos servidores PHP (la app y un sitio de autos falso) y prueba:
  1. Pegar el link en el formulario.
  2. El botón del navegador (bookmarklet) ejecutado sobre la página del auto.
  3. El aviso cuando el sitio bloquea al servidor.

Ejecutar:  python -m pytest tests/e2e_test.py
Requiere: php, playwright y Chromium (ruta configurable con CHROMIUM).
"""

import os
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.parse

import pytest
from playwright.sync_api import sync_playwright

WEB = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CHROMIUM = os.environ.get("CHROMIUM", "/opt/pw-browsers/chromium-1194/chrome-linux/chrome")


def _puerto_libre():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def _esperar(puerto):
    for _ in range(50):
        try:
            socket.create_connection(("127.0.0.1", puerto), timeout=0.2).close()
            return
        except OSError:
            time.sleep(0.1)
    raise RuntimeError(f"El servidor en {puerto} no arrancó")


def _levantar(raiz_app, host_app="127.0.0.1"):
    almacen = tempfile.mkdtemp()
    env = {k: v for k, v in os.environ.items() if "proxy" not in k.lower()}
    env["EXTRACTOR_RED_LOCAL"] = "1"
    env["EXTRACTOR_ALMACEN"] = almacen
    p_app, p_sitio = _puerto_libre(), _puerto_libre()
    procesos = [
        subprocess.Popen(["php", "-S", f"127.0.0.1:{p_app}", "-t", raiz_app], env=env, cwd=raiz_app,
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL),
        subprocess.Popen(["php", "-S", f"127.0.0.1:{p_sitio}", os.path.join(WEB, "tests", "sitio_falso.php")],
                         env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL),
    ]
    _esperar(p_app)
    _esperar(p_sitio)
    return procesos, almacen, f"http://{host_app}:{p_app}/index.php", f"http://127.0.0.1:{p_sitio}"


@pytest.fixture(scope="module")
def servidores():
    if not shutil.which("php") or not os.path.exists(CHROMIUM):
        pytest.skip("Falta php o Chromium")
    procesos, almacen, app, sitio = _levantar(WEB)
    yield app, sitio
    for p in procesos:
        p.terminate()
    shutil.rmtree(almacen, ignore_errors=True)


@pytest.fixture(scope="module")
def servidores_con_clave():
    """Copia de la app con contraseña. La app corre en 'localhost' y el sitio en '127.0.0.1'
    para que sean sitios distintos, como en la realidad (cookies SameSite)."""
    if not shutil.which("php") or not os.path.exists(CHROMIUM):
        pytest.skip("Falta php o Chromium")
    copia = tempfile.mkdtemp()
    shutil.copytree(WEB, copia, dirs_exist_ok=True)
    config = os.path.join(copia, "config.php")
    with open(config, encoding="utf-8") as f:
        contenido = f.read().replace("'clave' => '',", "'clave' => 'secreta123',")
    with open(config, "w", encoding="utf-8") as f:
        f.write(contenido)
    procesos, almacen, app, sitio = _levantar(copia, host_app="localhost")
    yield app, sitio
    for p in procesos:
        p.terminate()
    shutil.rmtree(almacen, ignore_errors=True)
    shutil.rmtree(copia, ignore_errors=True)


@pytest.fixture()
def pagina():
    with sync_playwright() as p:
        navegador = p.chromium.launch(executable_path=CHROMIUM, headless=True)
        yield navegador.new_page()
        navegador.close()


def _verificar_resultado(page):
    page.wait_for_url("**r=resultado**", timeout=60000)
    texto = page.input_value("#texto")
    assert "🚗 Mazda CX-5 2019" in texto
    assert "🛣️ Kilometraje: 32.000 km" in texto
    assert "🕹️ Transmisión: Automática" in texto
    assert "⛽ Combustible: Bencina" in texto
    assert "🎨 Color: Rojo" in texto
    assert "⚙️ Motor: 2.5L 4 cilindros" in texto
    assert "🚙 Tracción: Integral (AWD)" in texto
    # 3 fotos: sin el logo ni la miniatura repetida
    assert page.locator(".galeria img").count() == 3
    primera = page.locator(".galeria img").first.get_attribute("src")
    respuesta = page.request.get(urllib.parse.urljoin(page.url, primera))
    assert respuesta.ok and respuesta.headers["content-type"] == "image/jpeg"
    zip_url = urllib.parse.urljoin(page.url, page.get_attribute("a[href*='r=zip']", "href"))
    zip_resp = page.request.get(zip_url)
    assert zip_resp.ok and zip_resp.body()[:2] == b"PK"


def test_pegar_link(servidores, pagina):
    app, sitio = servidores
    pagina.goto(app)
    pagina.fill("input[name=url]", f"{sitio}/auto")
    pagina.click("form[action*='r=extraer'] button")
    _verificar_resultado(pagina)


def test_boton_del_navegador(servidores, pagina):
    app, sitio = servidores
    pagina.goto(f"{app}?r=instalar")
    enlace = pagina.get_attribute("a.marcador", "href")
    assert enlace.startswith("javascript:")
    codigo = urllib.parse.unquote(enlace[len("javascript:"):])

    pagina.goto(f"{sitio}/auto")
    pagina.wait_for_load_state("networkidle")
    pagina.evaluate(codigo)  # lo mismo que apretar el botón en la barra de favoritos
    _verificar_resultado(pagina)


def test_sitio_que_bloquea_al_servidor(servidores, pagina):
    app, sitio = servidores
    pagina.goto(app)
    pagina.fill("input[name=url]", f"{sitio}/bloqueado")
    pagina.click("form[action*='r=extraer'] button")
    pagina.wait_for_selector("#fallo:not(.oculto)", timeout=30000)
    assert "no permitió" in pagina.inner_text("#fallo-mensaje")
    assert pagina.is_visible("#fallo-bloqueo")


def test_con_clave_pide_entrar_y_el_boton_funciona(servidores_con_clave):
    app, sitio = servidores_con_clave
    with sync_playwright() as p:
        navegador = p.chromium.launch(executable_path=CHROMIUM, headless=True)
        # 1. Instalar el botón (requiere entrar con la contraseña)
        page = navegador.new_page()
        page.goto(f"{app}?r=instalar")
        assert "r=acceso" in page.url
        page.fill("input[name=clave]", "mala")
        page.click("button[type=submit]")
        assert "incorrecta" in page.inner_text("body")
        page.fill("input[name=clave]", "secreta123")
        page.click("button[type=submit]")
        page.goto(f"{app}?r=instalar")
        codigo = urllib.parse.unquote(page.get_attribute("a.marcador", "href")[len("javascript:"):])
        assert "secreta123" not in codigo  # el botón lleva un token, no la contraseña

        # 2. Otro navegador sin sesión: el token del botón basta para extraer
        otro = navegador.new_context().new_page()
        otro.goto(f"{sitio}/auto")
        otro.wait_for_load_state("networkidle")
        otro.evaluate(codigo)
        _verificar_resultado(otro)

        # 3. Un botón con token falso es rechazado
        otro2 = navegador.new_context().new_page()
        otro2.goto(f"{sitio}/auto")
        otro2.evaluate(codigo.replace("TOKEN = '", "TOKEN = 'x"))
        otro2.wait_for_url("**r=recibir**")
        assert "no es válido" in otro2.inner_text("body")
        navegador.close()

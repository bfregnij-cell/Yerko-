"""Prueba de punta a punta con un navegador real contra una página local.

Requiere Chromium; se salta si no está disponible. Ruta configurable con EXTRACTOR_NAVEGADOR.
"""

import http.server
import io
import os
import sys
import threading

import pytest
from PIL import Image

from extractor.core import extraer

CHROMIUM = os.environ.get("EXTRACTOR_NAVEGADOR", "/opt/pw-browsers/chromium-1194/chrome-linux/chrome")
# En Windows se prueba con el Edge instalado, igual que lo usará el programa
USAR_EDGE = sys.platform.startswith("win") and not os.path.exists(CHROMIUM)

PAGINA = """<!doctype html><html><head><title>2019 MAZDA CX-5 GRAND TOURING | Autos</title>
<meta property="og:image" content="/fotos/auto/01.jpg"></head><body>
<img src="/img/logo.png" alt="logo">
<h1>2019 MAZDA CX-5 GRAND TOURING</h1>
<div id="galeria">
  <img src="/fotos/auto/01.jpg">
  <img data-src="/fotos/auto/02.jpg" class="lazy">
  <img srcset="/fotos/auto/03_chica.jpg 300w, /fotos/auto/03.jpg 1200w">
</div>
<table>
  <tr><th>Mileage</th><td>32,000 km</td><th>Transmission</th><td>Automatic</td></tr>
  <tr><th>Fuel</th><td>Gasoline</td><th>Exterior Color</th><td>Red</td></tr>
</table>
<ul><li><span>Engine:</span><span>2.5L 4</span></li><li>Drive: AWD</li></ul>
<script>
  document.querySelectorAll('img.lazy').forEach(i => i.src = i.dataset.src);
</script>
</body></html>"""


def _jpg(ancho, alto, color):
    b = io.BytesIO()
    Image.new("RGB", (ancho, alto), color).save(b, "JPEG")
    return b.getvalue()


ARCHIVOS = {
    "/auto": ("text/html; charset=utf-8", PAGINA.encode()),
    "/img/logo.png": ("image/jpeg", _jpg(120, 40, "black")),
    "/fotos/auto/01.jpg": ("image/jpeg", _jpg(1024, 768, "red")),
    "/fotos/auto/02.jpg": ("image/jpeg", _jpg(1024, 768, "blue")),
    "/fotos/auto/03.jpg": ("image/jpeg", _jpg(1200, 900, "green")),
    "/fotos/auto/03_chica.jpg": ("image/jpeg", _jpg(300, 225, "green")),
}


class Manejador(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        tipo, cuerpo = ARCHIVOS.get(self.path, ("text/plain", b""))
        self.send_response(200 if self.path in ARCHIVOS else 404)
        self.send_header("Content-Type", tipo)
        self.send_header("Content-Length", str(len(cuerpo)))
        self.end_headers()
        self.wfile.write(cuerpo)

    def log_message(self, *a):
        pass


@pytest.mark.skipif(not (USAR_EDGE or os.path.exists(CHROMIUM)), reason="Navegador no disponible")
def test_extraccion_completa(tmp_path, monkeypatch):
    if USAR_EDGE:
        monkeypatch.delenv("EXTRACTOR_NAVEGADOR", raising=False)
    else:
        monkeypatch.setenv("EXTRACTOR_NAVEGADOR", CHROMIUM)
    monkeypatch.setenv("LOCALAPPDATA", str(tmp_path / "appdata"))
    for v in ("HTTP_PROXY", "HTTPS_PROXY", "http_proxy", "https_proxy"):
        monkeypatch.delenv(v, raising=False)
    servidor = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Manejador)
    threading.Thread(target=servidor.serve_forever, daemon=True).start()
    try:
        url = f"http://127.0.0.1:{servidor.server_port}/auto"
        res = extraer(url, carpeta_base=str(tmp_path / "salida"), headless=True, log=lambda m: None)
    finally:
        servidor.shutdown()

    assert res.sitio == "generico"
    assert len(res.fotos) == 3  # sin logo ni miniatura duplicada
    d = res.datos
    assert d["titulo"] == "Mazda CX-5 2019"
    assert d["kilometraje"] == "32.000 km"
    assert d["transmision"] == "Automática"
    assert d["combustible"] == "Bencina"
    assert d["color"] == "Rojo"
    assert d["motor"] == "2.5L 4 cilindros"
    assert d["traccion"] == "Integral (AWD)"
    assert os.path.exists(os.path.join(res.carpeta, "publicacion.txt"))
    assert "🛣️ Kilometraje: 32.000 km" in res.texto

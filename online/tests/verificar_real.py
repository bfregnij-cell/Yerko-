"""Verificación contra los sitios reales y el sitio publicado.

Abre un auto real de cada proveedor en un navegador, aprieta el botón
"Extraer auto" del sitio publicado y revisa el resultado. También prueba el
modo "pegar el link". Guarda capturas de pantalla en verificacion/.

    APP=https://extractor-autos.netlify.app python tests/verificar_real.py
"""

import json
import os
import re
import sys
import urllib.parse

from playwright.sync_api import sync_playwright

APP = os.environ.get("APP", "https://extractor-autos.netlify.app").rstrip("/")
SALIDA = "verificacion"
AGENTE = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
          "(KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36")

SITIOS = {
    "beforward": ("https://www.beforward.jp/stocklist/sortkey=n", r"beforward\.jp/[^\"']+/id/\d+"),
    "copart": ("https://www.copart.com/", r"copart\.com/lot/\d+"),
    "iaai": ("https://www.iaai.com/", r"iaai\.com/VehicleDetail/\d+"),
}

os.makedirs(SALIDA, exist_ok=True)
informe = {}


def log(*a):
    print(*a, flush=True)


def leer_resultado(page, nombre):
    """Espera el resultado (o el error) y devuelve lo que muestra la página."""
    page.wait_for_selector("#resultado:not([hidden]), #fallo:not([hidden])", timeout=90000)
    if page.is_visible("#fallo"):
        return {"ok": False, "error": page.inner_text("#fallo-mensaje")}
    try:
        page.wait_for_function("document.querySelector('#cantidad').textContent !== '…'", timeout=120000)
    except Exception:
        pass
    page.screenshot(path=f"{SALIDA}/{nombre}_resultado.png", full_page=True)
    filas = page.eval_on_selector_all(
        "#tabla-datos tr", "rs => rs.map(r => [r.cells[0].textContent, r.cells[1] ? r.cells[1].textContent : ''])")
    pares = page.eval_on_selector_all("#tabla-pares tr", "rs => rs.length")
    return {
        "ok": True,
        "fotos": page.inner_text("#cantidad"),
        "fotos_no_descargadas": page.inner_text("#remotas-cantidad") if page.is_visible("#remotas") else "0",
        "texto": page.input_value("#texto"),
        "datos": filas,
        "pares_leidos": pares,
    }


def main():
    with sync_playwright() as p:
        navegador = p.chromium.launch(headless=False, args=["--disable-blink-features=AutomationControlled"])
        ctx = navegador.new_context(user_agent=AGENTE, locale="en-US", viewport={"width": 1366, "height": 900})

        # 1. El sitio publicado responde y entrega el botón
        page = ctx.new_page()
        page.goto(f"{APP}/instalar.html")
        page.wait_for_function("document.querySelector('#marcador').href.startsWith('javascript:')", timeout=30000)
        codigo = urllib.parse.unquote(page.get_attribute("#marcador", "href")[len("javascript:"):])
        r = page.request.get(f"{APP}/api/captura?id=invalido")
        log("Sitio publicado OK. /api/captura con id inválido ->", r.status)

        for nombre, (portada, patron) in SITIOS.items():
            log(f"\n===== {nombre} =====")
            res = informe.setdefault(nombre, {})
            pag = ctx.new_page()
            try:
                pag.goto(portada, wait_until="domcontentloaded", timeout=60000)
                pag.wait_for_timeout(6000)
                pag.screenshot(path=f"{SALIDA}/{nombre}_portada.png")
                html = pag.content()
                enlaces = [a for a in pag.eval_on_selector_all("a[href]", "as => as.map(a => a.href)")
                           if re.search(patron, a)]
                res["titulo_portada"] = pag.title()
                if not enlaces:
                    res["error"] = "No se encontró ningún auto en la portada (¿bloqueo anti-robots?)"
                    res["inicio_html"] = html[:300]
                    log(res["error"], "| título:", pag.title())
                    continue
                auto = enlaces[0]
                res["auto"] = auto
                log("Auto:", auto)

                # 2. Botón del navegador
                pag.goto(auto, wait_until="domcontentloaded", timeout=60000)
                try:
                    pag.wait_for_load_state("networkidle", timeout=25000)
                except Exception:
                    pass
                pag.wait_for_timeout(3000)
                pag.screenshot(path=f"{SALIDA}/{nombre}_auto.png")
                res["titulo_auto"] = pag.title()
                pag.evaluate(codigo)
                res["boton"] = leer_resultado(pag, f"{nombre}_boton")
                log("Botón:", json.dumps(res["boton"], ensure_ascii=False, indent=1)[:3000])

                # 3. Pegar el link (el servidor descarga la página por su cuenta)
                pag2 = ctx.new_page()
                pag2.goto(f"{APP}/resultado.html?link=" + urllib.parse.quote(auto, safe=""))
                res["link"] = leer_resultado(pag2, f"{nombre}_link")
                log("Link:", json.dumps({k: v for k, v in res["link"].items() if k != "datos"},
                                         ensure_ascii=False)[:1500])
            except Exception as e:
                res["excepcion"] = str(e)[:500]
                log("Excepción:", e)
                try:
                    pag.screenshot(path=f"{SALIDA}/{nombre}_error.png")
                except Exception:
                    pass
        navegador.close()

    with open(f"{SALIDA}/informe.json", "w", encoding="utf-8") as f:
        json.dump(informe, f, ensure_ascii=False, indent=2)
    log("\n===== RESUMEN =====")
    for nombre, res in informe.items():
        b, l = res.get("boton", {}), res.get("link", {})
        log(f"{nombre}: botón={'OK ' + b.get('fotos', '?') + ' fotos' if b.get('ok') else b.get('error') or res.get('error') or res.get('excepcion')}"
            f" | link={'OK ' + l.get('fotos', '?') + ' fotos' if l.get('ok') else l.get('error')}")


if __name__ == "__main__":
    sys.exit(main())

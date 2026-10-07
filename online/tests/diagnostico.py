"""Imprime la estructura de la página de un auto real (para ajustar los extractores).

    SITIO=beforward python tests/diagnostico.py
"""
import os
import re

from playwright.sync_api import sync_playwright

PORTADAS = {
    "beforward": ("https://www.beforward.jp/stocklist/sortkey=n", r"beforward\.jp/[^\"']+/id/\d+"),
    "iaai": ("https://www.iaai.com/", r"iaai\.com/VehicleDetail/\d+"),
    "copart": ("https://www.copart.com/", r"copart\.com/lot/\d+"),
}
AGENTE = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
          "(KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36")

JS = r"""
() => {
  const limpio = el => el.outerHTML.replace(/\s+/g, ' ').replace(/ (style|onclick|data-[\w-]+)="[^"]*"/g, '');
  const tablas = [...document.querySelectorAll('table')].map(limpio);
  const h1 = [...document.querySelectorAll('h1, h2')].slice(0, 6).map(limpio);
  const imgs = [...document.querySelectorAll('img')].map(i => {
    let p = i.parentElement, ruta = [];
    for (let k = 0; k < 4 && p; k++, p = p.parentElement) ruta.push(p.tagName.toLowerCase() + (p.id ? '#' + p.id : '') + (p.className && typeof p.className === 'string' ? '.' + p.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    return [i.naturalWidth + 'x' + i.naturalHeight, (i.currentSrc || i.src).slice(0, 160), ruta.join(' < ')];
  });
  return { titulo: document.title, h1, tablas, imgs };
}
"""

sitio = os.environ.get("SITIO", "beforward")
portada, patron = PORTADAS[sitio]
with sync_playwright() as p:
    b = p.chromium.launch(headless=False, args=["--disable-blink-features=AutomationControlled"])
    page = b.new_context(user_agent=AGENTE, locale="en-US").new_page()
    page.goto(portada, wait_until="domcontentloaded", timeout=60000)
    page.wait_for_timeout(5000)
    auto = next(a for a in page.eval_on_selector_all("a[href]", "as => as.map(a => a.href)") if re.search(patron, a))
    print("AUTO:", auto)
    page.goto(auto, wait_until="domcontentloaded", timeout=60000)
    try:
        page.wait_for_load_state("networkidle", timeout=25000)
    except Exception:
        pass
    page.wait_for_timeout(3000)
    d = page.evaluate(JS)
    print("TITULO:", d["titulo"])
    for h in d["h1"]:
        print("ENCABEZADO:", h[:600])
    for i, t in enumerate(d["tablas"]):
        print(f"--- TABLA {i} ({len(t)} car.) ---")
        print(t[:4000])
    print(f"--- IMAGENES ({len(d['imgs'])}) ---")
    for im in d["imgs"][:150]:
        print(" | ".join(im))
    b.close()

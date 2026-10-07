# Extractor de Autos

Programa de escritorio para Windows: pegas el link de un auto y obtienes **todas sus fotos** y un **texto con la ficha técnica** listo para Facebook / Marketplace. Funciona sin IA, solo con reglas programadas.

Sitios soportados: **BE FORWARD** (beforward.jp), **Copart** (copart.com) e **IAAI** (iaai.com). Con otros sitios intenta un modo genérico.

## Descargar el programa

1. Pestaña **Actions** del repositorio → última ejecución de **"Compilar para Windows"** (en verde).
2. Abajo, en *Artifacts*, descarga **ExtractorAutos-Windows**.
3. Descomprime el zip y abre `ExtractorAutos.exe`. Las instrucciones de uso están en `LEEME.txt`.

## Cómo funciona

| Paso | Detalle |
|---|---|
| Detectar sitio | Por el dominio del link (`extractor/sites.py`). |
| Abrir la página | Con el Edge/Chrome del PC vía Playwright, con perfil propio para mantener cookies. Si aparece un captcha, el usuario lo resuelve en la ventana. |
| Especificaciones | 1) Datos internos del sitio (API JSON de Copart), 2) tablas y pares "Etiqueta: valor" de la página, 3) el título. Se normalizan y traducen al español (`extractor/specs.py`). |
| Fotos | Lista oficial del sitio cuando existe (Copart `imagesList`, claves de imagen de IAAI); si no, imágenes de la página filtradas por dominio y carpeta. Se pide la versión en alta resolución, se descartan las menores a 480×320 y los duplicados (`extractor/imagenes.py`). |
| Texto | `plantilla.txt` editable; las líneas sin dato se omiten (`extractor/formatter.py`). |

## Desarrollo

```bash
pip install -r requirements.txt pytest
python -m pytest tests     # pruebas
python app.py              # abrir la ventana
```

# Extractor de Autos — versión de escritorio (Windows)

Programa para Windows: pegas el link de un auto y obtienes todas sus fotos y un texto con la ficha técnica. Usa el Edge o Chrome del PC (vía Playwright), así que la página carga como en un navegador normal.

## Descargar

En la pestaña **Actions** del repositorio, abre la última ejecución de **"Compilar para Windows"** y descarga el artifact **ExtractorAutos-Windows**. Descomprime el zip y abre `ExtractorAutos.exe`. Las instrucciones para el usuario están en `LEEME.txt`.

## Desarrollo

```bash
pip install -r requirements.txt pytest
python -m pytest tests
python app.py
```

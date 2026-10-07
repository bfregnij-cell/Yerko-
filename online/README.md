# Extractor de Autos — versión online (Netlify)

La misma aplicación que la versión PHP (`web/`), con el mismo diseño orientado a objetos, adaptada para publicarse en **Netlify** con un link para compartir. No requiere hosting propio.

- **Interfaz** (`public/`): páginas HTML y JavaScript con controladores, modelos (`Captura`), extractores (`Extractor` → `BeForwardExtractor`, `CopartExtractor`, `IaaiExtractor`, `GenericoExtractor`, más `ExtractorFactory`) y servicios (`NormalizadorSpecs`, `GeneradorPublicacion`, `LectorHtml`, `DescargadorFotos`). La extracción se hace en el navegador.
- **Servidor** (`netlify/functions/`):
  - `/api/recibir` guarda lo que envía el botón del navegador (Netlify Blobs, 7 días).
  - `/api/captura` devuelve esos datos.
  - `/api/pagina` descarga una página cuando se pega el link.
  - `/api/imagen` descarga las fotos para armar el ZIP.

  Solo se conecta a direcciones públicas.
- **Plantilla:** se edita en *Plantilla* y se guarda en el navegador de cada usuario.

## Publicar

Se publica desde GitHub Actions con el workflow **"Versión online (Netlify)"**. Primero corre las pruebas y luego publica con la credencial temporal que entrega el conector de Netlify.

## Pruebas

```bash
npm install
npm test                                   # unitarias
python -m pytest tests/e2e_test.py         # punta a punta (navegador real + sitio falso)
```

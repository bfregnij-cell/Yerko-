# Extractor de Autos — versión web (PHP)

Aplicación PHP con arquitectura **MVC** y **programación orientada a objetos**, sin frameworks ni Composer, para que funcione en cualquier hosting. Toma un auto de **BE FORWARD, Copart o IAAI** y entrega todas sus fotos y un texto con la ficha técnica, listo para Facebook o Marketplace. No usa IA.

## Cómo se usa

1. **Botón del navegador (recomendado, sirve para los 3 sitios).** Se instala una vez desde *Instalar botón*. Después, con la página del auto abierta, se aprieta **“Extraer auto”** en la barra de favoritos. Como la página la abrió el usuario en su propio navegador, los sistemas anti-robots de Copart e IAAI no la bloquean.
2. **Pegar el link.** El servidor descarga la página por su cuenta. Funciona con BE FORWARD. Copart e IAAI suelen bloquear a los servidores, y en ese caso la app avisa y sugiere usar el botón.

El resultado muestra el texto (editable, con botón para copiar), la galería de fotos y un botón para descargarlas todas en un ZIP.

## Instalación en el hosting

**Requisitos:** PHP **8.1 o superior** con las extensiones `curl`, `dom`, `mbstring`, `json` y `zip`. Vienen activadas en casi todos los hostings. No necesita base de datos.

1. Sube la carpeta `web/` completa al hosting, por ejemplo a `public_html/extractor/`.
2. Verifica que PHP pueda escribir en la carpeta `almacen/` (permisos 755 o 775).
3. **Recomendado:** abre `config.php` y define una contraseña en `'clave' => 'tu-clave'`.
4. Entra a `https://tu-dominio.cl/extractor/`, ve a **Instalar botón** y arrastra el botón verde a la barra de favoritos.

> Usa **HTTPS**. Algunos navegadores no dejan que una página `https` (como Copart) envíe datos a un sitio `http`.

Los resultados se guardan en `almacen/resultados/` y se borran solos después de 7 días (se cambia en `config.php`). El texto de la publicación se edita desde la sección **Plantilla**.

## Estructura (MVC)

```
web/
├── index.php                 Controlador frontal (único punto de entrada)
├── config.php                Configuración
├── src/
│   ├── Core/                 App (enrutador), Controlador (base), Vista
│   ├── Controladores/        Extraccion, Instalar, Plantilla, Acceso
│   ├── Modelos/              Vehiculo, Captura, RepositorioVehiculos
│   ├── Extractores/          Extractor (abstracta) → BeForward, Copart, Iaai, Generico
│   │                         + ExtractorFactory (detecta el sitio por el dominio)
│   └── Servicios/            ServicioExtraccion, ClienteHttp, LectorHtml, DescargadorFotos,
│                             NormalizadorSpecs, GeneradorPublicacion, Marcador, Autenticacion
├── vistas/                   Plantillas HTML (inicio, procesando, resultado, instalar, …)
├── assets/                   CSS, JS de la interfaz y código del botón (marcador.js)
├── almacen/                  Resultados (protegido, no accesible desde la web)
└── tests/                    Pruebas unitarias (PHP) y de punta a punta (navegador real)
```

### Cómo funciona

| Paso | Clase | Detalle |
|---|---|---|
| Recibir | `ExtraccionController` | Guarda el trabajo y responde al instante. La página *Procesando* lo ejecuta después. |
| Leer la página | `LectorHtml` / `marcador.js` | Pares etiqueta/valor (tablas, listas, “Etiqueta: valor”), imágenes y JSON incrustados. |
| Detectar el sitio | `ExtractorFactory` | Elige el extractor según el dominio. |
| Especificaciones | `Extractor` + subclases | Prioridad: datos internos del sitio (API de Copart), luego tablas de la página, luego el título. |
| Traducir | `NormalizadorSpecs` | Etiquetas a campos fijos, valores al español, millas a km. |
| Fotos | `Extractor::candidatas`, `DescargadorFotos` | Lista oficial del sitio cuando existe. Pide alta resolución y descarta miniaturas (< 480×320), logos y repetidas. |
| Texto | `GeneradorPublicacion` | Plantilla editable. Una línea sin dato se omite. |

### Agregar otro sitio

Crea una clase en `src/Extractores/` que herede de `Extractor`, implementa `nombre()`, `clave()` y `coincide()`, y sobrescribe lo que necesites (`specsDesdeJson`, `imagenesOficiales`, `altaResolucion`). Luego agrégala en `ExtractorFactory`.

## Seguridad

- Contraseña opcional. El botón lleva un **token** derivado de la contraseña, nunca la contraseña misma.
- Protección CSRF en los formularios y salida HTML escapada.
- `ClienteHttp` solo descarga desde direcciones **públicas**, para que nadie use el servidor para entrar a la red interna. Además fija la IP validada y limita el tamaño de cada respuesta.
- `almacen/`, `src/`, `vistas/` y `config.php` están bloqueados desde la web (`.htaccess`).

## Pruebas

```bash
php tests/unidad.php                       # pruebas unitarias
pip install playwright pytest
python -m pytest tests/e2e_test.py         # punta a punta (PHP + navegador real)
```

<?php use App\Core\Vista; ?>
<section class="tarjeta centrado" id="procesando"
         data-id="<?= Vista::e($id) ?>" data-csrf="<?= Vista::e($csrf) ?>">
  <div class="spinner" aria-hidden="true"></div>
  <h2>Extrayendo datos y descargando fotos…</h2>
  <p class="nota">Puede tardar entre 10 y 60 segundos según la cantidad de fotos. No cierres esta página.</p>
</section>

<section class="tarjeta oculto" id="fallo">
  <h2>No se pudo completar</h2>
  <p id="fallo-mensaje" class="alerta alerta-error"></p>
  <div id="fallo-bloqueo" class="oculto">
    <p>Este sitio no deja que el servidor lea sus páginas. Usa el <strong>botón “Extraer auto”</strong> de tu barra
      de favoritos estando en la página del auto.</p>
    <a class="boton" href="index.php?r=instalar">Instalar el botón</a>
  </div>
  <p><a href="index.php">← Volver</a></p>
</section>

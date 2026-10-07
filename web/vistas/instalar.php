<?php use App\Core\Vista; ?>
<section class="tarjeta">
  <h1>Instalar el botón “Extraer auto”</h1>
  <p>Se instala <strong>una sola vez</strong> en el computador (Chrome, Edge o Firefox).</p>

  <ol class="pasos">
    <li>Muestra la barra de favoritos: presiona <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>B</kbd>
      (en Mac: <kbd>⌘</kbd> + <kbd>Shift</kbd> + <kbd>B</kbd>).</li>
    <li><strong>Arrastra</strong> este botón verde hasta la barra de favoritos:
      <p class="centrado">
        <a class="boton marcador" href="<?= Vista::e($enlace) ?>"
           onclick="alert('No hagas clic aquí: arrastra el botón a la barra de favoritos.'); return false;">🚗 Extraer auto</a>
      </p>
    </li>
    <li>Listo. Cuando estés en la página de un auto en <strong>BE FORWARD, Copart o IAAI</strong>, espera que cargue
      y aprieta <strong>“Extraer auto”</strong> en la barra de favoritos.</li>
  </ol>

  <details>
    <summary>¿No puedes arrastrarlo?</summary>
    <ol>
      <li>Copia el código de abajo.</li>
      <li>Clic derecho en la barra de favoritos → “Agregar página…”.</li>
      <li>Nombre: <em>Extraer auto</em>. En “URL” pega el código. Guarda.</li>
    </ol>
    <textarea id="codigo-marcador" rows="4" readonly><?= Vista::e($enlace) ?></textarea>
    <button class="boton secundario" type="button" data-copiar="#codigo-marcador">Copiar código</button>
  </details>

  <p class="nota">Si cambias la contraseña del sistema, tendrás que instalar el botón de nuevo.</p>
</section>

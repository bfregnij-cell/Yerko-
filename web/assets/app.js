/* Botones "Copiar" y página de espera. */
(function () {
  document.querySelectorAll('[data-copiar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var campo = document.querySelector(boton.getAttribute('data-copiar'));
      var original = boton.textContent;
      var listo = function () {
        boton.textContent = '✅ ¡Copiado!';
        setTimeout(function () { boton.textContent = original; }, 2000);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(campo.value).then(listo);
      } else {
        campo.select();
        document.execCommand('copy');
        listo();
      }
    });
  });

  var espera = document.getElementById('procesando');
  if (espera) {
    var cuerpo = new URLSearchParams({ csrf: espera.dataset.csrf });
    fetch('index.php?r=ejecutar&id=' + encodeURIComponent(espera.dataset.id), { method: 'POST', body: cuerpo })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) {
          location.replace(res.redirigir);
          return;
        }
        espera.classList.add('oculto');
        document.getElementById('fallo').classList.remove('oculto');
        document.getElementById('fallo-mensaje').textContent = res.error || 'Error desconocido.';
        if (res.bloqueado) document.getElementById('fallo-bloqueo').classList.remove('oculto');
      })
      .catch(function () {
        espera.classList.add('oculto');
        document.getElementById('fallo').classList.remove('oculto');
        document.getElementById('fallo-mensaje').textContent =
          'Se perdió la conexión con el servidor o tardó demasiado. Inténtalo de nuevo.';
      });
  }
})();

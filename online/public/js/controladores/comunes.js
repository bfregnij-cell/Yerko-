/** Botones "Copiar" (data-copiar="#selector") de todas las páginas. */
export function activarBotonesCopiar() {
  document.querySelectorAll("[data-copiar]").forEach((boton) => {
    boton.addEventListener("click", () => {
      const campo = document.querySelector(boton.getAttribute("data-copiar"));
      const original = boton.textContent;
      const listo = () => {
        boton.textContent = "✅ ¡Copiado!";
        setTimeout(() => (boton.textContent = original), 2000);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(campo.value).then(listo);
      } else {
        campo.select();
        document.execCommand("copy");
        listo();
      }
    });
  });
}

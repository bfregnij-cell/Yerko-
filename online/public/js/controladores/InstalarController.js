/**
 * Arma el botón "Extraer auto" (bookmarklet) con la dirección de este sitio.
 */
export class InstalarController {
  static async codigo(origen) {
    const fuente = await (await fetch("/js/marcador-fuente.js")).text();
    return fuente
      .replace(/\/\*[\s\S]*?\*\//g, "")
      .split("\n")
      .map((l) => l.trim())
      .filter(Boolean)
      .join("\n")
      .replace("__DESTINO__", `${origen}/api/recibir`);
  }

  async iniciar() {
    const enlace = "javascript:" + encodeURIComponent(await InstalarController.codigo(location.origin));
    const boton = document.querySelector("#marcador");
    boton.href = enlace;
    boton.addEventListener("click", (e) => {
      e.preventDefault();
      alert("No hagas clic aquí: arrastra el botón a la barra de favoritos.");
    });
    document.querySelector("#codigo-marcador").value = enlace;
  }
}

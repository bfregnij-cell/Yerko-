import { GeneradorPublicacion, RepositorioPlantilla } from "../servicios/GeneradorPublicacion.js";
import { CAMPOS } from "../servicios/NormalizadorSpecs.js";

const EJEMPLO = {
  titulo: "Toyota Corolla 2018", marca: "Toyota", modelo: "Corolla", anio: "2018",
  kilometraje: "87.421 km (54.321 millas)", motor: "1.8L 4 cilindros", transmision: "Automática",
  combustible: "Bencina", color: "Plateado", danio_principal: "Parte delantera",
  estado: "Arranca y anda", llaves: "Sí", fuente: "Copart", link: "https://…",
};

/** Edición de la plantilla del texto (se guarda en este navegador). */
export class PlantillaController {
  iniciar() {
    const area = document.querySelector("#plantilla");
    const previa = document.querySelector("#previa");
    const aviso = document.querySelector("#aviso");
    const actualizar = () => (previa.textContent = new GeneradorPublicacion(area.value).generar(EJEMPLO));

    area.value = RepositorioPlantilla.leer();
    actualizar();
    area.addEventListener("input", actualizar);

    document.querySelector("#guardar").addEventListener("click", () => {
      RepositorioPlantilla.guardar(area.value);
      aviso.textContent = "Plantilla guardada.";
      aviso.hidden = false;
    });
    document.querySelector("#restaurar").addEventListener("click", () => {
      if (!confirm("¿Volver a la plantilla original?")) return;
      RepositorioPlantilla.restaurar();
      area.value = RepositorioPlantilla.leer();
      actualizar();
      aviso.textContent = "Se restauró la plantilla original.";
      aviso.hidden = false;
    });

    document.querySelector("#campos").textContent = ["titulo", ...CAMPOS, "fuente", "link"]
      .map((c) => `{${c}}`)
      .join("  ");
  }
}

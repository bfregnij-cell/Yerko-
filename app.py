"""Extractor de Autos: ventana principal."""

import os
import queue
import subprocess
import sys
import threading
import tkinter as tk
from tkinter import messagebox, scrolledtext, ttk

from extractor.core import carpeta_salida_por_defecto, extraer
from extractor.formatter import PLANTILLA_POR_DEFECTO

NOMBRE = "Extractor de Autos"


def carpeta_app():
    if getattr(sys, "frozen", False):
        return os.path.dirname(sys.executable)
    return os.path.dirname(os.path.abspath(__file__))


def ruta_plantilla():
    ruta = os.path.join(carpeta_app(), "plantilla.txt")
    if not os.path.exists(ruta):
        try:
            with open(ruta, "w", encoding="utf-8") as f:
                f.write(PLANTILLA_POR_DEFECTO)
        except OSError:
            return None
    return ruta


def leer_plantilla():
    ruta = ruta_plantilla()
    if ruta:
        try:
            with open(ruta, encoding="utf-8") as f:
                return f.read()
        except OSError:
            pass
    return PLANTILLA_POR_DEFECTO


def abrir(ruta):
    if sys.platform.startswith("win"):
        os.startfile(ruta)
    elif sys.platform == "darwin":
        subprocess.Popen(["open", ruta])
    else:
        subprocess.Popen(["xdg-open", ruta])


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title(NOMBRE)
        self.geometry("820x680")
        self.minsize(640, 520)
        self.cola = queue.Queue()
        self.carpeta = None
        self._construir()
        self.after(100, self._revisar_cola)

    def _construir(self):
        pad = {"padx": 12, "pady": 6}
        ttk.Label(self, text="Pega el link del auto (BE FORWARD, Copart o IAAI):",
                  font=("Segoe UI", 11, "bold")).pack(anchor="w", **pad)

        fila = ttk.Frame(self)
        fila.pack(fill="x", **pad)
        self.url = ttk.Entry(fila, font=("Segoe UI", 11))
        self.url.pack(side="left", fill="x", expand=True, ipady=4)
        self.url.bind("<Return>", lambda e: self.iniciar())
        self.btn_pegar = ttk.Button(fila, text="Pegar", command=self.pegar)
        self.btn_pegar.pack(side="left", padx=(6, 0))
        self.btn_extraer = ttk.Button(fila, text="Extraer", command=self.iniciar)
        self.btn_extraer.pack(side="left", padx=(6, 0))

        self.estado = ttk.Label(self, text=f"Las fotos se guardan en: {carpeta_salida_por_defecto()}",
                                foreground="#555")
        self.estado.pack(anchor="w", padx=12)
        self.progreso = ttk.Progressbar(self, mode="indeterminate")
        self.progreso.pack(fill="x", padx=12, pady=(4, 0))

        ttk.Label(self, text="Texto para publicar:", font=("Segoe UI", 10, "bold")).pack(anchor="w", **pad)
        self.texto = scrolledtext.ScrolledText(self, height=16, font=("Segoe UI Emoji", 11), wrap="word")
        self.texto.pack(fill="both", expand=True, padx=12)

        botones = ttk.Frame(self)
        botones.pack(fill="x", **pad)
        ttk.Button(botones, text="📋 Copiar texto", command=self.copiar).pack(side="left")
        self.btn_carpeta = ttk.Button(botones, text="📁 Abrir carpeta de fotos", command=self.abrir_carpeta,
                                      state="disabled")
        self.btn_carpeta.pack(side="left", padx=6)
        ttk.Button(botones, text="✏️ Editar plantilla", command=self.editar_plantilla).pack(side="right")

        ttk.Label(self, text="Registro:", foreground="#555").pack(anchor="w", padx=12)
        self.registro = scrolledtext.ScrolledText(self, height=6, font=("Consolas", 9), state="disabled")
        self.registro.pack(fill="x", padx=12, pady=(0, 12))

    # ------------------------------------------------------------ acciones
    def pegar(self):
        try:
            self.url.delete(0, "end")
            self.url.insert(0, self.clipboard_get().strip())
        except tk.TclError:
            pass

    def copiar(self):
        self.clipboard_clear()
        self.clipboard_append(self.texto.get("1.0", "end").strip())
        self.estado.config(text="Texto copiado. Ya puedes pegarlo en Facebook / Marketplace.")

    def abrir_carpeta(self):
        if self.carpeta and os.path.isdir(self.carpeta):
            abrir(self.carpeta)

    def editar_plantilla(self):
        ruta = ruta_plantilla()
        if ruta:
            abrir(ruta)
        else:
            messagebox.showinfo(NOMBRE, "No se pudo crear plantilla.txt junto al programa.")

    def iniciar(self):
        url = self.url.get().strip()
        if not url:
            messagebox.showwarning(NOMBRE, "Primero pega el link del auto.")
            return
        self.btn_extraer.config(state="disabled")
        self.btn_carpeta.config(state="disabled")
        self.texto.delete("1.0", "end")
        self._limpiar_registro()
        self.progreso.start(12)
        self.estado.config(text="Trabajando… (se abrirá una ventana del navegador, no la cierres)")
        threading.Thread(target=self._trabajar, args=(url,), daemon=True).start()

    def _trabajar(self, url):
        try:
            res = extraer(url, plantilla=leer_plantilla(), log=lambda m: self.cola.put(("log", m)))
            self.cola.put(("ok", res))
        except Exception as e:
            self.cola.put(("error", str(e)))

    # ------------------------------------------------------------ cola de mensajes
    def _revisar_cola(self):
        try:
            while True:
                tipo, dato = self.cola.get_nowait()
                if tipo == "log":
                    self._log(dato)
                elif tipo == "ok":
                    self._terminar()
                    self.carpeta = dato.carpeta
                    self.texto.insert("1.0", dato.texto)
                    self.btn_carpeta.config(state="normal")
                    self.estado.config(text=f"✅ {len(dato.fotos)} fotos guardadas en: {dato.carpeta}")
                    if not dato.fotos:
                        messagebox.showwarning(NOMBRE, "Se leyó la página pero no se encontraron fotos.")
                elif tipo == "error":
                    self._terminar()
                    self._log(f"❌ {dato}")
                    self.estado.config(text="Ocurrió un error. Revisa el registro.")
                    messagebox.showerror(NOMBRE, dato)
        except queue.Empty:
            pass
        self.after(100, self._revisar_cola)

    def _terminar(self):
        self.progreso.stop()
        self.btn_extraer.config(state="normal")

    def _log(self, msg):
        self.registro.config(state="normal")
        self.registro.insert("end", msg + "\n")
        self.registro.see("end")
        self.registro.config(state="disabled")

    def _limpiar_registro(self):
        self.registro.config(state="normal")
        self.registro.delete("1.0", "end")
        self.registro.config(state="disabled")


if __name__ == "__main__":
    App().mainloop()

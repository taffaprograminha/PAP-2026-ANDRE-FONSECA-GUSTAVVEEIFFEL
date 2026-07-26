#!/usr/bin/env python3
"""
Auto Clicker para macOS — clica num ponto fixo sem prender o rato.

Modo "Segundo plano": o clique é enviado diretamente ao processo da janela
que estava debaixo do ponto capturado (CGEventPostToPid), por isso o cursor
NÃO se move e podes continuar a usar o rato noutro sítio.

Modo "Normal": o clique é injetado no sistema (CGEventPost) e move o cursor
até ao ponto — útil para apps que ignoram eventos enviados por PID (alguns jogos).

Atalhos globais:
  F6 — capturar a posição atual do cursor (e a app por baixo dele)
  F7 — iniciar / parar os cliques
"""

import os
import subprocess
import threading
import time
import tkinter as tk
from tkinter import ttk

import Quartz
from pynput import keyboard

try:
    from ApplicationServices import (AXIsProcessTrusted,
                                     AXIsProcessTrustedWithOptions,
                                     kAXTrustedCheckOptionPrompt)
except ImportError:  # fallback improvável
    AXIsProcessTrusted = lambda: True
    AXIsProcessTrustedWithOptions = None
    kAXTrustedCheckOptionPrompt = None


def request_accessibility():
    """Pede a permissão de Acessibilidade (mostra o diálogo do sistema)."""
    if AXIsProcessTrustedWithOptions is not None:
        AXIsProcessTrustedWithOptions({kAXTrustedCheckOptionPrompt: True})


def open_accessibility_settings():
    subprocess.Popen(["open", "x-apple.systempreferences:"
                      "com.apple.preference.security?Privacy_Accessibility"])


# ---------------------------------------------------------------- Quartz utils

def cursor_position():
    ev = Quartz.CGEventCreate(None)
    p = Quartz.CGEventGetLocation(ev)
    return p.x, p.y


def window_at_point(x, y):
    """Devolve (pid, nome_da_app) da janela mais à frente que contém o ponto."""
    wins = Quartz.CGWindowListCopyWindowInfo(
        Quartz.kCGWindowListOptionOnScreenOnly
        | Quartz.kCGWindowListExcludeDesktopElements,
        Quartz.kCGNullWindowID,
    )
    me = os.getpid()
    for w in wins or []:
        if w.get("kCGWindowOwnerPID") == me:  # ignora as nossas janelas
            continue
        if w.get("kCGWindowLayer", 0) != 0:  # ignora menu bar, dock, overlays
            continue
        b = w.get("kCGWindowBounds", {})
        if (b.get("X", 0) <= x <= b.get("X", 0) + b.get("Width", 0)
                and b.get("Y", 0) <= y <= b.get("Y", 0) + b.get("Height", 0)):
            return w.get("kCGWindowOwnerPID"), w.get("kCGWindowOwnerName", "?")
    return None, None


_BROWSERS = ("chrome", "safari", "firefox", "edge", "arc", "brave",
             "opera", "vivaldi")


def _is_browser(app_name):
    return any(b in (app_name or "").lower() for b in _BROWSERS)


_BUTTONS = {
    "esquerdo": (Quartz.kCGEventLeftMouseDown, Quartz.kCGEventLeftMouseUp,
                 Quartz.kCGMouseButtonLeft),
    "direito": (Quartz.kCGEventRightMouseDown, Quartz.kCGEventRightMouseUp,
                Quartz.kCGMouseButtonRight),
}


def send_click(x, y, pid=None, button="esquerdo", double=False):
    """Um clique em (x, y). Com pid → enviado só a essa app (cursor não mexe)."""
    down_t, up_t, btn = _BUTTONS[button]
    pt = Quartz.CGPointMake(x, y)
    n = 2 if double else 1
    for state in range(1, n + 1):
        for etype in (down_t, up_t):
            ev = Quartz.CGEventCreateMouseEvent(None, etype, pt, btn)
            Quartz.CGEventSetIntegerValueField(
                ev, Quartz.kCGMouseEventClickState, state)
            if pid is not None:
                Quartz.CGEventPostToPid(pid, ev)
            else:
                Quartz.CGEventPost(Quartz.kCGHIDEventTap, ev)
        if double:
            time.sleep(0.04)


def send_real_click(x, y, button="esquerdo", double=False):
    """Clique de sistema verdadeiro: o cursor salta para (x, y), clica e
    volta imediatamente para onde o utilizador o tinha."""
    down_t, up_t, btn = _BUTTONS[button]
    ox, oy = cursor_position()
    pt = Quartz.CGPointMake(x, y)

    # Fonte de eventos que NÃO suprime o rato físico do utilizador
    src = Quartz.CGEventSourceCreate(Quartz.kCGEventSourceStateHIDSystemState)
    Quartz.CGEventSourceSetLocalEventsSuppressionInterval(src, 0.0)

    n = 2 if double else 1
    for state in range(1, n + 1):
        for etype in (down_t, up_t):
            ev = Quartz.CGEventCreateMouseEvent(src, etype, pt, btn)
            Quartz.CGEventSetIntegerValueField(
                ev, Quartz.kCGMouseEventClickState, state)
            Quartz.CGEventPost(Quartz.kCGHIDEventTap, ev)
        if double:
            time.sleep(0.04)

    back = Quartz.CGEventCreateMouseEvent(
        src, Quartz.kCGEventMouseMoved, Quartz.CGPointMake(ox, oy), btn)
    Quartz.CGEventPost(Quartz.kCGHIDEventTap, back)


# ---------------------------------------------------------------------- estado

class State:
    def __init__(self):
        self.lock = threading.Lock()
        self.target = None          # (x, y)
        self.target_pid = None
        self.target_app = None
        self.running = False
        self.clicks_done = 0
        self.ui_mode = "real"       # espelho do modo escolhido na GUI
        self.placing = False        # seta azul a seguir o rato (modo fundo)
        self.message = "Posiciona o cursor no alvo e prime F6."


STATE = State()


# ---------------------------------------------------------------- click worker

FAILSAFE_PX = 10  # canto superior esquerdo: zona de paragem de emergência


def stop_clicking(reason):
    with STATE.lock:
        STATE.running = False
        STATE.message = reason


def click_loop(get_interval, get_mode, get_button, get_double, get_limit):
    while True:
        with STATE.lock:
            run = STATE.running
            target = STATE.target
            pid = STATE.target_pid
            done = STATE.clicks_done
        if not run or target is None:
            time.sleep(0.05)
            continue

        # FAILSAFE 1: cursor no canto superior esquerdo → parar já.
        cx, cy = cursor_position()
        if cx <= FAILSAFE_PX and cy <= FAILSAFE_PX:
            stop_clicking("PARADO (failsafe): cursor no canto superior "
                          "esquerdo do ecrã.")
            continue

        # FAILSAFE 2: limite de cliques atingido.
        limit = get_limit()
        if limit and done >= limit:
            stop_clicking(f"Parado: limite de {limit} cliques atingido.")
            continue

        mode = get_mode()

        # FAILSAFE 3 (modo real): nunca interferir com o botão físico
        # premido — evita "botão preso" e cliques desgarrados em arrastos.
        if mode == "real" and Quartz.CGEventSourceButtonState(
                Quartz.kCGEventSourceStateHIDSystemState, 0):
            time.sleep(0.05)
            continue

        try:
            if mode == "fundo":
                send_click(target[0], target[1], pid=pid,
                           button=get_button(), double=get_double())
            else:
                send_real_click(target[0], target[1],
                                button=get_button(), double=get_double())
            with STATE.lock:
                STATE.clicks_done += 1
        except Exception as e:  # permissão em falta, app fechada, etc.
            stop_clicking(f"Erro ao clicar: {e}")

        interval = get_interval()
        if mode == "real":
            interval = max(0.05, interval)  # o cursor precisa de ir e voltar
        time.sleep(max(0.02, interval))


# --------------------------------------------------------------------- hotkeys

def capture_target():
    x, y = cursor_position()
    pid, app = window_at_point(x, y)
    with STATE.lock:
        STATE.target = (x, y)
        STATE.target_pid = pid
        STATE.target_app = app
        STATE.clicks_done = 0
        STATE.placing = False
        STATE.message = (f"Alvo fixado: ({x:.0f}, {y:.0f}) em “{app}”. "
                         "Prime 1 para mover a seta de novo."
                         if pid else f"Alvo: ({x:.0f}, {y:.0f}) — sem janela "
                                     "(modo fundo indisponível aqui)")
        if pid and STATE.ui_mode == "fundo" and _is_browser(app):
            STATE.message += (f" Atenção: “{app}” ignora cliques em segundo "
                              "plano — usa o modo Real.")


def toggle_running():
    with STATE.lock:
        if STATE.target is None:
            STATE.message = "Captura primeiro um alvo com F6."
            return
        STATE.running = not STATE.running
        if STATE.running:
            STATE.placing = False
            STATE.clicks_done = 0  # o limite conta a partir de cada início
        STATE.message = "A clicar… (F7 para parar)" if STATE.running \
            else "Parado. F7 para retomar."


def hotkey_listener():
    def on_press(key):
        if key == keyboard.Key.esc:
            with STATE.lock:
                running = STATE.running
            if running:
                stop_clicking("Parado com ESC.")
        elif key == keyboard.Key.f6:
            capture_target()
        elif key == keyboard.Key.f7:
            toggle_running()
        elif getattr(key, "char", None) == "1":
            with STATE.lock:
                placing, running = STATE.placing, STATE.running
            if running:
                return
            if placing:
                capture_target()  # fixa a seta azul onde está
            else:
                with STATE.lock:
                    STATE.placing = True
                    STATE.message = ("Seta azul a seguir o rato — prime 1 "
                                     "para a fixar no alvo.")
    try:
        keyboard.Listener(on_press=on_press).start()
    except Exception:
        with STATE.lock:
            STATE.message = ("Atalhos F6/F7 indisponíveis — dá permissão de "
                             "Acessibilidade/Monitorização de Entrada ao "
                             "Terminal em Definições do Sistema.")


# ------------------------------------------------------------------------- GUI

class BlueArrow:
    """Seta azul flutuante: segue o cursor em modo de colocação e fica
    pregada no alvo depois de fixado. Desenhada 3 px ao lado do ponto real
    para nunca tapar o sítio onde o clique aterra."""

    OFFSET = 3

    def __init__(self, root):
        self.root = root
        self.top = tk.Toplevel(root)
        self.top.overrideredirect(True)
        self.top.attributes("-topmost", True)
        try:
            self.top.attributes("-transparent", True)
            bg = "systemTransparent"
        except tk.TclError:
            bg = "#ffffff"
        cv = tk.Canvas(self.top, width=22, height=22, bg=bg,
                       highlightthickness=0)
        cv.pack()
        cv.create_polygon(2, 2, 2, 18, 6, 14, 9, 21, 12, 20, 9, 13, 15, 13,
                          fill="#1f6feb", outline="#0a3d91")
        self.top.withdraw()
        self.visible = False
        self._tick()

    def _tick(self):
        with STATE.lock:
            placing, target = STATE.placing, STATE.target
        if placing:
            self._show(*cursor_position())
        elif target:
            self._show(*target)
        else:
            self._hide()
        self.root.after(30, self._tick)

    def _show(self, x, y):
        self.top.geometry(f"+{int(x) + self.OFFSET}+{int(y) + self.OFFSET}")
        if not self.visible:
            self.top.deiconify()
            self.top.attributes("-topmost", True)
            self.visible = True

    def _hide(self):
        if self.visible:
            self.top.withdraw()
            self.visible = False


def main():
    request_accessibility()

    root = tk.Tk()
    root.title("Auto Clicker")
    root.attributes("-topmost", True)
    root.resizable(False, False)

    # Ícone próprio no Dock (quando corre a partir do bundle .app)
    try:
        icon_png = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                                "AppIcon.png")
        if os.path.exists(icon_png):
            root.iconphoto(True, tk.PhotoImage(file=icon_png))
    except Exception:
        pass

    frm = ttk.Frame(root, padding=14)
    frm.grid()

    status = tk.StringVar()
    target_lbl = tk.StringVar(value="Alvo: — (usa o botão Capturar)")
    count_lbl = tk.StringVar(value="Cliques: 0")

    # Aviso de permissão — sem Acessibilidade os cliques são descartados
    perm_lbl = tk.Label(frm, fg="white", bg="#c62828", wraplength=300,
                        justify="left", padx=6, pady=4)
    perm_btn = ttk.Button(frm, text="Abrir Definições do Sistema",
                          command=open_accessibility_settings)

    def check_permission():
        if AXIsProcessTrusted():
            perm_lbl.grid_forget()
            perm_btn.grid_forget()
        else:
            perm_lbl.config(text="⚠ Sem permissão de Acessibilidade — os "
                                 "cliques NÃO funcionam. A permissão tem de "
                                 "ser dada à app de onde lançaste o script "
                                 "(ex.: Terminal). Depois de a ativar, sai "
                                 "totalmente dessa app (⌘Q) e volta a abrir.")
            perm_lbl.grid(column=0, row=8, columnspan=4, sticky="we",
                          pady=(8, 2))
            perm_btn.grid(column=0, row=9, columnspan=4, sticky="we")
        root.after(2000, check_permission)

    ttk.Label(frm, textvariable=target_lbl).grid(
        column=0, row=0, columnspan=4, sticky="w")
    ttk.Label(frm, textvariable=count_lbl).grid(
        column=0, row=1, columnspan=4, sticky="w", pady=(0, 8))

    ttk.Label(frm, text="Intervalo (ms):").grid(column=0, row=2, sticky="w")
    interval_var = tk.StringVar(value="500")
    ttk.Entry(frm, textvariable=interval_var, width=8).grid(
        column=1, row=2, sticky="w")

    ttk.Label(frm, text="Máx. cliques (0=∞):").grid(
        column=2, row=2, sticky="e", padx=(10, 2))
    limit_var = tk.StringVar(value="0")
    ttk.Entry(frm, textvariable=limit_var, width=6).grid(
        column=3, row=2, sticky="w")

    ttk.Label(frm, text="Botão:").grid(column=0, row=3, sticky="w", pady=(4, 0))
    button_var = tk.StringVar(value="esquerdo")
    ttk.Combobox(frm, textvariable=button_var, width=10, state="readonly",
                 values=("esquerdo", "direito")).grid(
        column=1, row=3, sticky="w", pady=(4, 0))

    double_var = tk.BooleanVar(value=False)
    ttk.Checkbutton(frm, text="Duplo clique", variable=double_var).grid(
        column=2, row=3, sticky="w", padx=(10, 0), pady=(4, 0))

    ttk.Label(frm, text="Modo:").grid(column=0, row=4, sticky="w", pady=(4, 0))
    mode_var = tk.StringVar(value="real")
    modes = ttk.Frame(frm)
    modes.grid(column=1, row=4, columnspan=2, sticky="w", pady=(4, 0))
    ttk.Radiobutton(modes, text="Real (clique normal; o cursor vai e volta)",
                    variable=mode_var, value="real").grid(column=0, row=0,
                                                          sticky="w")
    ttk.Radiobutton(modes, text="Segundo plano (só envia à app alvo)",
                    variable=mode_var, value="fundo").grid(column=0, row=1,
                                                           sticky="w")

    def on_mode_change(*_):
        m = mode_var.get()
        with STATE.lock:
            STATE.ui_mode = m
            if not STATE.running:
                STATE.placing = True
                STATE.message = ("Seta azul a seguir o rato — prime 1 (ou "
                                 "“Capturar”) para a fixar no alvo.")
            if m == "fundo" and STATE.target_app and \
                    _is_browser(STATE.target_app):
                STATE.message = (f"Atenção: “{STATE.target_app}” ignora "
                                 "cliques em segundo plano — usa o modo Real "
                                 "para páginas web.")
    mode_var.trace_add("write", on_mode_change)

    def get_interval():
        try:
            return max(10, int(interval_var.get())) / 1000.0
        except ValueError:
            return 0.5

    def get_limit():
        try:
            return max(0, int(limit_var.get()))
        except ValueError:
            return 0

    def capture_countdown(n=3):
        if n > 0:
            with STATE.lock:
                STATE.message = (f"A capturar em {n}… põe o cursor "
                                 "em cima do alvo!")
            root.after(1000, capture_countdown, n - 1)
        else:
            capture_target()

    btns = ttk.Frame(frm)
    btns.grid(column=0, row=5, columnspan=4, sticky="we", pady=(10, 4))
    btns.columnconfigure((0, 1), weight=1)
    ttk.Button(btns, text="Capturar alvo (3 s)",
               command=capture_countdown).grid(column=0, row=0, sticky="we",
                                               padx=(0, 4))
    start_btn = ttk.Button(btns, text="Iniciar", command=toggle_running)
    start_btn.grid(column=1, row=0, sticky="we")

    ttk.Label(frm, textvariable=status, foreground="gray",
              wraplength=300).grid(column=0, row=6, columnspan=4, sticky="w")

    ttk.Label(frm, text="EMERGÊNCIA: ESC pára · ou atira o cursor para o "
                        "canto superior esquerdo\n"
                        "Atalhos: 1 fixa/solta a seta · Fn+F6 captura · "
                        "Fn+F7 inicia/para",
              foreground="gray", justify="left").grid(
        column=0, row=7, columnspan=4, sticky="w", pady=(6, 0))

    def on_close():
        with STATE.lock:
            STATE.running = False
        root.destroy()
    root.protocol("WM_DELETE_WINDOW", on_close)

    def refresh():
        with STATE.lock:
            status.set(STATE.message)
            count_lbl.set(f"Cliques: {STATE.clicks_done}")
            if STATE.target:
                app = STATE.target_app or "?"
                target_lbl.set(f"Alvo: ({STATE.target[0]:.0f}, "
                               f"{STATE.target[1]:.0f}) — {app}")
            start_btn.config(text="Parar" if STATE.running else "Iniciar")
        root.after(150, refresh)

    threading.Thread(
        target=click_loop,
        args=(get_interval, lambda: STATE.ui_mode,
              lambda: button_var.get(), lambda: double_var.get(), get_limit),
        daemon=True,
    ).start()
    hotkey_listener()
    BlueArrow(root)
    refresh()
    check_permission()
    root.mainloop()


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
# rfid_bridge.py - bridge serial -> HTTP (robusto, junta fragmentos)

import time
import requests
import serial
import sys
import traceback
import os

# CONFIGURAÇÕES
# Podes definir estas variáveis no ambiente em vez de as editar aqui:
#   export RFID_PORT=/dev/cu.usbmodem143101
#   export RFID_API_KEY=a1b2c3...
#   export RFID_PLACA_ID=1

PORT     = os.environ.get("RFID_PORT",     "/dev/cu.usbmodem144301")
BAUD     = int(os.environ.get("RFID_BAUD", "9600"))
API_URL  = os.environ.get("RFID_API_URL",  "http://localhost/demo/api_rfid.php")
API_KEY  = os.environ.get("RFID_API_KEY",  "e39e6c73bc28e70fe95c58ae46d920fc")  # Placa 1
PLACA_ID = int(os.environ.get("RFID_PLACA_ID", "1"))
WRITE_BACK_TO_ARDUINO = True   # se quiseres mandar resposta para a Arduino (ex: "ACESSO_OK")
DEBOUNCE_SECONDS = 1.2        # ignora leituras repetidas no curto prazo

# helpers
def send_post(uid):
    payload = {"cartao_uid": uid, "placa_id": PLACA_ID}
    headers = {}
    if API_KEY:
        headers["X-API-Key"] = API_KEY
    try:
        print(f"-> POST {API_URL}  payload={payload}")
        r = requests.post(API_URL, data=payload, headers=headers, timeout=6)
        print(f"<- HTTP {r.status_code}  body={r.text.strip()}")
        return r.status_code, r.text.strip()
    except Exception as e:
        print("Erro ao enviar POST:", e)
        return None, str(e)

def find_arduino_port():
    """Procura uma porta /dev/cu.usbmodem* (macOS) ou /dev/ttyACM*/ttyUSB* (Linux).
    Devolve o primeiro caminho encontrado ou None."""
    import glob
    for pattern in ("/dev/cu.usbmodem*", "/dev/cu.usbserial*",
                    "/dev/ttyACM*", "/dev/ttyUSB*"):
        ports = sorted(glob.glob(pattern))
        if ports:
            return ports[0]
    return None

def open_serial():
    global PORT
    # Se a porta configurada não existir, tenta auto-detetar
    if not os.path.exists(PORT):
        detected = find_arduino_port()
        if detected:
            print(f"Porta {PORT} não existe. A usar porta detetada: {detected}")
            PORT = detected
        else:
            print(f"Porta {PORT} não existe e nenhum Arduino foi detetado.")
            return None
    try:
        ser = serial.Serial(PORT, BAUD, timeout=0.2)
        # evita reset do Arduino ao abrir a porta
        try:
            ser.dtr = False
        except Exception:
            pass
        # limpa buffer inicial
        try:
            ser.reset_input_buffer()
        except Exception:
            pass
        print("Serial aberta em", PORT)
        return ser
    except Exception as e:
        print("Não foi possível abrir porta serial:", e)
        return None

def normalize_uid(s):
    """Valida e normaliza um UID RFID.
    Remove apenas separadores comuns (:, -, espaço).
    Rejeita qualquer string que contenha letras não-hex (G-Z, I, etc.)
    — isso apanha mensagens de debug como 'RFID READY', 'APROXIME UM CARTÃO', etc.
    """
    # Remove separadores típicos de formatos de UID (AA:BB:CC:DD ou AA-BB-CC-DD)
    cleaned = s.replace(':', '').replace('-', '').replace(' ', '').strip().upper()

    if not cleaned:
        return ''

    # O string COMPLETO tem de ser hexadecimal - se tiver G-Z, I, etc. é mensagem de debug
    HEX = set('0123456789ABCDEF')
    if not all(c in HEX for c in cleaned):
        return ''  # ex: "RFIDREADY" → 'R','I','Y' não são hex → rejeitado

    # UID RFID válido: 4 a 20 chars (2 a 10 bytes)
    if len(cleaned) < 4 or len(cleaned) > 20:
        return ''

    return cleaned

def main():
    last_uid = None
    last_time = 0
    buffer = ""
    ser = None

    print("✅ A escutar RFID... (CTRL+C para sair)")
    while True:
        try:
            if ser is None or not ser.is_open:
                ser = open_serial()
                if ser is None:
                    print("A tentar reconectar à serial em 2s...")
                    time.sleep(2)
                    continue

            data = ser.read(ser.in_waiting or 1)
            if data:
                try:
                    chunk = data.decode('utf-8', errors='replace')
                except Exception:
                    chunk = repr(data)
                buffer += chunk
                # processa todas as linhas completas na buffer
                while '\n' in buffer or '\r' in buffer:
                    # aceita \r\n e \n e \r
                    # normaliza para \n
                    buffer = buffer.replace('\r\n', '\n').replace('\r', '\n')
                    line, buffer = buffer.split('\n', 1)
                    uid_raw = line.strip()
                    if not uid_raw:
                        continue
                    uid = normalize_uid(uid_raw)
                    if not uid:
                        continue
                    now = time.time()
                    # debounce: ignora leituras repetidas muito rápidas
                    if uid == last_uid and (now - last_time) < DEBOUNCE_SECONDS:
                        print(f"(ignorado debounce) UID={uid}")
                        continue
                    last_uid = uid
                    last_time = now
                    print("Lido UID:", uid)
                    status_code, body = send_post(uid)
                    # envia resposta de volta ao Arduino (opcional)
                    if WRITE_BACK_TO_ARDUINO and ser and ser.is_open:
                        resp = (body if body else (str(status_code) if status_code else "ERR"))
                        try:
                            ser.write((resp + "\n").encode('utf-8', errors='replace'))
                        except Exception:
                            pass
            else:
                # sem dados - espera curta
                time.sleep(0.03)

        except KeyboardInterrupt:
            print("Interrompido pelo utilizador.")
            break
        except Exception:
            print("Excepção no loop principal:")
            traceback.print_exc()
            # fecha e força reconexão
            try:
                if ser:
                    ser.close()
            except:
                pass
            ser = None
            time.sleep(1)

    if ser:
        try:
            ser.close()
        except:
            pass

if __name__ == "__main__":
    main()
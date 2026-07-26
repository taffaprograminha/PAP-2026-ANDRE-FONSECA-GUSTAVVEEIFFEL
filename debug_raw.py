# debug_raw.py
import serial, time, sys, traceback

PORT = "/dev/tty.usbmodem144101"
BAUD = 9600
TIMEOUT = 0.5

print("Abrindo porta:", PORT)
try:
    ser = serial.Serial(PORT, BAUD, timeout=TIMEOUT)
    # Evita reset do Arduino: desactiva DTR imediatamente após abrir
    try:
        ser.dtr = False
    except Exception:
        pass
except Exception as e:
    print("Erro ao abrir porta:", e)
    sys.exit(1)

print("Porta aberta. A ler (CTRL+C para sair). Aproxima o cartão agora.")
try:
    while True:
        try:
            data = ser.read(ser.in_waiting or 1)  # lê bytes disponíveis
            if data:
                try:
                    txt = data.decode('utf-8', errors='replace')
                except:
                    txt = repr(data)
                print(f"[{time.strftime('%H:%M:%S')}] RAW_BYTES={data!r}  -> TXT={txt!s}")
            else:
                time.sleep(0.05)
        except KeyboardInterrupt:
            break
        except Exception:
            print("Erro no loop:")
            traceback.print_exc()
            break
finally:
    ser.close()
    print("Fechado.")
import time
import urllib.request
import json
import os
import sys

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass

SCHEDULER_URL = "http://127.0.0.1:8000/api/run_scheduler.php?format=json"

def run_scheduler_tick():
    try:
        req = urllib.request.Request(SCHEDULER_URL, headers={'User-Agent': 'GRI-Scheduler-Daemon/1.0'})
        with urllib.request.urlopen(req, timeout=20) as response:
            if response.status == 200:
                data = json.loads(response.read().decode('utf-8'))
                fired = data.get('fired_count', 0)
                if fired > 0:
                    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] [DISPATCHED] AUTOMATED EMAIL SENT: Fired {fired} job(s) at scheduled time: {data.get('fired_jobs')}", flush=True)
                return True
    except Exception as e:
        # Backend might be busy or restarting
        return False

if __name__ == '__main__':
    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Starting GRI Automated Email Scheduler Daemon...", flush=True)
    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Target: {SCHEDULER_URL}", flush=True)
    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Polling interval: every 20 seconds. Scheduled jobs will fire automatically.", flush=True)
    
    tick = 0
    while True:
        tick += 1
        ok = run_scheduler_tick()
        if tick % 15 == 0:
            status_text = "online" if ok else "waiting for backend"
            print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] [HEARTBEAT] Scheduler ({status_text}) - monitoring scheduled emails...", flush=True)
        time.sleep(20)

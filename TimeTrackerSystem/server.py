import http.server
import json
import os
import shutil
import socketserver
import webbrowser
from datetime import datetime
from threading import Timer

BASE_DIR   = os.path.dirname(__file__)
DATA_FILE  = os.path.join(BASE_DIR, 'data.json')
BACKUP_DIR = os.path.join(BASE_DIR, 'backup')
PORT = 5000

# How many rolling backups to keep
ROLLING_KEEP = 5


def ensure_backup_dir():
    os.makedirs(BACKUP_DIR, exist_ok=True)


def load_data():
    if not os.path.exists(DATA_FILE):
        return []
    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        return json.load(f)


def save_data(entries):
    ensure_backup_dir()

    # ── Rolling backup (last N saves) ──────────────────────────────
    # Rotate existing backups: oldest dropped, .4→.5, .3→.4, ..., .1→.2
    for i in range(ROLLING_KEEP, 0, -1):
        src  = os.path.join(BACKUP_DIR, f'data.backup.{i}.json')
        dest = os.path.join(BACKUP_DIR, f'data.backup.{i + 1}.json')
        if os.path.exists(src):
            if i == ROLLING_KEEP:
                os.remove(src)        # drop the oldest
            else:
                os.rename(src, dest)

    # Save current data.json as .backup.1 before overwriting
    if os.path.exists(DATA_FILE):
        shutil.copy2(DATA_FILE, os.path.join(BACKUP_DIR, 'data.backup.1.json'))

    # ── Daily snapshot ─────────────────────────────────────────────
    # One snapshot per calendar day — only written if it doesn't exist yet
    today = datetime.now().strftime('%Y-%m-%d')
    daily = os.path.join(BACKUP_DIR, f'data.{today}.json')
    if not os.path.exists(daily) and os.path.exists(DATA_FILE):
        shutil.copy2(DATA_FILE, daily)

    # ── Write new data ─────────────────────────────────────────────
    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(entries, f, indent=2, ensure_ascii=False)


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=os.path.dirname(__file__), **kwargs)

    def do_GET(self):
        if self.path == '/api/entries':
            self.send_json(load_data())
        else:
            super().do_GET()

    def do_POST(self):
        if self.path == '/api/entries':
            length = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(length)
            entries = json.loads(body)
            save_data(entries)
            self.send_json({'ok': True})
        else:
            self.send_error(404)

    def send_json(self, data):
        body = json.dumps(data).encode('utf-8')
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

    def log_message(self, format, *args):
        # Suppress request logs for cleaner output
        pass


def is_wsl():
    try:
        return 'microsoft' in open('/proc/version').read().lower()
    except OSError:
        return False

def open_browser():
    if not is_wsl():
        webbrowser.open(f'http://localhost:{PORT}/index.html')


if __name__ == '__main__':
    ensure_backup_dir()
    with socketserver.TCPServer(('', PORT), Handler) as httpd:
        print(f'  Time Tracker running at http://localhost:{PORT}')
        print(f'  Data file:   {DATA_FILE}')
        print(f'  Backup dir:  {BACKUP_DIR}')
        print()
        print(f'  Developed by Luis Felipe Castro with the assistance of Claude (Anthropic).')
        print()
        print(f'  ⚠  DO NOT close this window until you are done using Time Tracker.')
        print(f'  Close this window to stop the server.')
        print()
        Timer(1.0, open_browser).start()
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print('\n  Server stopped.')

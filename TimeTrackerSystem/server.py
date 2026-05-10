import http.server
import json
import os
import shutil
import socketserver
import webbrowser
from datetime import datetime
from threading import Timer

BASE_DIR   = os.path.dirname(__file__)
_data_dir  = os.environ.get('DATA_DIR', BASE_DIR)
BACKUP_DIR = os.path.join(_data_dir, 'backup')
PORT = 5000

# How many rolling backups to keep
ROLLING_KEEP = 5


def ensure_backup_dir():
    os.makedirs(BACKUP_DIR, exist_ok=True)


def load_data(filename='data.json', default=None):
    path = os.path.join(_data_dir, filename)
    if not os.path.exists(path):
        return [] if default is None else default
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)


def save_data(entries, filename='data.json'):
    path = os.path.join(_data_dir, filename)
    stem = os.path.splitext(filename)[0]
    ensure_backup_dir()

    # ── Rolling backup (last N saves) ──────────────────────────────
    for i in range(ROLLING_KEEP, 0, -1):
        src  = os.path.join(BACKUP_DIR, f'{stem}.backup.{i}.json')
        dest = os.path.join(BACKUP_DIR, f'{stem}.backup.{i + 1}.json')
        if os.path.exists(src):
            if i == ROLLING_KEEP:
                os.remove(src)        # drop the oldest
            else:
                os.rename(src, dest)

    # Save current data file as .backup.1 before overwriting
    if os.path.exists(path):
        shutil.copy2(path, os.path.join(BACKUP_DIR, f'{stem}.backup.1.json'))

    # ── Daily snapshot ─────────────────────────────────────────────
    today = datetime.now().strftime('%Y-%m-%d')
    daily = os.path.join(BACKUP_DIR, f'{stem}.{today}.json')
    if not os.path.exists(daily) and os.path.exists(path):
        shutil.copy2(path, daily)

    # ── Write new data ─────────────────────────────────────────────
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(entries, f, indent=2, ensure_ascii=False)


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=os.path.dirname(__file__), **kwargs)

    def do_GET(self):
        if self.path == '/api/entries':
            self.send_json(load_data('data.json'))
        elif self.path == '/api/replicon/entries':
            self.send_json(load_data('data-replicon.json'))
        elif self.path == '/api/contractor/entries':
            self.send_json(load_data('data-contractor.json'))
        elif self.path == '/api/contractor/invoices':
            self.send_json(load_data('data-contractor-invoices.json'))
        elif self.path == '/api/contractor/clients':
            self.send_json(load_data('data-contractor-clients.json', default={}))
        else:
            super().do_GET()

    def do_POST(self):
        if self.path == '/api/entries':
            self._handle_post('data.json')
        elif self.path == '/api/replicon/entries':
            self._handle_post('data-replicon.json')
        elif self.path == '/api/contractor/entries':
            self._handle_post('data-contractor.json')
        elif self.path == '/api/contractor/invoices':
            self._handle_post('data-contractor-invoices.json')
        elif self.path == '/api/contractor/clients':
            self._handle_post('data-contractor-clients.json')
        else:
            self.send_error(404)

    def _handle_post(self, filename):
        length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(length)
        entries = json.loads(body)
        save_data(entries, filename)
        self.send_json({'ok': True})

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
        print(f'  Data dir:    {_data_dir}')
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

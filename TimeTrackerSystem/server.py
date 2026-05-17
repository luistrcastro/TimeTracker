import http.server
import json
import os
import re
import shutil
import socketserver
import urllib.error
import urllib.request
import webbrowser
from datetime import datetime
from threading import Timer

BASE_DIR   = os.path.dirname(__file__)
_data_dir  = os.environ.get('DATA_DIR', BASE_DIR)
BACKUP_DIR = os.path.join(_data_dir, 'backup')
PORT = 5000

# Credentials expire after this many seconds — session tokens change frequently
CREDS_TTL = 15 * 60  # 15 minutes
_creds_expiry_timer = None
_last_replicon_debug = {}  # stores last submit request + raw Replicon response

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


def load_config(filename):
    path = os.path.join(_data_dir, filename)
    if not os.path.exists(path):
        return None
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)


def save_config(data, filename):
    path = os.path.join(_data_dir, filename)
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)


def queue_requests(base_url, creds, requests):
    """Call the Replicon QueueRequests endpoint with a list of method calls."""
    body = {
        'serverViewStateId': creds['server_view_state_id'],
        'sessionId':         creds['session_id'],
        'requests':          requests,
    }
    hdrs = {
        'Cookie':           creds['cookie_header'],
        'Origin':           base_url,
        'Referer':          f'{base_url}/a/TimeSheetModule/TimeSheet.aspx',
        'X-Requested-With': 'XMLHttpRequest',
    }
    return replicon_call(
        base_url,
        '/a/TimesheetService/Interaction.asmx/QueueRequests',
        hdrs,
        body=body,
    )


def extract_tasks_from_root(root_task, _ancestors=None):
    """Recursively collect enabled leaf tasks with their full ancestor path.

    Category/folder nodes may be disabled but still have enabled children —
    always recurse regardless of Enabled; only gate the append on Enabled+Value.
    """
    tasks = []
    ancestors = _ancestors or []
    for t in (root_task or {}).get('ChildTasks', []):
        name = t.get('Text', '')
        path = ancestors + [name]
        children = t.get('ChildTasks')
        if children:
            tasks.extend(extract_tasks_from_root(t, path))
        elif t.get('Value'):
            tasks.append({'id': t['Value'], 'name': name, 'path': path})
    tasks.sort(key=lambda x: x['path'])
    return tasks


def get_return_object(resp, request_index):
    """Extract ReturnObject for a given requestIndex from a QueueRequests response.

    Response shape:
      d.data[N] where data[N].RequestIndex == request_index
        → CommitRequests[0].ReturnObject
    """
    try:
        for item in resp['d']['data']:
            if item.get('RequestIndex') == request_index:
                return item['CommitRequests'][0]['ReturnObject']
    except (KeyError, IndexError, TypeError):
        pass
    return None


def extract_redirected_session(resp):
    """If Replicon returned Action 11 (session redirect), return the new sessionId; else None.

    Action 11 means the server rotated the session. Content looks like:
      {sessionId:'0f21d16e-0d91-4687-b50c-116d6c9775f4'}
    """
    try:
        for item in resp.get('d', {}).get('data', []):
            for action in item.get('Actions', []):
                if action.get('Action') == 11:
                    m = re.search(r"sessionId:'([^']+)'", action.get('Content', ''))
                    if m:
                        return m.group(1)
    except Exception:
        pass
    return None


def replicon_call(base_url, path, hdrs, body=None):
    url = f'{base_url}{path}'
    method = 'POST' if body is not None else 'GET'
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header('Content-Type', 'application/json')
    req.add_header('Accept', 'application/json')
    for k, v in hdrs.items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        msg = e.read().decode('utf-8', errors='replace')[:300]
        raise RuntimeError(f'Replicon API {e.code}: {msg}')


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


def _expire_replicon_credentials():
    """Clear session credentials, keeping only base_url (called by timer after CREDS_TTL)."""
    creds = load_config('replicon-credentials.json') or {}
    save_config({'base_url': creds.get('base_url', '')}, 'replicon-credentials.json')


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
        elif self.path == '/api/replicon/credentials':
            creds = load_config('replicon-credentials.json')
            if creds:
                self.send_json({
                    'configured':           True,
                    'base_url':             creds.get('base_url', ''),
                    'server_view_state_id': creds.get('server_view_state_id', ''),
                    'session_id':           creds.get('session_id', ''),
                    'cookie_set':           bool(creds.get('cookie_header', '')),
                    'last_request_index':   int(creds.get('last_request_index', 0)),
                })
            else:
                self.send_json({'configured': False, 'cookie_set': False})
        elif self.path == '/api/replicon/cache':
            cache = load_config('replicon-projects-cache.json')
            self.send_json(cache or {'projects': []})
        elif self.path == '/api/replicon/row-map':
            self.send_json(load_config('replicon-row-map.json') or {})
        elif self.path == '/api/replicon/debug-sync':
            self._handle_replicon_debug_sync()
        elif self.path == '/api/replicon/debug':
            self.send_json(_last_replicon_debug)
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
        elif self.path == '/api/replicon/credentials':
            self._handle_replicon_credentials()
        elif self.path == '/api/replicon/sync':
            self._handle_replicon_sync()
        elif self.path == '/api/replicon/row-map':
            self._handle_replicon_row_map()
        elif self.path == '/api/replicon/submit':
            self._handle_replicon_submit()
        else:
            self.send_error(404)

    def _handle_post(self, filename):
        length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(length)
        entries = json.loads(body)
        save_data(entries, filename)
        self.send_json({'ok': True})

    def _handle_replicon_debug_sync(self):
        creds = load_config('replicon-credentials.json')
        if not creds:
            self.send_json({'error': 'not configured'}); return
        cache = load_config('replicon-projects-cache.json')
        try:
            # Use first cached project ID, or fall back to known value
            first_pid = '0'
            if cache and cache.get('projects'):
                first_pid = cache['projects'][0]['id']
            resp = queue_requests(creds['base_url'], creds, [
                {'requestIndex': 1, 'methodName': 'RequestProjects', 'instanceId': 'timesheet', 'paramList': ['0']},
                {'requestIndex': 2, 'methodName': 'RequestTasks',    'instanceId': 'timesheet', 'paramList': [str(first_pid)]},
            ])
            self.send_json({'raw': resp, 'first_project_id_used': first_pid})
        except Exception as ex:
            self.send_json({'error': str(ex)})

    def _handle_replicon_credentials(self):
        global _creds_expiry_timer
        length = int(self.headers.get('Content-Length', 0))
        body = json.loads(self.rfile.read(length))
        save_config({
            'base_url':             body.get('base_url', '').rstrip('/'),
            'cookie_header':        body.get('cookie_header', ''),
            'server_view_state_id': body.get('server_view_state_id', ''),
            'session_id':           body.get('session_id', ''),
            'last_request_index':   int(body.get('last_request_index', 0)),
        }, 'replicon-credentials.json')

        # Cancel any existing expiry timer and start a fresh one
        if _creds_expiry_timer is not None:
            _creds_expiry_timer.cancel()
        _creds_expiry_timer = Timer(CREDS_TTL, _expire_replicon_credentials)
        _creds_expiry_timer.daemon = True
        _creds_expiry_timer.start()

        self.send_json({'ok': True, 'expires_in': CREDS_TTL})

    def _handle_replicon_sync(self):
        creds = load_config('replicon-credentials.json')
        required = ('base_url', 'cookie_header', 'server_view_state_id', 'session_id')
        if not creds or not all(creds.get(k) for k in required):
            self.send_json({'ok': False, 'error': 'All four credential fields must be filled in Settings'})
            return
        try:
            base_url = creds['base_url']

            # ── Fetch all projects (paged) ───────────────────────────
            # Response: d.data[N].CommitRequests[0].ReturnObject
            #   → { Projects: [{Value: "9424", Text: "2259 - Name - FY26 - ..."},...], TotalOptions: N }
            projects_raw = []
            page = 0
            try:
                while True:
                    resp = queue_requests(base_url, creds, [{
                        'requestIndex': 1,
                        'methodName':   'RequestProjects',
                        'instanceId':   'timesheet',
                        'paramList':    [str(page)],
                    }])
                    ret = get_return_object(resp, 1)
                    if not ret:
                        break
                    batch = ret.get('Projects', [])
                    projects_raw.extend(batch)
                    total = ret.get('TotalOptions', len(projects_raw))
                    if len(projects_raw) >= total or not batch:
                        break
                    page += 1
            except Exception as ex:
                self.send_json({'ok': False, 'error': f'RequestProjects failed: {ex}'}); return

            # ── Fetch tasks for all projects in one batched call ─────
            # paramList: [projectValue] e.g. ["9424"]
            # Response shape assumed similar: ReturnObject.Tasks (or Activities)
            task_results = {}
            task_error = None
            if projects_raw:
                try:
                    task_reqs = [{
                        'requestIndex': i + 1,
                        'methodName':   'RequestTasks',
                        'instanceId':   'timesheet',
                        'paramList':    [p['Value']],
                    } for i, p in enumerate(projects_raw)]
                    task_resp = queue_requests(base_url, creds, task_reqs)
                    for i in range(len(projects_raw)):
                        ret = get_return_object(task_resp, i + 1)
                        if ret:
                            task_results[i] = extract_tasks_from_root(ret.get('RootTask', {}))
                except Exception as task_ex:
                    task_error = str(task_ex)  # surfaced in sync response

            # ── Build cache entries ──────────────────────────────────
            # Text format: "CODE - Name - FY26 - CODE.version"
            # We extract the leading code (everything before the first " - ")
            projects = []
            for i, p in enumerate(projects_raw):
                text = p.get('Text', '')
                code = text.split(' - ')[0].strip() if ' - ' in text else text
                tasks = task_results.get(i, [])
                projects.append({
                    'id':   p.get('Value', ''),
                    'code': code,
                    'name': text,
                    'tasks': tasks,
                })

            cache = {
                'synced_at': datetime.now().isoformat(),
                'base_url':  base_url,
                'projects':  projects,
            }
            save_config(cache, 'replicon-projects-cache.json')
            result = {'ok': True, 'count': len(projects), 'synced_at': cache['synced_at']}
            if task_error:
                result['task_warning'] = task_error
            self.send_json(result)
        except Exception as ex:
            self.send_json({'ok': False, 'error': str(ex)})

    def _handle_replicon_row_map(self):
        length = int(self.headers.get('Content-Length', 0))
        body = json.loads(self.rfile.read(length))
        incoming = body.get('rows', [])

        # ── Update row map ─────────────────────────────────────────
        row_map = load_config('replicon-row-map.json') or {}
        for r in incoming:
            key = f"{r['projectId']}:{r['taskId']}"
            row_map[key] = int(r['rowId'])
        save_config(row_map, 'replicon-row-map.json')

        # ── Merge into projects cache if DOM names were provided ───
        projects_updated = 0
        if incoming and 'projectName' in incoming[0]:
            cache = load_config('replicon-projects-cache.json') or {'projects': []}
            proj_index = {p['id']: p for p in cache.get('projects', [])}

            for r in incoming:
                pid   = r['projectId']
                tid   = r['taskId']
                pname = r.get('projectName', '').strip()
                tname = r.get('taskName', '').strip()
                if not pname or not tname:
                    continue
                code = pname.split(' - ')[0].strip() if ' - ' in pname else pname
                if pid not in proj_index:
                    proj_index[pid] = {'id': pid, 'code': code, 'name': pname, 'tasks': []}
                    projects_updated += 1
                proj = proj_index[pid]
                # update code/name in case it changed
                proj['code'] = code
                proj['name'] = pname
                # add task if not already present
                if not any(t['id'] == tid for t in proj.get('tasks', [])):
                    proj.setdefault('tasks', []).append({'id': tid, 'name': tname, 'path': [tname]})

            cache['projects'] = sorted(proj_index.values(), key=lambda p: p['code'])
            cache.setdefault('synced_at', datetime.now().isoformat())
            save_config(cache, 'replicon-projects-cache.json')
            projects_updated = len(cache['projects'])

        self.send_json({'ok': True, 'count': len(incoming),
                        'total': len(row_map), 'projects': projects_updated})

    def _handle_replicon_submit(self):
        length = int(self.headers.get('Content-Length', 0))
        body     = json.loads(self.rfile.read(length))
        date_str = body['date']
        rows     = body['rows']

        creds   = load_config('replicon-credentials.json')
        cache   = load_config('replicon-projects-cache.json')
        row_map = load_config('replicon-row-map.json') or {}

        if not creds or not creds.get('cookie_header'):
            self.send_json({'ok': False, 'error': 'Credentials not configured'})
            return
        if not cache or not cache.get('projects'):
            self.send_json({'ok': False, 'error': 'Projects not synced — run Sync first'})
            return

        # Build (project_id, task_id) lookup keyed by (code/id, task_name) and (code/id, task_id)
        id_lookup = {}
        for p in cache.get('projects', []):
            for t in p.get('tasks', []):
                id_lookup[(p['code'], t['name'])] = (p['id'], t['id'])
                id_lookup[(p['id'],   t['name'])] = (p['id'], t['id'])
                id_lookup[(p['code'], t['id'])]   = (p['id'], t['id'])
                id_lookup[(p['id'],   t['id'])]   = (p['id'], t['id'])

        # Column: days since Saturday (Sat=0, Sun=1, Mon=2, … Fri=6)
        date      = datetime.strptime(date_str, '%Y-%m-%d')
        col       = (date.weekday() + 2) % 7

        results     = []
        requests    = []
        row_indices = []  # (dur_idx, cmt_idx) for each result entry with ok=True
        idx         = int(creds.get('last_request_index', 0)) + 1

        for row in rows:
            project    = row['project']
            subproject = row['subProject']
            hours      = row['hours']
            comments   = row['comments']

            ids = id_lookup.get((project, subproject))
            if not ids:
                results.append({'project': project, 'subProject': subproject,
                                'ok': False, 'error': 'Not in project cache'})
                row_indices.append(None)
                continue

            proj_id, task_id = ids
            row_id = row_map.get(f'{proj_id}:{task_id}')
            if row_id is None:
                results.append({'project': project, 'subProject': subproject,
                                'ok': False, 'error': 'Row not mapped — click the bookmarklet on your Replicon timesheet'})
                row_indices.append(None)
                continue

            requests += [
                {'requestIndex': idx,     'methodName': 'SetDuration', 'instanceId': 'timesheet',
                 'paramList': ['time', str(row_id), str(col), str(hours)]},
                {'requestIndex': idx + 1, 'methodName': 'SetComment',  'instanceId': 'timesheet',
                 'paramList': ['time', str(row_id), col, comments]},
            ]
            row_indices.append((idx, idx + 1))
            idx += 2
            results.append({'project': project, 'subProject': subproject, 'ok': True})

        if not requests:
            self.send_json({'ok': False, 'results': results,
                            'error': 'No rows could be mapped — run Sync and the bookmarklet first'})
            return

        save_idx = idx
        requests.append({'requestIndex': save_idx, 'methodName': 'Save',
                         'instanceId': 'timesheet', 'paramList': []})
        cookie_str = creds.get('cookie_header', '')
        cookie_names = [c.split('=')[0].strip() for c in cookie_str.split(';') if '=' in c]
        sent_body = {
            'serverViewStateId': creds.get('server_view_state_id'),
            'sessionId':         creds.get('session_id'),
            'requests':          requests,
            'cookie_names_sent': cookie_names,
        }
        try:
            rpl_resp = queue_requests(creds['base_url'], creds, requests)
            new_session = extract_redirected_session(rpl_resp)
            if new_session:
                creds['session_id'] = new_session
                sent_body['sessionId_retry'] = new_session
                rpl_resp = queue_requests(creds['base_url'], creds, requests)

            # Build commit map: requestIndex → bool (True if CommitRequests non-empty)
            commit_map = {}
            for item in rpl_resp.get('d', {}).get('data', []):
                ri = item.get('RequestIndex')
                commit_map[ri] = bool(item.get('CommitRequests'))

            # Update per-row results based on actual Replicon commit status
            for i, indices in enumerate(row_indices):
                if indices is None:
                    continue  # already marked as mapping failure
                dur_idx, cmt_idx = indices
                dur_ok = commit_map.get(dur_idx, False)
                cmt_ok = commit_map.get(cmt_idx, False)
                if not dur_ok and not cmt_ok:
                    results[i]['ok'] = False
                    results[i]['error'] = 'Not committed by Replicon — session may have expired'
                elif not dur_ok:
                    results[i]['ok'] = False
                    results[i]['error'] = 'Duration not committed'
                elif not cmt_ok:
                    results[i]['ok'] = False
                    results[i]['error'] = 'Comment not committed'

            # Persist the last used requestIndex so next submit continues the counter
            creds['last_request_index'] = save_idx
            save_config(creds, 'replicon-credentials.json')
            _last_replicon_debug.update({
                'sent_body': sent_body,
                'response':  rpl_resp,
                'commit_map': commit_map,
                'session_redirected': bool(new_session),
                'error': None,
            })
            self.send_json({'ok': True, 'results': results})
        except Exception as ex:
            _last_replicon_debug.update({
                'sent_body': sent_body,
                'response':  None,
                'error':     str(ex),
            })
            self.send_json({'ok': False, 'error': str(ex), 'results': results})

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

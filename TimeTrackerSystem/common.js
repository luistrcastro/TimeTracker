// ══════════════════════════════════════════════
//  common.js — shared code for all TT variants
//  Requires window.TT_CONFIG = { api, storageKey }
//  to be defined before this script loads.
// ══════════════════════════════════════════════

// ── State variables ──────────────────────────
let currentDate = todayStr();
let _entries = [];
let _usingServer = false;
let _sortCol = null;
let _sortDir = null;
let _darkMode = false;
let _use12h = localStorage.getItem('timetracker_12h') === 'true';
let _deletedEntry = null;
let _undoTimeout = null;
// Hook: set by contractor variant to run post-recovery logic after heartbeat reconnects
let _onServerRecovery = null;

// ══════════════════════════════════════════════
//  Data layer — server API with localStorage fallback
// ══════════════════════════════════════════════
const HEARTBEAT_INTERVAL = 3 * 60 * 1000; // 3 minutes

async function initData() {
	const API = TT_CONFIG.api;
	const STORAGE_KEY = TT_CONFIG.storageKey;
	try {
		const res = await fetch(API, { signal: AbortSignal.timeout(1500) });
		if (!res.ok) throw new Error('bad response');
		_entries = await res.json();
		_usingServer = true;
		setServerStatus(true);
		// Migrate any localStorage data on first server connection
		const local = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
		if (local.length > 0) {
			const ids = new Set(_entries.map((e) => e.id));
			const newOnes = local.filter((e) => !ids.has(e.id));
			if (newOnes.length > 0) {
				_entries = [..._entries, ...newOnes];
				await persistEntries();
				localStorage.removeItem(STORAGE_KEY);
				console.log(
					`Migrated ${newOnes.length} entries from localStorage to file.`,
				);
			}
		}
	} catch {
		_usingServer = false;
		setServerStatus(false);
		_entries = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
	}
}

async function heartbeat() {
	const API = TT_CONFIG.api;
	const STORAGE_KEY = TT_CONFIG.storageKey;
	try {
		const res = await fetch(API, { signal: AbortSignal.timeout(1500) });
		if (!res.ok) throw new Error();

		if (!_usingServer) {
			// Server just came back — migrate localStorage entries into data file
			const serverEntries = await res.json();
			const local = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
			const ids = new Set(serverEntries.map((e) => e.id));
			const newOnes = local.filter((e) => !ids.has(e.id));
			_entries = [...serverEntries, ...newOnes];
			_usingServer = true;
			if (newOnes.length > 0) {
				await persistEntries();
				localStorage.removeItem(STORAGE_KEY);
				console.log(
					`Heartbeat: recovered ${newOnes.length} entries from localStorage.`,
				);
			}
			setServerStatus(true);
			if (_onServerRecovery) _onServerRecovery();
			renderAll();
		}
	} catch {
		if (_usingServer) {
			// Server just went down — switch to localStorage
			_usingServer = false;
			localStorage.setItem(STORAGE_KEY, JSON.stringify(_entries));
			setServerStatus(false);
		}
	}
}

function startHeartbeat() {
	setInterval(heartbeat, HEARTBEAT_INTERVAL);
}

async function persistEntries() {
	const API = TT_CONFIG.api;
	const STORAGE_KEY = TT_CONFIG.storageKey;
	if (_usingServer) {
		try {
			await fetch(API, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(_entries),
			});
		} catch {
			// Server went away — fall back to localStorage silently
			localStorage.setItem(STORAGE_KEY, JSON.stringify(_entries));
		}
	} else {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(_entries));
	}
}

function loadEntries() {
	return _entries;
}

async function saveEntries(entries) {
	_entries = entries;
	await persistEntries();
}

function setServerStatus(online) {
	let dot = document.getElementById('serverDot');
	if (!dot) return;
	dot.title = online
		? 'Saving to file (server running)'
		: 'Saving to browser (server not found)';
	dot.style.background = online ? '#52c97e' : '#f0b429';
}

// ══════════════════════════════════════════════
//  Date helpers
// ══════════════════════════════════════════════
function todayStr() {
	const d = new Date();
	return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function changeDate(delta) {
	const d = new Date(currentDate + 'T00:00:00');
	d.setDate(d.getDate() + delta);
	currentDate = d.toISOString().slice(0, 10);
	syncDatePickers();
	renderAll();
}

function goToday() {
	currentDate = todayStr();
	syncDatePickers();
	renderAll();
}

function syncDatePickers() {
	document.getElementById('datePicker').value = currentDate;
	document.getElementById('datePickerR').value = currentDate;
}

function formatDateHeader(dateStr) {
	const d = new Date(dateStr + 'T00:00:00');
	return d.toLocaleDateString('en-CA', {
		weekday: 'long',
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	});
}

// ══════════════════════════════════════════════
//  Time helpers
// ══════════════════════════════════════════════
function timeToMinutes(t) {
	if (!t) return 0;
	const [h, m] = t.split(':').map(Number);
	return h * 60 + (m || 0);
}

function minutesToHHMM(mins) {
	if (mins < 0) mins = 0;
	const h = Math.floor(mins / 60);
	const m = mins % 60;
	return `${h}:${String(m).padStart(2, '0')}`;
}

function minutesToDecimal(mins) {
	return Math.round((mins / 60) * 4) / 4;
}

// ══════════════════════════════════════════════
//  Theme (dark / light)
// ══════════════════════════════════════════════
function initTheme() {
	const saved = localStorage.getItem('timetracker_theme');
	if (saved) {
		_darkMode = saved === 'dark';
	} else {
		_darkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
	}
	applyTheme();

	// Follow OS changes if no manual preference saved
	window
		.matchMedia('(prefers-color-scheme: dark)')
		.addEventListener('change', (e) => {
			if (!localStorage.getItem('timetracker_theme')) {
				_darkMode = e.matches;
				applyTheme();
			}
		});
}

function toggleTheme() {
	_darkMode = !_darkMode;
	localStorage.setItem('timetracker_theme', _darkMode ? 'dark' : 'light');
	applyTheme();
}

function applyTheme() {
	document.documentElement.setAttribute(
		'data-theme',
		_darkMode ? 'dark' : 'light',
	);
	const thumb = document.getElementById('themeToggleThumb');
	const track = document.getElementById('themeToggle');
	if (!thumb || !track) return;
	thumb.style.left = _darkMode ? '16px' : '2px';
	track.style.background = _darkMode
		? 'rgba(255,255,255,0.5)'
		: 'rgba(255,255,255,0.2)';
}

// ══════════════════════════════════════════════
//  Gap / Overlap detection
// ══════════════════════════════════════════════
function detectGapsAndOverlaps(entries) {
	// entries must be sorted by start time
	const result = new Map(); // id → { gap: mins, overlap: bool }
	for (let i = 0; i < entries.length - 1; i++) {
		const a = entries[i];
		const b = entries[i + 1];
		if (!a.finish || !b.start) continue;
		const aFinish = timeToMinutes(a.finish);
		const bStart = timeToMinutes(b.start);
		const diff = bStart - aFinish;
		if (diff > 0) {
			result.set(a.id, { ...(result.get(a.id) || {}), gapAfter: diff });
		} else if (diff < 0) {
			result.set(a.id, { ...(result.get(a.id) || {}), overlap: true });
			result.set(b.id, { ...(result.get(b.id) || {}), overlap: true });
		}
	}
	return result;
}

function formatGap(mins) {
	const h = Math.floor(mins / 60);
	const m = mins % 60;
	return h > 0 ? `${h}h ${m}m gap` : `${m}m gap`;
}

// ══════════════════════════════════════════════
//  Clock format (12/24h)
// ══════════════════════════════════════════════
function toggleClockFormat() {
	_use12h = !_use12h;
	localStorage.setItem('timetracker_12h', _use12h);
	updateToggleUI();
	renderAll();
}

function updateToggleUI() {
	const thumb = document.getElementById('clockToggleThumb');
	const track = document.getElementById('clockToggle');
	if (!thumb || !track) return;
	if (_use12h) {
		thumb.style.left = '16px';
		track.style.background = 'rgba(255,255,255,0.5)';
	} else {
		thumb.style.left = '2px';
		track.style.background = 'rgba(255,255,255,0.2)';
	}
}

function formatTime(hhmm) {
	// hhmm is always "HH:MM" (24h internally)
	if (!hhmm) return '';
	if (!_use12h) return hhmm;
	const [h, m] = hhmm.split(':').map(Number);
	const period = h >= 12 ? 'PM' : 'AM';
	const h12 = h % 12 || 12;
	return `${h12}:${String(m).padStart(2, '0')} ${period}`;
}

function startClock() {
	function tick() {
		const now = new Date();
		const h = String(now.getHours()).padStart(2, '0');
		const m = String(now.getMinutes()).padStart(2, '0');
		const s = String(now.getSeconds()).padStart(2, '0');
		const el = document.getElementById('liveClock');
		if (!el) return;
		if (_use12h) {
			const period = now.getHours() >= 12 ? 'PM' : 'AM';
			const h12 = now.getHours() % 12 || 12;
			el.textContent = `${h12}:${m}:${s} ${period}`;
		} else {
			el.textContent = `${h}:${m}:${s}`;
		}
	}
	tick();
	setInterval(tick, 1000);
}

// ══════════════════════════════════════════════
//  Utilities
// ══════════════════════════════════════════════
function esc(str) {
	return String(str || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
}

function showToast(msg) {
	if (_deletedEntry) return;
	const t = document.getElementById('copyToast');
	t.textContent = msg;
	t.classList.add('show');
	setTimeout(() => t.classList.remove('show'), 2000);
}

function showUndoToast() {
	clearTimeout(_undoTimeout);
	const t = document.getElementById('copyToast');
	t.innerHTML =
		'Entry deleted &nbsp;<button onclick="undoDelete()" style="background:#fff;color:#1a1917;border:none;border-radius:4px;padding:2px 8px;font-family:var(--mono);font-size:0.75rem;cursor:pointer;font-weight:600;">Undo</button>';
	t.classList.add('show');
	_undoTimeout = setTimeout(() => {
		t.classList.remove('show');
		_deletedEntry = null;
	}, 5000);
}

// ══════════════════════════════════════════════
//  CRUD — Delete with undo
// ══════════════════════════════════════════════
async function deleteEntry(id) {
	const entries = loadEntries();
	_deletedEntry = entries.find((e) => e.id === id) || null;
	await saveEntries(entries.filter((e) => e.id !== id));
	renderAll();
	if (_deletedEntry) showUndoToast();
}

async function undoDelete() {
	if (!_deletedEntry) return;
	clearTimeout(_undoTimeout);
	const entries = loadEntries();
	entries.push(_deletedEntry);
	_deletedEntry = null;
	await saveEntries(entries);
	document.getElementById('copyToast').classList.remove('show');
	renderAll();
}

// ══════════════════════════════════════════════
//  Sort
// ══════════════════════════════════════════════
function applySortToEntries(entries) {
	if (!_sortCol || !_sortDir) {
		// Default: chronological by start
		return [...entries].sort((a, b) => a.start.localeCompare(b.start));
	}
	return [...entries].sort((a, b) => {
		const av = (a[_sortCol] || '').toLowerCase();
		const bv = (b[_sortCol] || '').toLowerCase();
		const cmp = av.localeCompare(bv);
		return _sortDir === 'asc' ? cmp : -cmp;
	});
}

// ══════════════════════════════════════════════
//  Export
// ══════════════════════════════════════════════
function exportData() {
	const blob = new Blob([JSON.stringify(loadEntries(), null, 2)], {
		type: 'application/json',
	});
	const a = document.createElement('a');
	a.href = URL.createObjectURL(blob);
	a.download = `timetracker-${currentDate}.json`;
	a.click();
}

// ══════════════════════════════════════════════
//  Week helpers (Sat–Fri)
// ══════════════════════════════════════════════
function getWeekStart(dateStr) {
	// Returns the Saturday on or before dateStr
	const d = new Date(dateStr + 'T00:00:00');
	const day = d.getDay(); // 0=Sun,1=Mon,...,6=Sat
	const diff = day >= 6 ? 0 : day + 1; // days back to reach Saturday
	d.setDate(d.getDate() - diff);
	return d.toISOString().slice(0, 10);
}

function addDays(dateStr, n) {
	const d = new Date(dateStr + 'T00:00:00');
	d.setDate(d.getDate() + n);
	return d.toISOString().slice(0, 10);
}

function changeWeek(delta) {
	currentDate = addDays(currentDate, delta * 7);
	syncDatePickers();
	renderAll();
}

// ══════════════════════════════════════════════
//  Render helpers
// ══════════════════════════════════════════════
function renderAll() {
	renderDayView();
	if (document.getElementById('tab-week').style.display !== 'none')
		renderWeekView();
	if (document.getElementById('tab-replicon').style.display !== 'none')
		renderRepliconView();
	document.getElementById('headerDate').textContent =
		formatDateHeader(currentDate);
}

function switchTab(name) {
	['day', 'week', 'replicon', 'data'].forEach((t) => {
		document.getElementById('tab-' + t).style.display =
			t === name ? 'block' : 'none';
	});
	document.querySelectorAll('.tab').forEach((btn, i) => {
		btn.classList.toggle(
			'active',
			['day', 'week', 'replicon', 'data'][i] === name,
		);
	});
	if (name === 'week') renderWeekView();
	if (name === 'data') {
		renderStats();
		populateSettingsTab();
	}
	if (name === 'replicon') renderRepliconView();
}

// ══════════════════════════════════════════════
//  Split modal — shared state + shared functions
// ══════════════════════════════════════════════
let _splitSourceId = null;
let _splitOriginalDurationMins = 0;
let _splitOriginalFinish = null;

function closeSplitModal() {
	document.getElementById('splitModal').classList.remove('show');
	_splitSourceId = null;
	_splitOriginalDurationMins = 0;
	_splitOriginalFinish = null;
}

function updateSplitRemaining() {
	const rows = document.getElementById('splitBody').querySelectorAll('tr');
	let usedMins = 0;
	rows.forEach((tr) => {
		const s = tr.querySelector('.sr-start')?.value;
		const f = tr.querySelector('.sr-finish')?.value;
		if (s && f) {
			const d = timeToMinutes(f) - timeToMinutes(s);
			if (d > 0) usedMins += d;
		}
	});

	const remainingMins = _splitOriginalDurationMins - usedMins;
	const badge = document.getElementById('splitRemaining');
	const absRem = Math.abs(remainingMins);
	const sign = remainingMins < 0 ? '-' : '';
	badge.textContent = sign + minutesToHHMM(absRem);
	badge.className =
		'remaining-badge' +
		(remainingMins === 0 ? ' balanced' : remainingMins < 0 ? ' over' : '');

	document.getElementById('splitUsed').textContent = minutesToHHMM(usedMins);

	// Cascade button — delta = last split finish minus original finish
	const cascadeBtn = document.getElementById('splitCascadeBtn');
	let lastFinish = '';
	rows.forEach((tr) => {
		const f = tr.querySelector('.sr-finish')?.value;
		if (f) lastFinish = f;
	});

	if (
		lastFinish &&
		_splitOriginalFinish &&
		lastFinish !== _splitOriginalFinish
	) {
		const deltaMins =
			timeToMinutes(lastFinish) - timeToMinutes(_splitOriginalFinish);
		const affected = loadEntries().filter(
			(e) =>
				e.date === currentDate &&
				e.id !== _splitSourceId &&
				timeToMinutes(e.start) >= timeToMinutes(_splitOriginalFinish),
		).length;
		const dsign = deltaMins >= 0 ? '+' : '−';
		const absD = Math.abs(deltaMins);
		const dh = Math.floor(absD / 60),
			dm = absD % 60;
		const dlabel = dh > 0 ? `${dh}h ${dm}m` : `${dm}m`;
		cascadeBtn.disabled = affected === 0;
		cascadeBtn.textContent =
			affected > 0
				? `Cascade ${dsign}${dlabel} to ${affected} row${affected > 1 ? 's' : ''}`
				: `Cascade ${dsign}${dlabel} — no rows after`;
	} else {
		cascadeBtn.disabled = true;
		cascadeBtn.textContent = 'Cascade change';
	}
}

// ══════════════════════════════════════════════
//  Keyboard shortcuts — shared helpers
// ══════════════════════════════════════════════
function toggleShortcutsHelp() {
	document.getElementById('shortcutsModal').classList.toggle('show');
}

function trapFocus(modal) {
	modal.addEventListener('keydown', function (e) {
		if (e.key !== 'Tab') return;
		const focusable = Array.from(
			modal.querySelectorAll(
				'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
			),
		).filter((el) => el.offsetParent !== null);
		if (focusable.length === 0) return;
		const first = focusable[0];
		const last = focusable[focusable.length - 1];
		if (e.shiftKey) {
			if (document.activeElement === first) {
				e.preventDefault();
				last.focus();
			}
		} else {
			if (document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
	});
}

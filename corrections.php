<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$user = get_auth_user();
if (($user['role'] ?? '') === 'employee') {
    header('Location: employee.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JustInTime | Corrections des pointages</title>
  <link rel="stylesheet" href="static/css/styles.css" />
  <style>
    /* ------- filter bar ------- */
    .corr-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    .corr-toolbar select {
      padding: 0.55rem 0.8rem;
      background: var(--surface-2);
      color: var(--ink);
      border: 1px solid var(--line);
      border-radius: 8px;
      font: inherit;
    }
    .corr-toolbar label { font-weight: 600; font-size: 0.9rem; color: var(--ink-soft); }

    /* ------- tabs ------- */
    .corr-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
    .corr-tab {
      padding: 0.55rem 1.1rem;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--surface-2);
      color: var(--ink-soft);
      font-weight: 600; font-size: 0.9rem;
      cursor: pointer;
    }
    .corr-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* ------- summary pills ------- */
    .corr-summary { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .corr-pill {
      padding: 0.6rem 1.1rem;
      border-radius: 999px;
      font-weight: 700; font-size: 0.9rem;
      border: 1px solid var(--line);
    }
    .corr-pill.warn { background: rgba(255,200,0,0.12); border-color: rgba(255,200,0,0.35); color: var(--warn); }
    .corr-pill.ok   { background: rgba(0,200,100,0.10); border-color: rgba(0,220,110,0.30); color: var(--ok); }
    .corr-pill.info { background: rgba(91,141,239,0.12); border-color: rgba(91,141,239,0.35); color: var(--accent); }

    /* ------- anomaly cards ------- */
    .anom-list { display: flex; flex-direction: column; gap: 1rem; }
    .anom-card {
      background: var(--surface-2);
      border: 1px solid var(--line);
      border-radius: 12px;
      overflow: hidden;
    }
    .anom-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      background: var(--surface);
      border-bottom: 1px solid var(--line);
      flex-wrap: wrap;
    }
    .anom-name  { font-weight: 700; font-size: 1rem; }
    .anom-date  { color: var(--ink-soft); font-size: 0.88rem; }
    .anom-badge {
      margin-left: auto;
      padding: 0.2rem 0.65rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
    }
    .badge-unpaired  { background: rgba(255,160,0,0.18); color: #ffaa00; }
    .badge-unscheduled { background: rgba(91,141,239,0.18); color: var(--accent); }

    .anom-events { padding: 0.5rem 1rem 0.75rem; }

    /* ------- event rows ------- */
    .ev-row {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.45rem 0.5rem;
      border-radius: 8px;
      margin-bottom: 0.25rem;
      flex-wrap: wrap;
    }
    .ev-row:hover { background: rgba(255,255,255,0.04); }
    .ev-type {
      min-width: 60px;
      padding: 0.18rem 0.6rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      text-align: center;
    }
    .ev-type.in  { background: rgba(0,200,100,0.16); color: #00c864; }
    .ev-type.out { background: rgba(255,100,0,0.16);  color: #ff6420; }
    .ev-ts   { font-size: 0.88rem; color: var(--ink-soft); min-width: 135px; }
    .ev-src  { font-size: 0.75rem; color: var(--ink-soft); opacity: 0.7; }

    /* edit inline form */
    .ev-edit-form {
      display: none;
      gap: 0.5rem;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 0.3rem;
      padding: 0.5rem 0.5rem 0.6rem;
      background: var(--surface);
      border-radius: 8px;
      border: 1px solid var(--line);
    }
    .ev-edit-form.open { display: flex; }
    .ev-edit-form select,
    .ev-edit-form input {
      padding: 0.4rem 0.6rem;
      background: var(--surface-2);
      color: var(--ink);
      border: 1px solid var(--line);
      border-radius: 6px;
      font: inherit;
      font-size: 0.88rem;
    }

    /* add event form */
    .add-event-form {
      display: none;
      gap: 0.5rem;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 0.6rem;
      padding: 0.6rem 0.5rem;
      background: var(--surface);
      border-radius: 8px;
      border: 1px solid var(--line);
    }
    .add-event-form.open { display: flex; }
    .add-event-form select,
    .add-event-form input {
      padding: 0.4rem 0.6rem;
      background: var(--surface-2);
      color: var(--ink);
      border: 1px solid var(--line);
      border-radius: 6px;
      font: inherit;
      font-size: 0.88rem;
    }

    /* buttons */
    .btn-xs {
      padding: 0.22rem 0.6rem; font-size: 0.78rem; border-radius: 6px;
      border: 1px solid transparent; cursor: pointer; font: inherit;
      font-size: 0.78rem; font-weight: 600;
    }
    .btn-xs.del   { background: rgba(230,50,50,0.16);  color: #e84040; border-color: rgba(230,50,50,0.3); }
    .btn-xs.edit  { background: rgba(91,141,239,0.16); color: var(--accent); border-color: rgba(91,141,239,0.3); }
    .btn-xs.save  { background: var(--accent); color: #fff; }
    .btn-xs.cancel{ background: var(--surface-2); color: var(--ink-soft); border-color: var(--line); }
    .btn-xs.add   { background: rgba(0,200,100,0.14); color: #00c864; border-color: rgba(0,200,100,0.3); }

    .btn-add-event {
      margin-top: 0.5rem;
      padding: 0.4rem 0.85rem;
      border-radius: 8px;
      border: 1px dashed var(--line);
      background: transparent;
      color: var(--ink-soft);
      cursor: pointer;
      font: inherit; font-size: 0.85rem;
      transition: color 0.15s, border-color 0.15s;
    }
    .btn-add-event:hover { color: var(--accent); border-color: var(--accent); }

    /* empty state */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--ink-soft);
      font-size: 1rem;
    }
    .empty-state span { font-size: 2.2rem; display: block; margin-bottom: 0.5rem; }

    /* loading */
    .corr-loading { text-align: center; padding: 2rem; color: var(--ink-soft); }

    /* error toast */
    #corr-toast {
      position: fixed; bottom: 1.5rem; right: 1.5rem;
      padding: 0.75rem 1.2rem;
      border-radius: 10px;
      font-weight: 600; font-size: 0.9rem;
      z-index: 9999;
      display: none;
    }
    #corr-toast.show { display: block; }
    #corr-toast.success { background: #1c3d2a; color: #6df0a0; border: 1px solid #3fa86a; }
    #corr-toast.error   { background: #3d1c1c; color: #f06d6d; border: 1px solid #a83f3f; }

    @media (max-width: 600px) {
      .anom-header { gap: 0.4rem; }
      .ev-row { font-size: 0.85rem; }
    }
  </style>
</head>
<body>
  <div class="page-bg" aria-hidden="true"></div>

  <nav class="app-nav">
    <div class="app-nav-inner">
      <a href="index.php" class="app-nav-logo">Just In Time</a>
      <div class="app-nav-links">
        <a href="dashboard.php">📊 Tableau de bord</a>
        <a href="reporting.php">📈 Reporting</a>
        <a href="corrections.php" class="active">✏️ Corrections</a>
        <?php if ($user['role'] === 'admin'): ?><a href="admin.php">🔧 Admin</a><?php endif; ?>
        <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
        <a href="logout.php">🚪 Logout</a>
      </div>
    </div>
  </nav>

  <main class="layout">
    <header class="hero">
      <h1>✏️ Correction des pointages</h1>
      <p class="subtitle">Pointages non appairés et présences sur jours non planifiés</p>
    </header>

    <div style="grid-column: span 12;">
      <!-- toolbar -->
      <div class="corr-toolbar">
        <label for="days-select">Période :</label>
        <select id="days-select">
          <option value="7">7 derniers jours</option>
          <option value="30" selected>30 derniers jours</option>
          <option value="60">60 derniers jours</option>
          <option value="90">90 derniers jours</option>
          <option value="180">6 derniers mois</option>
          <option value="365">12 derniers mois</option>
        </select>
        <button class="btn-xs save" id="btn-refresh" style="padding:0.45rem 1rem;font-size:0.88rem;">↺ Actualiser</button>
      </div>

      <!-- summary -->
      <div class="corr-summary" id="corr-summary"></div>

      <!-- tabs -->
      <div class="corr-tabs">
        <button class="corr-tab active" data-tab="unpaired">⚠️ Non appairés</button>
        <button class="corr-tab" data-tab="unscheduled">📅 Hors planning</button>
      </div>

      <!-- panels -->
      <div id="panel-unpaired">
        <div class="corr-loading">Chargement…</div>
      </div>
      <div id="panel-unscheduled" style="display:none;">
        <div class="corr-loading">Chargement…</div>
      </div>
    </div>
  </main>

  <div id="corr-toast"></div>

  <script>
  (() => {
    'use strict';

    const API = 'api/attendance_corrections.php';
    const DAY_FR = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

    let state = { unpaired: [], unscheduled: [], days: 30 };
    let activeTab = 'unpaired';

    // ----------------------------------------------------------------- utils -
    function esc(s) {
      return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function toast(msg, type = 'success') {
      const el = document.getElementById('corr-toast');
      el.textContent = msg;
      el.className = 'show ' + type;
      clearTimeout(el._t);
      el._t = setTimeout(() => el.classList.remove('show'), 3500);
    }

    function fmtDate(iso) {
      const d = new Date(iso + 'T00:00:00');
      const dow = DAY_FR[d.getDay()];
      return `${dow} ${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
    }

    function fmtTs(ts) {
      return ts ? ts.substring(0, 16).replace('T',' ') : '';
    }

    // Local datetime string for <input type="datetime-local">
    function toDatetimeLocal(ts) {
      if (!ts) return '';
      return ts.substring(0, 16).replace(' ', 'T');
    }

    // ----------------------------------------------------------------- fetch -
    async function loadAnomalies() {
      const days = parseInt(document.getElementById('days-select').value, 10);
      document.getElementById('panel-unpaired').innerHTML   = '<div class="corr-loading">Chargement…</div>';
      document.getElementById('panel-unscheduled').innerHTML = '<div class="corr-loading">Chargement…</div>';
      document.getElementById('corr-summary').innerHTML = '';

      try {
        const res  = await fetch(`${API}?action=anomalies&days=${days}`);
        const data = await res.json();
        if (!res.ok) { toast(data.error || 'Erreur serveur', 'error'); return; }
        state = { unpaired: data.unpaired || [], unscheduled: data.unscheduled || [], days };
        updateSummary();
        renderTab(activeTab);
      } catch (e) {
        toast('Impossible de charger les anomalies.', 'error');
      }
    }

    // --------------------------------------------------------------- summary -
    function updateSummary() {
      const { unpaired, unscheduled } = state;
      const total = unpaired.length + unscheduled.length;
      document.getElementById('corr-summary').innerHTML = `
        <span class="corr-pill ${total === 0 ? 'ok' : 'warn'}">
          ${total === 0 ? '✅' : '⚠️'} ${total} anomalie${total !== 1 ? 's' : ''} détectée${total !== 1 ? 's' : ''}
        </span>
        <span class="corr-pill info">⚠️ ${unpaired.length} non appairé${unpaired.length !== 1 ? 's' : ''}</span>
        <span class="corr-pill info">📅 ${unscheduled.length} hors planning</span>
      `;
      // Update tab labels
      document.querySelector('[data-tab="unpaired"]').textContent    = `⚠️ Non appairés (${unpaired.length})`;
      document.querySelector('[data-tab="unscheduled"]').textContent = `📅 Hors planning (${unscheduled.length})`;
    }

    // ----------------------------------------------------------------- render -
    function renderTab(tab) {
      const items = state[tab];
      const container = document.getElementById(`panel-${tab}`);

      if (!items.length) {
        container.innerHTML = `<div class="empty-state"><span>✅</span> Aucune anomalie détectée sur cette période.</div>`;
        return;
      }

      const cards = items.map(a => buildCard(a, tab)).join('');
      container.innerHTML = `<div class="anom-list">${cards}</div>`;
    }

    function buildCard(a, type) {
      const labelBadge = type === 'unpaired'
        ? `<span class="anom-badge badge-unpaired">Impair — ${a.cnt_in} arrivée${a.cnt_in !== 1 ? 's' : ''} / ${a.cnt_out} départ${a.cnt_out !== 1 ? 's' : ''}</span>`
        : `<span class="anom-badge badge-unscheduled">Hors planning — ${DAY_FR[a.day_of_week] ?? ''}</span>`;

      const evRows = (a.events || []).map(ev => buildEvRow(ev, a)).join('');
      const addBtn = `<button class="btn-add-event" data-emp="${a.employee_id}" data-day="${a.day}">+ Ajouter un pointage</button>`;
      const addForm = buildAddForm(a);

      return `
        <div class="anom-card" data-emp="${a.employee_id}" data-day="${a.day}">
          <div class="anom-header">
            <span class="anom-name">${esc(a.employee_name)}</span>
            <span class="anom-date">📅 ${fmtDate(a.day)}</span>
            ${labelBadge}
          </div>
          <div class="anom-events">
            <div class="ev-list">${evRows}</div>
            ${addBtn}
            ${addForm}
          </div>
        </div>`;
    }

    function buildEvRow(ev, a) {
      const tsLocal = toDatetimeLocal(ev.timestamp);
      return `
        <div class="ev-row" data-evid="${ev.id}">
          <span class="ev-type ${ev.event_type}">${ev.event_type === 'in' ? '🟢 Entrée' : '🔴 Sortie'}</span>
          <span class="ev-ts">${esc(fmtTs(ev.timestamp))}</span>
          <span class="ev-src">${ev.source === 'rfid' ? '📟 RFID' : '⌨️ Manuel'}</span>
          <button class="btn-xs edit"   onclick="corrEdit(this, ${ev.id})">✏️ Modifier</button>
          <button class="btn-xs del"    onclick="corrDelete(this, ${ev.id}, ${a.employee_id}, '${a.day}')">🗑 Supprimer</button>
        </div>
        <div class="ev-edit-form" id="edit-form-${ev.id}">
          <select id="edit-type-${ev.id}">
            <option value="in"  ${ev.event_type === 'in'  ? 'selected' : ''}>Entrée</option>
            <option value="out" ${ev.event_type === 'out' ? 'selected' : ''}>Sortie</option>
          </select>
          <input type="datetime-local" id="edit-ts-${ev.id}" value="${tsLocal}" />
          <button class="btn-xs save"   onclick="corrSaveEdit(${ev.id}, ${a.employee_id}, '${a.day}')">💾 Enregistrer</button>
          <button class="btn-xs cancel" onclick="corrCancelEdit(${ev.id})">Annuler</button>
        </div>`;
    }

    function buildAddForm(a) {
      return `
        <div class="add-event-form" id="add-form-${a.employee_id}-${a.day.replace(/-/g,'_')}">
          <select id="add-type-${a.employee_id}-${a.day.replace(/-/g,'_')}">
            <option value="in">Entrée</option>
            <option value="out">Sortie</option>
          </select>
          <input type="datetime-local" id="add-ts-${a.employee_id}-${a.day.replace(/-/g,'_')}"
                 value="${a.day}T09:00" />
          <button class="btn-xs save"   onclick="corrConfirmAdd(${a.employee_id}, '${a.day}')">💾 Ajouter</button>
          <button class="btn-xs cancel" onclick="corrCancelAdd(${a.employee_id}, '${a.day}')">Annuler</button>
        </div>`;
    }

    // -------------------------------------------------------- action handlers -
    window.corrEdit = function(btn, evId) {
      const form = document.getElementById(`edit-form-${evId}`);
      form.classList.toggle('open');
    };

    window.corrCancelEdit = function(evId) {
      document.getElementById(`edit-form-${evId}`).classList.remove('open');
    };

    window.corrSaveEdit = async function(evId, empId, day) {
      const eventType = document.getElementById(`edit-type-${evId}`).value;
      const ts        = document.getElementById(`edit-ts-${evId}`).value;
      if (!ts) { toast('Veuillez saisir une date/heure.', 'error'); return; }

      try {
        const res = await fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'edit', id: evId, event_type: eventType, timestamp: ts }),
        });
        const data = await res.json();
        if (!res.ok) { toast(data.error || 'Erreur', 'error'); return; }
        toast('Pointage modifié.', 'success');
        await refreshCard(empId, day);
      } catch (e) { toast('Erreur réseau.', 'error'); }
    };

    window.corrDelete = async function(btn, evId, empId, day) {
      if (!confirm('Supprimer ce pointage ?')) return;
      try {
        const res = await fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id: evId }),
        });
        const data = await res.json();
        if (!res.ok) { toast(data.error || 'Erreur', 'error'); return; }
        toast('Pointage supprimé.', 'success');
        await refreshCard(empId, day);
      } catch (e) { toast('Erreur réseau.', 'error'); }
    };

    window.corrCancelAdd = function(empId, day) {
      const key = `${empId}-${day.replace(/-/g,'_')}`;
      document.getElementById(`add-form-${key}`).classList.remove('open');
    };

    window.corrConfirmAdd = async function(empId, day) {
      const key       = `${empId}-${day.replace(/-/g,'_')}`;
      const eventType = document.getElementById(`add-type-${key}`).value;
      const ts        = document.getElementById(`add-ts-${key}`).value;
      if (!ts) { toast('Veuillez saisir une date/heure.', 'error'); return; }

      try {
        const res = await fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add', employee_id: empId, event_type: eventType, timestamp: ts }),
        });
        const data = await res.json();
        if (!res.ok) { toast(data.error || 'Erreur', 'error'); return; }
        toast('Pointage ajouté.', 'success');
        await refreshCard(empId, day);
      } catch (e) { toast('Erreur réseau.', 'error'); }
    };

    // Open add form
    document.addEventListener('click', e => {
      const btn = e.target.closest('.btn-add-event');
      if (!btn) return;
      const empId = btn.dataset.emp;
      const day   = btn.dataset.day;
      const key   = `${empId}-${day.replace(/-/g,'_')}`;
      const form  = document.getElementById(`add-form-${key}`);
      if (form) form.classList.toggle('open');
    });

    // ------------------------------------------------------- refresh one card -
    async function refreshCard(empId, day) {
      try {
        const res  = await fetch(`${API}?action=day_events&employee_id=${empId}&day=${day}`);
        const data = await res.json();
        if (!res.ok) return;

        const newEvents = data.events || [];

        // Update the events in state (both tabs, same employee+day may appear in both)
        ['unpaired', 'unscheduled'].forEach(tab => {
          const idx = state[tab].findIndex(a => a.employee_id === empId && a.day === day);
          if (idx < 0) return;

          // Re-check pairing
          const cntIn  = newEvents.filter(e => e.event_type === 'in').length;
          const cntOut = newEvents.filter(e => e.event_type === 'out').length;

          if (tab === 'unpaired' && cntIn === cntOut) {
            // Anomaly resolved
            state[tab].splice(idx, 1);
          } else {
            state[tab][idx].events  = newEvents;
            state[tab][idx].cnt_in  = cntIn;
            state[tab][idx].cnt_out = cntOut;
          }

          if (tab === 'unscheduled' && newEvents.length === 0) {
            state[tab].splice(idx, 1);
          }
        });

        updateSummary();
        renderTab(activeTab);
      } catch (_) { /* silent */ }
    }

    // ------------------------------------------------------------------ tabs -
    document.querySelectorAll('.corr-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.corr-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeTab = btn.dataset.tab;
        ['unpaired','unscheduled'].forEach(t => {
          document.getElementById(`panel-${t}`).style.display = t === activeTab ? '' : 'none';
        });
        renderTab(activeTab);
      });
    });

    // ----------------------------------------------------------------- init -
    document.getElementById('btn-refresh').addEventListener('click', loadAnomalies);
    document.getElementById('days-select').addEventListener('change', loadAnomalies);

    loadAnomalies();
  })();
  </script>
</body>
</html>

<?php require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login('admin');
$user = get_auth_user();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JustInTime | Admin</title>
  <link rel="stylesheet" href="static/css/styles.css?v=20260412-2" />
  <style>
    .admin-nav {
      grid-column: span 12;
      display: flex;
      flex-wrap: wrap;
      align-items: stretch;
      column-gap: 0.5rem;
      row-gap: 0.5rem;
      margin-bottom: 1.5rem;
      width: 100%;
    }
    .admin-nav .tab-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      min-height: 46px;
      height: 46px;
      line-height: 1.35;
      padding: 0.7rem 0.9rem;
      border: 1px solid var(--line);
      border-radius: 10px;
      cursor: pointer;
      white-space: nowrap;
      font-size: 0.9rem;
      font-weight: 600;
      font-family: 'Sora', sans-serif;
      background: var(--surface-2);
      color: var(--ink-soft);
      transform: none;
      flex: 1 1 calc(25% - 0.5rem);
      max-width: calc(25% - 0.5rem);
      transition: border-color 0.2s, color 0.2s, background 0.2s;
    }
    .admin-nav .tab-btn:hover {
      transform: none !important;
      filter: none;
      border-color: var(--accent);
      color: var(--ink);
    }
    .admin-nav .tab-btn.active {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; font-size: 0.95rem; color: var(--ink-soft); }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 0.6rem;
      background: var(--surface-2); color: var(--ink);
      border: 1px solid var(--line); border-radius: 8px; font: inherit;
    }
    .employee-row {
      padding: 0.8rem; border: 1px solid var(--line);
      margin-bottom: 0.5rem; border-radius: 10px;
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 0.5rem;
      background: var(--surface-2);
    }
    .employee-row button { padding: 0.4rem 0.8rem; margin-left: 0.3rem; font-size: 0.85rem; }
    .btn-delete { background: var(--warn); color: #0a0a0a; }
    .btn-edit { background: var(--accent); }
    @media (max-width: 920px) {
      .admin-nav .tab-btn {
        flex: 1 1 calc(50% - 0.5rem);
        max-width: calc(50% - 0.5rem);
      }
    }
    @media (max-width: 560px) {
      .admin-nav { gap: 0.35rem; }
      .admin-nav .tab-btn {
        min-height: 42px; height: 42px;
        padding: 0.55rem 0.7rem; font-size: 0.8rem;
        flex: 1 1 100%; max-width: 100%;
      }
      .employee-row { flex-direction: column; align-items: flex-start; }
      .employee-row button { margin-left: 0; width: 100%; }
    }
  </style>
  <style>
    .btn-access { background: #7c5cbf; color: #fff; border: 0; border-radius: 8px; cursor: pointer; padding: 0.4rem 0.8rem; font-size: 0.85rem; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: var(--surface); border: 1px solid var(--line);
      border-radius: var(--radius); padding: clamp(1rem, 4vw, 2rem);
      width: min(90vw, 460px); box-shadow: var(--shadow);
    }
    .modal-box h3 { margin-top: 0; font-size: clamp(1rem, 2.5vw, 1.3rem); }
    .modal-close { float: right; background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--ink-soft); }
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
        <a href="admin.php">🔧 Admin</a>
        <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
        <a href="logout.php">🚪 Logout</a>
      </div>
    </div>
  </nav>

  <main class="layout">
    <header class="hero">
      <h1>🔧 Administration</h1>
      <p class="subtitle">Gestion des collaborateurs, absences et horaires</p>
    </header>

    <div class="admin-nav">
      <button class="tab-btn active" data-tab="employees">👥 Collaborateurs</button>
      <button class="tab-btn" data-tab="absences">📋 Absences</button>
      <button class="tab-btn" data-tab="hours">⏰ Horaires</button>
      <button class="tab-btn" data-tab="vacation-requests">🏖️ Congés</button>
    </div>

    <!-- Onglet Collaborateurs -->
    <div id="employees" class="tab-content active panel">
      <h2>Gestion des collaborateurs</h2>
      <form id="employee-form" class="form-group">
        <input type="hidden" id="emp-id" />
        <div class="form-grid-2">
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-first">Prenom</label>
            <input id="emp-first" type="text" required />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-last">Nom</label>
            <input id="emp-last" type="text" required />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-badge">Badge RFID</label>
            <input id="emp-badge" type="text" required />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-active">Actif</label>
            <select id="emp-active">
              <option value="1">Oui</option>
              <option value="0">Non</option>
            </select>
          </div>
        </div>
        <div class="form-grid-auto" style="margin-top: 0.5rem;">
          <div class="form-group" style="margin: 0; grid-column: span 2;">
            <label for="emp-address">Adresse (lieu de travail)</label>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
              <input id="emp-address" type="text" placeholder="Ex: 12 rue de la Paix, Paris" style="flex:1; min-width: 180px;" />
              <button type="button" id="btn-geocode" style="padding: 0.5rem 0.8rem; background: var(--accent); color:#fff; border:0; border-radius:6px; cursor:pointer; white-space:nowrap;">📍 Geocoder</button>
            </div>
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="emp-lat">Latitude</label>
            <input id="emp-lat" type="number" step="0.0000001" placeholder="48.8566" />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="emp-lng">Longitude</label>
            <input id="emp-lng" type="number" step="0.0000001" placeholder="2.3522" />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="emp-geo-radius">Rayon GPS (metres)</label>
            <input id="emp-geo-radius" type="number" min="50" max="5000" value="200" />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="emp-vacation-days">Jours de conges/an</label>
            <input id="emp-vacation-days" type="number" min="0" max="60" value="25" />
          </div>
        </div>
        <button type="submit" class="btn-in" style="margin-top: 1rem;">Enregistrer</button>
      </form>

      <h3 style="margin-top: 2rem;">Liste des collaborateurs</h3>
      <div id="employees-list"></div>
    </div>

    <!-- Modal gestion d'acces employe -->
    <div id="access-modal" class="modal-overlay">
      <div class="modal-box">
        <button class="modal-close" id="access-modal-close">&times;</button>
        <h3 id="access-modal-title">Acces employe</h3>
        <div id="access-modal-body"></div>
      </div>
    </div>
    <!-- Onglet Absences -->
    <div id="absences" class="tab-content panel">
      <h2>Gestion des absences</h2>
      <form id="absence-form" class="form-group">
        <div style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr;">
          <div class="form-group">
            <label for="abs-employee">Collaborateur</label>
            <select id="abs-employee" required></select>
          </div>
          <div class="form-group">
            <label for="abs-type">Type</label>
            <select id="abs-type" required>
              <option value="sick">Malade (Certificat)</option>
              <option value="vacation">Conge</option>
              <option value="other">Autre</option>
            </select>
          </div>
          <div class="form-group">
            <label for="abs-start">Date debut</label>
            <input id="abs-start" type="date" required />
          </div>
          <div class="form-group">
            <label for="abs-end">Date fin</label>
            <input id="abs-end" type="date" required />
          </div>
        </div>
        <div class="form-group">
          <label for="abs-reason">Motif (optionnel)</label>
          <textarea id="abs-reason" placeholder="Certificat medical, etc."></textarea>
        </div>
        <button type="submit" class="btn-in">Ajouter absence</button>
      </form>

      <h3 style="margin-top: 2rem;">Absences en cours</h3>
      <div id="absences-list"></div>
    </div>

    <!-- Onglet Horaires -->
    <div id="hours" class="tab-content panel">
      <h2>Horaires prevus par collaborateur</h2>
      <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <div class="form-group">
          <label for="hours-employee">Collaborateur</label>
          <select id="hours-employee"></select>
        </div>
        <div class="form-group">
          <label for="hours-apply-to">Portee</label>
          <select id="hours-apply-to">
            <option value="default">Horaire de reference (chaque semaine)</option>
            <option value="week">Semaine specifique</option>
          </select>
        </div>
        <div class="form-group" id="hours-week-wrap" style="display:none;">
          <label for="hours-week-start">Semaine du (lundi)</label>
          <input id="hours-week-start" type="date" />
        </div>
      </div>

      <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-top: 0.5rem;">
        <div class="form-group">
          <label for="hours-mode">Mode d'encodage</label>
          <select id="hours-mode">
            <option value="reference">Horaire de reference (heure debut/fin)</option>
            <option value="daily">Nombre d'heures par jour</option>
            <option value="weekly">Nombre d'heures par semaine</option>
          </select>
        </div>
      </div>

      <div id="hours-reference" style="display:none; margin-top: 1rem; border:1px solid var(--line); border-radius: 10px; padding: 0.9rem;">
        <h3 style="margin-top:0; font-size:1rem;">Horaire de reference</h3>
        <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
          <div class="form-group" style="margin:0;">
            <label for="ref-start">Heure de debut</label>
            <input id="ref-start" type="time" value="08:00" />
          </div>
          <div class="form-group" style="margin:0;">
            <label for="ref-end">Heure de fin</label>
            <input id="ref-end" type="time" value="17:00" />
          </div>
        </div>
        <div class="form-group" style="margin-top: 0.8rem;">
          <label>Jours concernes</label>
          <div style="display:flex; gap:0.8rem; flex-wrap:wrap;">
            <label><input type="checkbox" class="ref-day" value="1" checked /> Lun</label>
            <label><input type="checkbox" class="ref-day" value="2" checked /> Mar</label>
            <label><input type="checkbox" class="ref-day" value="3" checked /> Mer</label>
            <label><input type="checkbox" class="ref-day" value="4" checked /> Jeu</label>
            <label><input type="checkbox" class="ref-day" value="5" checked /> Ven</label>
            <label><input type="checkbox" class="ref-day" value="6" /> Sam</label>
            <label><input type="checkbox" class="ref-day" value="0" /> Dim</label>
          </div>
        </div>
      </div>

      <div id="hours-daily" style="margin-top: 1rem;">
        <div id="hours-grid" style="display: grid; gap: 0.5rem;"></div>
      </div>

      <div id="hours-weekly" style="display:none; margin-top: 1rem; border:1px solid var(--line); border-radius: 10px; padding: 0.9rem;">
        <h3 style="margin-top:0; font-size:1rem;">Volume hebdomadaire</h3>
        <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
          <div class="form-group" style="margin:0;">
            <label for="weekly-hours-total">Heures a prester par semaine</label>
            <input id="weekly-hours-total" type="number" min="0" max="80" step="0.5" value="40" />
          </div>
        </div>
        <div class="form-group" style="margin-top: 0.8rem;">
          <label>Repartir sur les jours</label>
          <div style="display:flex; gap:0.8rem; flex-wrap:wrap;">
            <label><input type="checkbox" class="weekly-day" value="1" checked /> Lun</label>
            <label><input type="checkbox" class="weekly-day" value="2" checked /> Mar</label>
            <label><input type="checkbox" class="weekly-day" value="3" checked /> Mer</label>
            <label><input type="checkbox" class="weekly-day" value="4" checked /> Jeu</label>
            <label><input type="checkbox" class="weekly-day" value="5" checked /> Ven</label>
            <label><input type="checkbox" class="weekly-day" value="6" /> Sam</label>
            <label><input type="checkbox" class="weekly-day" value="0" /> Dim</label>
          </div>
        </div>
      </div>
      <button id="btn-save-hours" class="btn-in" style="margin-top: 1rem;">Enregistrer horaires</button>
    </div>

    <!-- Onglet Demandes de congés -->
    <div id="vacation-requests" class="tab-content panel">
      <h2>Demandes de congés</h2>
      <style>
        .vac-filter { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .vac-filter select { padding: 0.6rem; border: 1px solid var(--line); border-radius: 6px; font: inherit; }
        .vac-request-item {
          padding: 1rem;
          border: 1px solid var(--line);
          border-radius: 8px;
          margin-bottom: 0.8rem;
          background: var(--surface-2);
        }
        .vac-request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap; gap: 0.5rem; }
        .vac-request-name { font-weight: 600; }
        .vac-request-dates { color: var(--ink-soft); font-size: 0.9rem; }
        .vac-request-reason { color: var(--ink-soft); font-size: 0.9rem; margin: 0.5rem 0; }
        .vac-request-comment { color: var(--ink-soft); font-size: 0.85rem; font-style: italic; margin: 0.5rem 0; }
        .vac-status { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; }
        .vac-status.pending { background: rgba(248, 113, 113, 0.15); color: var(--warn); }
        .vac-status.approved { background: rgba(52, 211, 153, 0.15); color: var(--ok); }
        .vac-status.rejected { background: rgba(248, 113, 113, 0.2); color: #f87171; }
        .vac-actions { display: flex; gap: 0.5rem; margin-top: 0.8rem; flex-wrap: wrap; }
        .vac-actions button { padding: 0.5rem 1rem; font-size: 0.85rem; border: 0; border-radius: 6px; cursor: pointer; }
        .vac-actions .approve { background: var(--ok); color: #0a0a0a; }
        .vac-actions .reject { background: var(--warn); color: #0a0a0a; }
        @media (max-width: 560px) {
          .vac-filter { flex-direction: column; }
          .vac-filter select { width: 100%; }
          .vac-request-header { flex-direction: column; align-items: flex-start; }
        }
      </style>
      <div class="vac-filter">
        <select id="vac-status-filter">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="approved">Approuvé</option>
          <option value="rejected">Rejeté</option>
        </select>
      </div>
      <div id="vac-requests-list" style="min-height: 200px;">
        <p style="color: var(--ink-soft);">Chargement des demandes...</p>
      </div>
    </div>

    <section id="toast" class="toast" role="status" aria-live="polite"></section>
  </main>

  <script>
    const els = {
      empForm: document.getElementById('employee-form'),
      empId: document.getElementById('emp-id'),
      empFirst: document.getElementById('emp-first'),
      empLast: document.getElementById('emp-last'),
      empBadge: document.getElementById('emp-badge'),
      empActive: document.getElementById('emp-active'),
      employeesList: document.getElementById('employees-list'),
      empAddress:     document.getElementById('emp-address'),
      empLat:         document.getElementById('emp-lat'),
      empLng:         document.getElementById('emp-lng'),
      empGeoRadius:   document.getElementById('emp-geo-radius'),
      empVacDays:     document.getElementById('emp-vacation-days'),
      btnGeocode:     document.getElementById('btn-geocode'),
      absenceForm: document.getElementById('absence-form'),
      absEmployee: document.getElementById('abs-employee'),
      absType: document.getElementById('abs-type'),
      absStart: document.getElementById('abs-start'),
      absEnd: document.getElementById('abs-end'),
      absReason: document.getElementById('abs-reason'),
      absencesList: document.getElementById('absences-list'),
      hoursEmployee: document.getElementById('hours-employee'),
      hoursApplyTo: document.getElementById('hours-apply-to'),
      hoursWeekWrap: document.getElementById('hours-week-wrap'),
      hoursWeekStart: document.getElementById('hours-week-start'),
      hoursMode: document.getElementById('hours-mode'),
      hoursReference: document.getElementById('hours-reference'),
      hoursDaily: document.getElementById('hours-daily'),
      hoursWeekly: document.getElementById('hours-weekly'),
      refStart: document.getElementById('ref-start'),
      refEnd: document.getElementById('ref-end'),
      weeklyHoursTotal: document.getElementById('weekly-hours-total'),
      hoursGrid: document.getElementById('hours-grid'),
      btnSaveHours: document.getElementById('btn-save-hours'),
      vacStatusFilter: document.getElementById('vac-status-filter'),
      vacRequestsList: document.getElementById('vac-requests-list'),
      toast: document.getElementById('toast'),
    };

    let toastTimer = null;

    function showToast(msg, isError = false) {
      els.toast.textContent = msg;
      els.toast.style.background = isError ? '#7f2323' : '#1f2f29';
      els.toast.classList.add('show');
      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(() => els.toast.classList.remove('show'), 2400);
    }

    async function api(path, opts = {}) {
      const res = await fetch(path, {
        headers: { 'Content-Type': 'application/json', ...opts.headers },
        ...opts,
      });
      const raw = await res.text();
      let data = {};
      try { data = raw ? JSON.parse(raw) : {}; } catch (_) {}
      if (!res.ok) {
        const fallback = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
        throw new Error(data.error || fallback || `Erreur serveur (${res.status})`);
      }
      return data;
    }

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
      });
    });

    // Collaborateurs
    let empCache = [];

    async function loadEmployees() {
      try {
        const prevAbsValue = els.absEmployee.value;
        const prevHoursValue = els.hoursEmployee.value;
        const currentEditId = els.empId.value;

        const data = await api('api/employees.php?action=list');
        empCache = data.employees;
        els.employeesList.innerHTML = data.employees.map(e => `
          <div class="employee-row">
            <div>
              <strong>${e.first_name} ${e.last_name}</strong><br/>
              <small>${e.badge_id}</small>
              ${e.address ? `<br/><small style="color:var(--ink-soft)">📍 ${e.address}</small>` : ''}
              <br/><small style="color:${e.login_username ? 'var(--ok)' : 'var(--warn)'}">
                🔑 ${e.login_username ? e.login_username : "Pas d'acces"}
              </small>
            </div>
            <div>
              <button class="btn-edit" onclick="editEmployee(${e.id})">Modifier</button>
              <button class="btn-access" onclick="openAccess(${e.id})">🔑 Acces</button>
              <button class="btn-delete" onclick="deleteEmployee(${e.id})">Supprimer</button>
            </div>
          </div>
        `).join('');

        const optionsHtml = `<option value="">-- Choisir --</option>` + data.employees.map(e => `
          <option value="${e.id}">${e.first_name} ${e.last_name}</option>
        `).join('');

        els.absEmployee.innerHTML = optionsHtml;
        els.hoursEmployee.innerHTML = optionsHtml;

        const existingIds = new Set(data.employees.map(e => String(e.id)));
        const preferredId = currentEditId || prevHoursValue;

        if (existingIds.has(prevAbsValue)) {
          els.absEmployee.value = prevAbsValue;
        }
        if (existingIds.has(preferredId)) {
          els.hoursEmployee.value = preferredId;
        }
      } catch (e) { showToast(e.message, true); }
    }

    window.editEmployee = (id) => {
      const emp = empCache.find(e => e.id === id);
      if (!emp) return;
      els.empId.value     = emp.id;
      els.empFirst.value  = emp.first_name;
      els.empLast.value   = emp.last_name;
      els.empBadge.value  = emp.badge_id;
      els.empActive.value = emp.active;
      els.empAddress.value   = emp.address || '';
      els.empLat.value       = emp.latitude ?? '';
      els.empLng.value       = emp.longitude ?? '';
      els.empGeoRadius.value = emp.geo_radius || 200;
      els.empVacDays.value   = emp.vacation_days || 25;
      document.getElementById('employees').scrollIntoView({ behavior: 'smooth' });
      els.empFirst.focus();
    };

    window.deleteEmployee = async (id) => {
      if (!confirm('Confirmer la suppression ?')) return;
      try {
        await api('api/employees.php?action=delete&id=' + id, { method: 'POST' });
        showToast('Employe supprime');
        loadEmployees();
      } catch (e) { showToast(e.message, true); }
    };

    els.empForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await api('api/employees.php?action=save', {
          method: 'POST',
          body: JSON.stringify({
            id: els.empId.value || null,
            first_name: els.empFirst.value,
            last_name: els.empLast.value,
            badge_id: els.empBadge.value,
            active: els.empActive.value,
            address: els.empAddress.value,
            latitude: els.empLat.value !== '' ? parseFloat(els.empLat.value) : null,
            longitude: els.empLng.value !== '' ? parseFloat(els.empLng.value) : null,
            geo_radius: parseInt(els.empGeoRadius.value, 10) || 200,
            vacation_days: parseInt(els.empVacDays.value, 10) || 25,
          }),
        });
        showToast('Collaborateur enregistre');
        els.empForm.reset();
        els.empGeoRadius.value = 200;
        els.empVacDays.value = 25;
        loadEmployees();
      } catch (e) { showToast(e.message, true); }
    });

    // Absences
    async function loadAbsences() {
      try {
        const data = await api('api/absences.php?action=list');
        els.absencesList.innerHTML = data.absences.map(a => `
          <div style="padding: 0.8rem; border: 1px solid var(--line); margin-bottom: 0.5rem; border-radius: 8px;">
            <strong>${a.employee_name}</strong> - ${a.type}<br/>
            <small>${a.start_date} au ${a.end_date}</small><br/>
            ${a.reason ? '<em>' + a.reason + '</em>' : ''}
            <button class="btn-delete" onclick="deleteAbsence(${a.id})" style="float: right;">Supprimer</button>
          </div>
        `).join('');
      } catch (e) { showToast(e.message, true); }
    }

    window.deleteAbsence = async (id) => {
      if (!confirm('Confirmer ?')) return;
      try {
        await api('api/absences.php?action=delete&id=' + id, { method: 'POST' });
        showToast('Absence supprimee');
        loadAbsences();
      } catch (e) { showToast(e.message, true); }
    };

    els.absenceForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await api('api/absences.php', {
          method: 'POST',
          body: JSON.stringify({
            employee_id: els.absEmployee.value,
            type: els.absType.value,
            start_date: els.absStart.value,
            end_date: els.absEnd.value,
            reason: els.absReason.value,
          }),
        });
        showToast('Absence enregistree');
        els.absenceForm.reset();
        loadAbsences();
      } catch (e) { showToast(e.message, true); }
    });

    // Horaires
    const dayLabels = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    function toMondayISO(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr + 'T00:00:00');
      if (Number.isNaN(d.getTime())) return '';
      const day = d.getDay();
      const diff = day === 0 ? -6 : 1 - day;
      d.setDate(d.getDate() + diff);
      return d.toISOString().slice(0, 10);
    }

    function ensureWeekDefault() {
      if (!els.hoursWeekStart.value) {
        els.hoursWeekStart.value = toMondayISO(new Date().toISOString().slice(0, 10));
      }
    }

    function renderHoursGrid(rows = []) {
      els.hoursGrid.innerHTML = dayLabels.map((day, idx) => {
        const existing = rows.find(h => Number(h.day_of_week) === idx);
        const val = existing ? Number(existing.hours) : 0;
        return `
          <div class="form-group">
            <label>${day}</label>
            <input type="number" data-day="${idx}" min="0" max="24" step="0.25" value="${Number.isFinite(val) ? val : 0}" />
          </div>
        `;
      }).join('');
    }

    function updateHoursModeVisibility() {
      const mode = els.hoursMode.value;
      const applyTo = els.hoursApplyTo.value;

      els.hoursWeekWrap.style.display = applyTo === 'week' ? 'block' : 'none';
      els.hoursReference.style.display = mode === 'reference' ? 'block' : 'none';
      els.hoursDaily.style.display = mode === 'daily' ? 'block' : 'none';
      els.hoursWeekly.style.display = mode === 'weekly' ? 'block' : 'none';
    }

    function applyHoursDataToForm(rows = []) {
      renderHoursGrid(rows);

      if (!rows.length) {
        els.hoursMode.value = 'reference';
        els.refStart.value = '08:00';
        els.refEnd.value = '17:00';
        updateHoursModeVisibility();
        return;
      }

      const mode = rows[0].entry_mode || 'daily';
      els.hoursMode.value = mode;

      if (mode === 'reference') {
        const first = rows.find(r => r.start_time && r.end_time) || rows[0];
        if (first.start_time) els.refStart.value = first.start_time.slice(0, 5);
        if (first.end_time) els.refEnd.value = first.end_time.slice(0, 5);
        const selectedDays = new Set(rows.map(r => Number(r.day_of_week)));
        document.querySelectorAll('.ref-day').forEach(cb => {
          cb.checked = selectedDays.has(Number(cb.value));
        });
      }

      if (mode === 'weekly') {
        const weeklyTotal = rows.reduce((acc, r) => acc + Number(r.hours || 0), 0);
        els.weeklyHoursTotal.value = Math.round(weeklyTotal * 100) / 100;
        const selectedDays = new Set(rows.map(r => Number(r.day_of_week)));
        document.querySelectorAll('.weekly-day').forEach(cb => {
          cb.checked = selectedDays.has(Number(cb.value));
        });
      }

      updateHoursModeVisibility();
    }

    async function loadHoursForSelected() {
      const empId = els.hoursEmployee.value;
      if (!empId) {
        renderHoursGrid([]);
        return;
      }

      try {
        let url = 'api/scheduled_hours.php?action=get&employee_id=' + encodeURIComponent(empId);
        if (els.hoursApplyTo.value === 'week') {
          ensureWeekDefault();
          url += '&week_start=' + encodeURIComponent(toMondayISO(els.hoursWeekStart.value));
        }
        const data = await api(url);
        applyHoursDataToForm(data.hours || []);
      } catch (e) {
        showToast(e.message, true);
      }
    }

    els.hoursEmployee.addEventListener('change', loadHoursForSelected);
    els.hoursApplyTo.addEventListener('change', () => {
      updateHoursModeVisibility();
      loadHoursForSelected();
    });
    els.hoursWeekStart.addEventListener('change', () => {
      els.hoursWeekStart.value = toMondayISO(els.hoursWeekStart.value);
      loadHoursForSelected();
    });
    els.hoursMode.addEventListener('change', updateHoursModeVisibility);

    els.btnSaveHours.addEventListener('click', async () => {
      const empId = els.hoursEmployee.value;
      if (!empId) {
        showToast('Selectionnez un collaborateur', true);
        return;
      }

      const mode = els.hoursMode.value;
      const applyTo = els.hoursApplyTo.value;
      const payload = {
        employee_id: Number(empId),
        mode,
        apply_to: applyTo,
      };

      if (applyTo === 'week') {
        ensureWeekDefault();
        payload.week_start = toMondayISO(els.hoursWeekStart.value);
      }

      if (mode === 'daily') {
        const hours = {};
        document.querySelectorAll('[data-day]').forEach(inp => {
          hours[inp.dataset.day] = parseFloat(inp.value) || 0;
        });
        payload.hours = hours;
      }

      if (mode === 'reference') {
        const days = Array.from(document.querySelectorAll('.ref-day:checked')).map(cb => Number(cb.value));
        if (!days.length) {
          showToast('Selectionnez au moins un jour pour l\'horaire de reference.', true);
          return;
        }
        payload.start_time = els.refStart.value;
        payload.end_time = els.refEnd.value;
        payload.days = days;
      }

      if (mode === 'weekly') {
        const days = Array.from(document.querySelectorAll('.weekly-day:checked')).map(cb => Number(cb.value));
        if (!days.length) {
          showToast('Selectionnez au moins un jour de prestation.', true);
          return;
        }
        payload.weekly_hours = parseFloat(els.weeklyHoursTotal.value) || 0;
        payload.days = days;
      }

      try {
        await api('api/scheduled_hours.php?action=save', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        showToast('Horaires enregistres');
        await loadHoursForSelected();
      } catch (e) {
        showToast(e.message, true);
      }
    });

    ensureWeekDefault();
    updateHoursModeVisibility();
    renderHoursGrid([]);

    // Geocodage via Nominatim (OpenStreetMap)
    els.btnGeocode.addEventListener('click', async () => {
      const addr = els.empAddress.value.trim();
      if (!addr) {
        showToast('Saisissez une adresse d\'abord.', true);
        return;
      }
      els.btnGeocode.disabled = true;
      els.btnGeocode.textContent = '...';
      try {
        const res = await fetch(
          'https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(addr) + '&format=json&limit=1',
          { headers: { 'Accept-Language': 'fr' } }
        );
        const data = await res.json();
        if (!data.length) {
          showToast('Adresse introuvable. Essayez avec plus de details.', true);
          return;
        }
        els.empLat.value = parseFloat(data[0].lat).toFixed(7);
        els.empLng.value = parseFloat(data[0].lon).toFixed(7);
        showToast('Coordonnees trouvees : ' + els.empLat.value + ', ' + els.empLng.value);
      } catch (e) {
        showToast('Erreur geocodage : ' + e.message, true);
      } finally {
        els.btnGeocode.disabled = false;
        els.btnGeocode.textContent = '📍 Geocoder';
      }
    });

    // Modal acces employe
    const accessModal = document.getElementById('access-modal');
    document.getElementById('access-modal-close').addEventListener('click', () => accessModal.classList.remove('open'));
    accessModal.addEventListener('click', (e) => {
      if (e.target === accessModal) accessModal.classList.remove('open');
    });

    window.openAccess = (empId) => {
      const emp = empCache.find(e => e.id === empId);
      if (!emp) return;
      document.getElementById('access-modal-title').textContent = 'Acces : ' + emp.first_name + ' ' + emp.last_name;
      const body = document.getElementById('access-modal-body');
      if (emp.login_username) {
        body.innerHTML = `
          <p>Compte actuel : <strong>${emp.login_username}</strong></p>
          <button class="btn-delete" onclick="deleteAccess(${empId})">Supprimer l'acces</button>
        `;
      } else {
        body.innerHTML = `
          <div class="form-group"><label>Identifiant</label>
            <input id="acc-username" type="text" placeholder="prenom.nom" /></div>
          <div class="form-group"><label>Mot de passe (6 car. min)</label>
            <input id="acc-password" type="password" /></div>
          <button class="btn-in" onclick="createAccess(${empId})">Creer l'acces</button>
        `;
      }
      accessModal.classList.add('open');
    };

    window.createAccess = async (empId) => {
      const usernameEl = document.getElementById('acc-username');
      const passwordEl = document.getElementById('acc-password');
      const username = usernameEl ? usernameEl.value.trim() : '';
      const password = passwordEl ? passwordEl.value : '';
      try {
        await api('api/employees.php?action=create_access', {
          method: 'POST',
          body: JSON.stringify({ employee_id: empId, username, password }),
        });
        showToast('Acces cree !');
        accessModal.classList.remove('open');
        loadEmployees();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    window.deleteAccess = async (empId) => {
      if (!confirm('Supprimer le compte de cet employe ?')) return;
      try {
        await api('api/employees.php?action=delete_access', {
          method: 'POST',
          body: JSON.stringify({ employee_id: empId }),
        });
        showToast('Acces supprime.');
        accessModal.classList.remove('open');
        loadEmployees();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    // Demandes de congés
    async function loadVacationRequests() {
      try {
        const filter = els.vacStatusFilter.value;
        const url = 'api/vacation_requests.php?action=list' + (filter ? '&status=' + filter : '');
        const data = await api(url);
        const requests = data.requests || [];

        if (!requests.length) {
          els.vacRequestsList.innerHTML = '<p style="color: var(--ink-soft);">Aucune demande de congé.</p>';
          return;
        }

        els.vacRequestsList.innerHTML = requests.map(req => {
          const sd = new Date(req.start_date).toLocaleDateString('fr-FR');
          const ed = new Date(req.end_date).toLocaleDateString('fr-FR');
          const statusLabel = { pending: 'En attente', approved: 'Approuvé', rejected: 'Rejeté' }[req.status] || '?';
          return `<div class="vac-request-item">
            <div class="vac-request-header">
              <span class="vac-request-name">${req.emp_first} ${req.emp_last}</span>
              <span class="vac-status ${req.status}">${statusLabel}</span>
            </div>
            <div class="vac-request-dates">📅 ${sd} au ${ed}</div>
            ${req.reason ? '<div class="vac-request-reason"><strong>Motif:</strong> ' + req.reason + '</div>' : ''}
            ${req.admin_comment ? '<div class="vac-request-comment"><strong>Commentaire:</strong> ' + req.admin_comment + '</div>' : ''}
            <div class="vac-actions">
              ${req.status === 'pending' ? `
                <button class="approve" onclick="approveRequest(${req.id})">✓ Approuver</button>
                <button class="reject" onclick="rejectRequest(${req.id})">✗ Rejeter</button>
              ` : ''}
            </div>
          </div>`;
        }).join('');
      } catch (e) {
        els.vacRequestsList.innerHTML = '<p style="color: var(--warn);">Erreur : ' + e.message + '</p>';
      }
    }

    window.approveRequest = async (requestId) => {
      const comment = prompt('Commentaire (optionnel):');
      if (comment === null) return; // Annulation
      try {
        await api('api/vacation_requests.php?action=review', {
          method: 'POST',
          body: JSON.stringify({ request_id: requestId, status: 'approved', comment }),
        });
        showToast('✓ Demande approuvée');
        loadVacationRequests();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    window.rejectRequest = async (requestId) => {
      const comment = prompt('Motif du rejet:');
      if (comment === null) return; // Annulation
      try {
        await api('api/vacation_requests.php?action=review', {
          method: 'POST',
          body: JSON.stringify({ request_id: requestId, status: 'rejected', comment }),
        });
        showToast('✗ Demande rejetée');
        loadVacationRequests();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    els.vacStatusFilter.addEventListener('change', loadVacationRequests);

    loadEmployees();
    loadAbsences();
    loadVacationRequests();
  </script>
</body>
</html>

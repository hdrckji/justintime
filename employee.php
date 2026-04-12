<?php
require_once __DIR__ . '/auth.php';
require_login();
$auth = get_auth_user();
if (!$auth['employee_id']) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JustInTime | Mon espace</title>
  <link rel="stylesheet" href="static/css/styles.css" />
  <style>
    .emp-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      grid-column: span 12;
    }
    @media (max-width: 768px) {
      .emp-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    }
    @media (max-width: 560px) {
      .emp-grid { grid-template-columns: 1fr; }
    }
    .status-badge {
      display: inline-block;
      padding: 0.3rem 0.9rem;
      border-radius: 99px;
      font-weight: 700;
      font-size: 0.85rem;
      letter-spacing: 0.04em;
    }
    .status-badge.present { background: rgba(52,211,153,0.15); color: var(--ok); }
    .status-badge.absent  { background: rgba(248,113,113,0.15); color: var(--warn); }
    .btn-pointage {
      width: 100%;
      padding: 1rem;
      font-size: 1.1rem;
      font-weight: 700;
      border: 0;
      border-radius: var(--radius);
      cursor: pointer;
      transition: filter 0.15s;
      margin-top: 0.5rem;
    }
    .btn-pointage:hover { filter: brightness(1.08); }
    .btn-pointage.in  { background: var(--ok);  color: #0a0a0a; }
    .btn-pointage.out { background: var(--warn); color: #0a0a0a; }
    .btn-pointage.loading { opacity: 0.6; pointer-events: none; }
    .vac-bar-bg { background: var(--line); border-radius: 99px; height: 10px; margin-top: 0.5rem; }
    .vac-bar-fill { background: var(--accent); border-radius: 99px; height: 10px; transition: width 0.4s; }
    .day-group { margin-bottom: 1.2rem; }
    .day-group h4 { margin: 0 0 0.4rem; font-size: 0.82rem; text-transform: uppercase;
                    letter-spacing: 0.06em; color: var(--ink-soft); }
    .event-pill {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0.3rem 0.7rem; border-radius: 99px; font-size: 0.84rem;
      margin: 0.2rem 0.2rem 0 0;
    }
    .event-pill.in  { background: rgba(52,211,153,0.12); color: var(--ok); }
    .event-pill.out { background: rgba(248,113,113,0.12); color: var(--warn); }
    .geo-status { font-size: 0.78rem; color: var(--ink-soft); margin-top: 0.4rem; min-height: 1.2em; }
    .vac-request-form { display: grid; gap: 0.8rem; }
    .vac-request-form input, .vac-request-form textarea {
      width: 100%; padding: 0.6rem;
      background: var(--surface-2); color: var(--ink);
      border: 1px solid var(--line); border-radius: 8px; font: inherit;
    }
    .vac-request-form button {
      background: var(--accent); color: #fff;
      padding: 0.7rem; font-weight: 600; cursor: pointer;
      border: 0; border-radius: 8px;
    }
    .cal-toolbar { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; margin-bottom: 1rem; }
    .cal-toolbar select, .cal-toolbar button {
      padding: 0.45rem 0.8rem; border-radius: 8px; border: 1px solid var(--line);
      background: var(--surface-2); color: var(--ink); font: inherit; cursor: pointer;
    }
    .cal-toolbar button { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 600; }
    .cal-toolbar button:hover { filter: brightness(1.08); }
    .cal-grid {
      display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;
      font-size: 0.78rem;
    }
    .cal-header { text-align: center; padding: 0.3rem 0; font-weight: 700;
                  color: var(--ink-soft); text-transform: uppercase; font-size: 0.72rem; }
    .cal-cell {
      min-height: 58px; padding: 0.3rem;
      background: var(--surface-2); border-radius: 6px;
      border: 1px solid var(--line);
      display: flex; flex-direction: column; gap: 2px;
    }
    .cal-cell.other-month { opacity: 0.3; }
    .cal-cell.today-cell  { border-color: var(--accent); }
    .cal-cell.has-events  { background: rgba(99,198,190,0.06); }
    .cal-day-num { font-size: 0.72rem; font-weight: 700; color: var(--ink-soft); }
    .cal-day-num.today-num { color: var(--accent); }
    .cal-pill {
      display: inline-block; padding: 1px 5px; border-radius: 99px; font-size: 0.68rem; font-weight: 600;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
    }
    .cal-pill.in  { background: rgba(52,211,153,0.18); color: var(--ok); }
    .cal-pill.out { background: rgba(248,113,113,0.18); color: var(--warn); }
    .cal-pill.both { background: rgba(99,198,190,0.2); color: var(--accent); }
    .cal-nav { display: flex; align-items: center; gap: 0.5rem; }
    .cal-nav button { background: var(--surface-2); color: var(--ink); border: 1px solid var(--line);
                      padding: 0.3rem 0.7rem; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
    .cal-nav button:hover { border-color: var(--accent); }
    .cal-legend { display: flex; gap: 0.8rem; margin-top: 0.6rem; font-size: 0.76rem; color: var(--ink-soft); align-items:center; }
    .cal-legend span { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 3px; vertical-align: middle; }
    .view-tabs { display: flex; gap: 0.5rem; margin-bottom: 0.8rem; }
    .view-tab { padding: 0.35rem 0.85rem; border-radius: 6px; border: 1px solid var(--line);
                background: var(--surface-2); color: var(--ink); font: inherit; cursor: pointer; font-size: 0.85rem; }
    .view-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 600; }
    .week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
    .week-cell { background: var(--surface-2); border: 1px solid var(--line); border-radius: 8px;
                 padding: 0.5rem 0.3rem; min-height: 80px; }
    .week-cell.today-cell { border-color: var(--accent); }
    .week-cell-header { font-size: 0.73rem; font-weight: 700; color: var(--ink-soft);
                        text-align: center; margin-bottom: 0.3rem; text-transform: uppercase; }
    .week-cell-date { font-size: 0.78rem; text-align: center; color: var(--ink-soft); margin-bottom: 0.4rem; }
    .week-cell-date.today-num { color: var(--accent); font-weight: 700; }
  </style>
</head>
<body>
  <div class="page-bg" aria-hidden="true"></div>

  <nav class="app-nav">
    <div class="app-nav-inner">
      <a href="index.php" class="app-nav-logo">Just In Time</a>
      <div class="app-nav-links">
        <span class="app-nav-user" id="emp-nav-name">👤 Mon espace</span>
        <a href="logout.php">🚪 Déconnexion</a>
      </div>
    </div>
  </nav>

  <main class="layout">

    <!-- En-tête -->
    <header class="hero" style="grid-column: span 12;">
      <p class="kicker" id="today-label"><?= date('l d F Y') ?></p>
      <h1 id="emp-name">Bonjour —</h1>
      <p class="subtitle" id="emp-status-line">Chargement en cours…</p>
    </header>

    <!-- Grille des 3 cartes -->
    <div class="emp-grid">

      <!-- Carte statut du jour -->
      <div class="panel">
        <h2 style="margin-top:0;">📅 Aujourd'hui</h2>
        <p>Statut : <span class="status-badge absent" id="status-badge">—</span></p>
        <div id="today-times" style="color: var(--ink-soft); font-size: 0.9rem;"></div>
      </div>

      <!-- Carte pointage manuel -->
      <div class="panel">
        <h2 style="margin-top:0;">✋ Pointage manuel</h2>
        <button class="btn-pointage in" id="btn-pointage">⏳ Chargement…</button>
        <div class="geo-status" id="geo-status"></div>
      </div>

      <!-- Carte congés -->
      <div class="panel">
        <h2 style="margin-top:0;">🌴 Congés <?= date('Y') ?></h2>
        <p id="vac-text" style="margin: 0 0 0.4rem;">—</p>
        <div class="vac-bar-bg"><div class="vac-bar-fill" id="vac-bar" style="width:0%"></div></div>
        <p style="font-size:0.8rem; color:var(--ink-soft); margin-top:0.4rem;" id="vac-sub"></p>
      </div>

    </div>

    <!-- Demande de congé -->
    <div class="panel" style="grid-column: span 12;">
      <h2 style="margin-top:0;">🏖️ Demander des congés</h2>
      <form id="vac-request-form">
        <div style="display: grid; gap: 0.8rem; grid-template-columns: 1fr 1fr; margin-bottom: 1rem;">
          <div>
            <label for="vac-start" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Date de début</label>
            <input id="vac-start" type="date" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--line); border-radius: 6px;" />
          </div>
          <div>
            <label for="vac-end" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Date de fin</label>
            <input id="vac-end" type="date" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--line); border-radius: 6px;" />
          </div>
        </div>
        <div style="margin-bottom: 1rem;">
          <label for="vac-reason" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Motif (optionnel)</label>
          <textarea id="vac-reason" rows="3" placeholder="Ex: Vacances en famille, repos, etc." style="width: 100%; padding: 0.6rem; border: 1px solid var(--line); border-radius: 6px; font: inherit;"></textarea>
        </div>
        <button type="submit" id="btn-vac-submit" style="width: 100%; padding: 0.7rem; background: var(--accent); color: #fff; border: 0; border-radius: 6px; font-weight: 600; cursor: pointer;">📤 Soumettre la demande</button>
      </form>
    </div>

    <!-- Calendrier des pointages -->
    <div class="panel" style="grid-column: span 12;">
      <h2 style="margin-top:0;">🗓️ Calendrier des pointages</h2>

      <div class="cal-toolbar">
        <div class="cal-nav">
          <button id="cal-prev" title="Mois précédent">&#8592;</button>
          <strong id="cal-month-label" style="min-width: 140px; text-align:center; font-size:1rem;"></strong>
          <button id="cal-next" title="Mois suivant">&#8594;</button>
        </div>
        <select id="cal-year" style="min-width:90px;"></select>
        <select id="cal-month" style="min-width:120px;">
          <option value="1">Janvier</option><option value="2">Février</option>
          <option value="3">Mars</option><option value="4">Avril</option>
          <option value="5">Mai</option><option value="6">Juin</option>
          <option value="7">Juillet</option><option value="8">Août</option>
          <option value="9">Septembre</option><option value="10">Octobre</option>
          <option value="11">Novembre</option><option value="12">Décembre</option>
        </select>
        <div class="view-tabs">
          <button class="view-tab active" id="tab-month">Mois</button>
          <button class="view-tab" id="tab-week">Semaine</button>
        </div>
        <select id="cal-week" style="min-width:120px; display:none;"></select>
      </div>

      <div id="cal-container"><p style="color:var(--ink-soft);">Chargement…</p></div>

      <div class="cal-legend">
        <span style="background:rgba(52,211,153,0.35);"></span> Entrée seule
        <span style="background:rgba(248,113,113,0.35);"></span> Sortie seule
        <span style="background:rgba(99,198,190,0.35);"></span> Entrée + Sortie
      </div>
    </div>

    <section id="toast" class="toast" role="status" aria-live="polite"></section>
  </main>

  <script>
    let toastTimer = null;
    function showToast(msg, isError = false) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.style.background = isError ? '#7f2323' : '#1f2f29';
      t.classList.add('show');
      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
    }

    async function apiCall(path, opts = {}) {
      const res = await fetch(path, {
        headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
        ...opts,
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || 'Erreur serveur');
      return data;
    }

    function fmtTime(iso) {
      return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }
    function fmtDate(iso) {
      return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    }
    function fmtDateShort(iso) {
      return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
    }

    let nextEventType = 'in';

    function renderToday(events) {
      const badge = document.getElementById('status-badge');
      const times = document.getElementById('today-times');
      const btn   = document.getElementById('btn-pointage');

      const ins  = events.filter(e => e.event_type === 'in');
      const outs = events.filter(e => e.event_type === 'out');
      const isPresent = events.length > 0 && events[events.length - 1].event_type === 'in';

      badge.textContent = isPresent ? 'Présent' : (events.length > 0 ? 'Parti' : 'Absent');
      badge.className   = 'status-badge ' + (isPresent ? 'present' : 'absent');

      let html = '';
      if (ins.length)  html += `<div>🟢 Entrée :  ${ins.map(e => fmtTime(e.timestamp)).join(', ')}</div>`;
      if (outs.length) html += `<div>🔴 Sortie :  ${outs.map(e => fmtTime(e.timestamp)).join(', ')}</div>`;
      times.innerHTML = html || '<em>Aucun pointage aujourd\'hui.</em>';

      nextEventType = isPresent ? 'out' : 'in';
      btn.textContent  = nextEventType === 'in' ? '🟢 Pointer mon arrivée' : '🔴 Pointer ma sortie';
      btn.className    = 'btn-pointage ' + nextEventType;
      document.getElementById('emp-status-line').textContent =
        isPresent ? '✅ Vous êtes actuellement présent(e).' : '⭕ Vous n\'êtes pas pointé(e) aujourd\'hui.';
    }

    function renderVacation(vac) {
      const pct = vac.total > 0 ? Math.min(100, Math.round((vac.used / vac.total) * 100)) : 0;
      document.getElementById('vac-text').innerHTML =
        `<strong style="font-size:1.5rem;">${vac.balance}</strong> jours restants`;
      document.getElementById('vac-bar').style.width = pct + '%';
      document.getElementById('vac-sub').textContent =
        `${vac.used} pris sur ${vac.total} jours (${vac.year})`;
    }

    function renderHistory(history) {
      // conservé pour compatibilité — l'affichage est géré par le calendrier
    }

    async function loadData() {
      try {
        const data = await apiCall('api/me.php');
        const emp  = data.employee;
        document.getElementById('emp-name').textContent =
          'Bonjour ' + emp.first_name + ' ' + emp.last_name + ' 👋';
        renderToday(data.today);
        renderVacation(data.vacation);
        renderHistory(data.history);
      } catch (e) {
        showToast(e.message, true);
      }
    }

    // Pointage avec géolocalisation
    document.getElementById('btn-pointage').addEventListener('click', async () => {
      const btn = document.getElementById('btn-pointage');
      const geoStatus = document.getElementById('geo-status');
      btn.classList.add('loading');
      geoStatus.textContent = '📡 Récupération de votre position…';

      const doPointage = async (lat, lng) => {
        try {
          const res = await apiCall('api/me_pointage.php', {
            method: 'POST',
            body: JSON.stringify({ latitude: lat, longitude: lng }),
          });
          const label = res.event_type === 'in' ? 'Arrivée' : 'Sortie';
          showToast('✅ ' + label + ' enregistrée !');
          geoStatus.textContent = `📍 Position validée (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
          await loadData();
        } catch (e) {
          if (e.message.includes('trop loin')) {
            geoStatus.textContent = '❌ ' + e.message;
          } else {
            geoStatus.textContent = '';
          }
          showToast(e.message, true);
        } finally {
          btn.classList.remove('loading');
        }
      };

      // Tenter la géolocalisation
      if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
          pos => doPointage(pos.coords.latitude, pos.coords.longitude),
          () => {
            // Permission refusée ou indisponible => blocage du pointage
            geoStatus.textContent = '❌ Localisation refusée : pointage impossible.';
            showToast('Activez la localisation pour pouvoir pointer.', true);
            btn.classList.remove('loading');
          },
          { timeout: 10000, maximumAge: 60000 }
        );
      } else {
        geoStatus.textContent = '❌ Géolocalisation non supportée : pointage impossible.';
        showToast('Ce navigateur ne permet pas la géolocalisation.', true);
        btn.classList.remove('loading');
      }
    });

    // Demande de congé
    document.getElementById('vac-request-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-vac-submit');
      const startDate = document.getElementById('vac-start').value;
      const endDate = document.getElementById('vac-end').value;
      const reason = document.getElementById('vac-reason').value;

      if (!startDate || !endDate) {
        showToast('Veuillez remplir les dates.', true);
        return;
      }

      if (startDate > endDate) {
        showToast('La date de fin doit être après la date de début.', true);
        return;
      }

      btn.disabled = true;
      btn.textContent = '⏳ Soumission…';

      try {
        await apiCall('api/vacation_requests.php', {
          method: 'POST',
          body: JSON.stringify({
            start_date: startDate,
            end_date: endDate,
            reason: reason,
          }),
        });
        showToast('✅ Demande de congé soumise avec succès !');
        document.getElementById('vac-request-form').reset();
      } catch (e) {
        showToast(e.message, true);
      } finally {
        btn.disabled = false;
        btn.textContent = '📤 Soumettre la demande';
      }
    });

    // Calendrier des pointages
    const DAYS_FR = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin',
                       'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

    let calYear  = new Date().getFullYear();
    let calMonth = new Date().getMonth() + 1; // 1-12
    let calView  = 'month'; // 'month' | 'week'
    let calWeek  = 1;
    let calEvents = []; // cache des évènements chargés

    function initCalFilters() {
      const yearSel = document.getElementById('cal-year');
      const thisYear = new Date().getFullYear();
      for (let y = thisYear - 3; y <= thisYear + 1; y++) {
        const o = document.createElement('option');
        o.value = y; o.textContent = y;
        if (y === thisYear) o.selected = true;
        yearSel.appendChild(o);
      }
      document.getElementById('cal-month').value = calMonth;
    }

    function rebuildWeekOptions() {
      const sel = document.getElementById('cal-week');
      sel.innerHTML = '';
      const firstDay = new Date(calYear, calMonth - 1, 1);
      const dow = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // 0=lun
      const firstMonday = new Date(firstDay);
      firstMonday.setDate(1 - dow + (dow === 0 ? 0 : 0));
      if (dow > 0) firstMonday.setDate(firstMonday.getDate() - (dow - 0));
      // trouver le 1er lundi dans ou avant le 1er du mois
      const d = new Date(calYear, calMonth - 1, 1);
      const dayOfWeek = (d.getDay() + 6) % 7; // 0=lun
      d.setDate(d.getDate() - dayOfWeek);

      let week = 1;
      const cur = new Date(d);
      while (cur.getMonth() <= calMonth - 1 || cur.getFullYear() < calYear) {
        if (cur.getFullYear() > calYear) break;
        const end = new Date(cur); end.setDate(end.getDate() + 6);
        const fmtD = (dt) => `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}`;
        const o = document.createElement('option');
        o.value = week;
        o.textContent = `Semaine ${week}  (${fmtD(cur)} – ${fmtD(end)})`;
        if (week === calWeek) o.selected = true;
        sel.appendChild(o);
        cur.setDate(cur.getDate() + 7);
        week++;
        if (week > 6) break;
      }
    }

    async function loadCalEvents() {
      let url = `api/me.php?action=history&year=${calYear}&month=${calMonth}`;
      if (calView === 'week') url += `&week=${calWeek}`;
      try {
        const data = await apiCall(url);
        calEvents = data.events || [];
        renderCal();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    function eventsForDay(dateStr) {
      return calEvents.filter(e => e.timestamp.substring(0, 10) === dateStr);
    }

    function dayPillHtml(events) {
      const hasIn  = events.some(e => e.event_type === 'in');
      const hasOut = events.some(e => e.event_type === 'out');
      const times  = events.map(e => `${e.event_type === 'in' ? '🟢' : '🔴'} ${e.timestamp.substring(11, 16)}`);
      if (!events.length) return '';
      const cls = hasIn && hasOut ? 'both' : (hasIn ? 'in' : 'out');
      return times.map(t => `<span class="cal-pill ${cls}" title="${t}">${t}</span>`).join('');
    }

    function renderCal() {
      const container = document.getElementById('cal-container');
      const todayStr  = new Date().toISOString().substring(0, 10);
      document.getElementById('cal-month-label').textContent = `${MONTHS_FR[calMonth - 1]} ${calYear}`;

      if (calView === 'month') {
        renderMonthView(container, todayStr);
      } else {
        renderWeekView(container, todayStr);
      }
    }

    function renderMonthView(container, todayStr) {
      const firstOfMonth = new Date(calYear, calMonth - 1, 1);
      const startDow = (firstOfMonth.getDay() + 6) % 7; // 0=lun
      const daysInMonth = new Date(calYear, calMonth, 0).getDate();
      const prevDays = new Date(calYear, calMonth - 1, 0).getDate();

      let html = '<div class="cal-grid">';
      DAYS_FR.forEach(d => { html += `<div class="cal-header">${d}</div>`; });

      let dayCount = 1;
      // Cases du mois précédent
      for (let i = 0; i < startDow; i++) {
        const d = prevDays - startDow + i + 1;
        const prevMonth = calMonth === 1 ? 12 : calMonth - 1;
        const prevYear  = calMonth === 1 ? calYear - 1 : calYear;
        const ds = `${prevYear}-${String(prevMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const evts = eventsForDay(ds);
        html += `<div class="cal-cell other-month${evts.length ? ' has-events' : ''}">
          <span class="cal-day-num">${d}</span>${dayPillHtml(evts)}</div>`;
      }

      while (dayCount <= daysInMonth) {
        const ds = `${calYear}-${String(calMonth).padStart(2,'0')}-${String(dayCount).padStart(2,'0')}`;
        const isToday = ds === todayStr;
        const evts = eventsForDay(ds);
        html += `<div class="cal-cell${isToday ? ' today-cell' : ''}${evts.length ? ' has-events' : ''}">
          <span class="cal-day-num${isToday ? ' today-num' : ''}">${dayCount}</span>${dayPillHtml(evts)}</div>`;
        dayCount++;
      }

      // Cases du mois suivant
      const totalCells = startDow + daysInMonth;
      const remain = (7 - (totalCells % 7)) % 7;
      for (let i = 1; i <= remain; i++) {
        const nextMonth = calMonth === 12 ? 1 : calMonth + 1;
        const nextYear  = calMonth === 12 ? calYear + 1 : calYear;
        const ds = `${nextYear}-${String(nextMonth).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
        const evts = eventsForDay(ds);
        html += `<div class="cal-cell other-month${evts.length ? ' has-events' : ''}">
          <span class="cal-day-num">${i}</span>${dayPillHtml(evts)}</div>`;
      }

      html += '</div>';
      container.innerHTML = html;
    }

    function renderWeekView(container, todayStr) {
      // Trouver le lundi de la semaine calWeek du mois
      const d = new Date(calYear, calMonth - 1, 1);
      const dayOfWeek = (d.getDay() + 6) % 7;
      d.setDate(d.getDate() - dayOfWeek + (calWeek - 1) * 7);

      let html = '<div class="week-grid">';
      for (let i = 0; i < 7; i++) {
        const cur = new Date(d); cur.setDate(d.getDate() + i);
        const ds = cur.toISOString().substring(0, 10);
        const isToday = ds === todayStr;
        const evts = eventsForDay(ds);
        const dayLabel = DAYS_FR[i];
        const dateLabel = `${String(cur.getDate()).padStart(2,'0')}/${String(cur.getMonth()+1).padStart(2,'0')}`;
        html += `<div class="week-cell${isToday ? ' today-cell' : ''}">
          <div class="week-cell-header">${dayLabel}</div>
          <div class="week-cell-date${isToday ? ' today-num' : ''}">${dateLabel}</div>
          ${dayPillHtml(evts) || '<span style="color:var(--ink-soft);font-size:0.72rem;">—</span>'}
        </div>`;
      }
      html += '</div>';
      container.innerHTML = html;
    }

    function updateCalViewUI() {
      document.getElementById('tab-month').classList.toggle('active', calView === 'month');
      document.getElementById('tab-week').classList.toggle('active', calView === 'week');
      document.getElementById('cal-week').style.display = calView === 'week' ? 'block' : 'none';
    }

    initCalFilters();
    rebuildWeekOptions();
    updateCalViewUI();

    document.getElementById('cal-year').addEventListener('change', e => {
      calYear = Number(e.target.value); calWeek = 1; rebuildWeekOptions(); loadCalEvents();
    });
    document.getElementById('cal-month').addEventListener('change', e => {
      calMonth = Number(e.target.value); calWeek = 1; rebuildWeekOptions(); loadCalEvents();
    });
    document.getElementById('cal-week').addEventListener('change', e => {
      calWeek = Number(e.target.value); loadCalEvents();
    });
    document.getElementById('cal-prev').addEventListener('click', () => {
      if (calMonth === 1) { calMonth = 12; calYear--; } else { calMonth--; }
      calWeek = 1;
      document.getElementById('cal-year').value = calYear;
      document.getElementById('cal-month').value = calMonth;
      rebuildWeekOptions();
      loadCalEvents();
    });
    document.getElementById('cal-next').addEventListener('click', () => {
      if (calMonth === 12) { calMonth = 1; calYear++; } else { calMonth++; }
      calWeek = 1;
      document.getElementById('cal-year').value = calYear;
      document.getElementById('cal-month').value = calMonth;
      rebuildWeekOptions();
      loadCalEvents();
    });
    document.getElementById('tab-month').addEventListener('click', () => {
      calView = 'month'; updateCalViewUI(); loadCalEvents();
    });
    document.getElementById('tab-week').addEventListener('click', () => {
      calView = 'week'; updateCalViewUI(); loadCalEvents();
    });

    loadCalEvents();

    loadData();
  </script>
</body>
</html>

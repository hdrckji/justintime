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
    .vac-request-form button:hover { filter: brightness(1.08); }
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

    <!-- Historique -->
    <div class="panel" style="grid-column: span 12;">
      <h2 style="margin-top:0;">🗓️ Historique (30 jours)</h2>
      <div id="history-container"><p style="color:var(--ink-soft);">Chargement…</p></div>
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
      const container = document.getElementById('history-container');
      if (!history.length) {
        container.innerHTML = '<p style="color:var(--ink-soft);">Aucun pointage dans les 30 derniers jours.</p>';
        return;
      }
      // Grouper par date
      const groups = {};
      history.forEach(e => {
        const d = e.timestamp.substring(0, 10);
        if (!groups[d]) groups[d] = [];
        groups[d].push(e);
      });

      container.innerHTML = Object.entries(groups).map(([date, events]) => {
        const pills = events.slice().reverse().map(e =>
          `<span class="event-pill ${e.event_type}">
            ${e.event_type === 'in' ? '🟢' : '🔴'} ${fmtTime(e.timestamp)}
            <small>${e.source === 'rfid' ? 'RFID' : 'Manuel'}</small>
          </span>`
        ).join('');
        const today = new Date().toISOString().substring(0, 10);
        const label = date === today ? "Aujourd'hui" : fmtDate(date + 'T00:00:00');
        return `<div class="day-group"><h4>${label}</h4>${pills}</div>`;
      }).join('');
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

    loadData();
  </script>
</body>
</html>

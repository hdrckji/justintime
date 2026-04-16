<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = get_auth_user();
if ($user['role'] === 'employee') {
    header('Location: employee.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>JustInTime | Tableau de bord</title>
    <link rel="stylesheet" href="static/css/styles.css" />
    <style>
      .dashboard-layout .panel-manual {
        grid-column: span 12;
      }

      .dashboard-layout .manual-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        gap: 0.9rem;
        align-items: end;
      }

      .dashboard-layout .manual-grid label {
        margin: 0;
        font-weight: 600;
        color: var(--ink-soft);
      }

      .dashboard-layout .manual-grid select {
        margin-top: 0.35rem;
      }

      .dashboard-layout .stats {
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      }

      .dashboard-layout .stat-card {
        min-height: 110px;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .dashboard-layout .stat-card.pending strong {
        color: #fbbf24;
      }

      .dashboard-layout .panel[aria-labelledby="team-title"] {
        margin-top: 0.2rem;
      }

      .dashboard-layout .employee-item {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 0.8rem;
        align-items: center;
      }

      .dashboard-layout .employee-link {
        appearance: none;
        border: 0;
        background: transparent;
        padding: 0;
        text-align: left;
        cursor: pointer;
        color: var(--ink);
        font: inherit;
      }

      .dashboard-layout .employee-link p {
        margin: 0;
        font-weight: 700;
      }

      .dashboard-layout .employee-link:hover p {
        text-decoration: underline;
      }

      .dashboard-layout .calendar-modal {
        position: fixed;
        inset: 0;
        background: rgba(10, 14, 23, 0.68);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        z-index: 1200;
      }

      .dashboard-layout .calendar-modal.open {
        display: flex;
      }

      .dashboard-layout .calendar-modal-card {
        width: min(1120px, 100%);
        max-height: 92vh;
        overflow: auto;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 1rem;
      }

      .dashboard-layout .calendar-modal-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: start;
        margin-bottom: 0.9rem;
      }

      .dashboard-layout .calendar-modal-head h3 {
        margin: 0;
      }

      .dashboard-layout .calendar-modal-close {
        appearance: none;
        border: 1px solid var(--line);
        background: var(--surface-2);
        color: var(--ink);
        border-radius: 10px;
        min-width: 40px;
        min-height: 40px;
        cursor: pointer;
      }

      .dashboard-layout .calendar-toolbar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.8rem;
        align-items: end;
        margin-bottom: 1rem;
      }

      .dashboard-layout .calendar-toolbar .actions {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
      }

      .dashboard-layout .calendar-toolbar label {
        display: block;
        margin-bottom: 0.35rem;
        color: var(--ink-soft);
        font-weight: 600;
      }

      .dashboard-layout .calendar-toolbar input,
      .dashboard-layout .calendar-toolbar select {
        width: 100%;
      }

      .dashboard-layout .calendar-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
      }

      .dashboard-layout .calendar-summary-card {
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
        padding: 0.8rem;
      }

      .dashboard-layout .calendar-summary-card p {
        margin: 0;
        color: var(--ink-soft);
        font-size: 0.82rem;
      }

      .dashboard-layout .calendar-summary-card strong {
        display: block;
        margin-top: 0.35rem;
        font-size: 1.15rem;
      }

      .dashboard-layout .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.6rem;
      }

      .dashboard-layout .calendar-weekday {
        color: var(--ink-soft);
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0 0.25rem;
      }

      .dashboard-layout .calendar-spacer {
        min-height: 1px;
      }

      .dashboard-layout .calendar-day {
        min-height: 158px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface-2);
        padding: 0.7rem;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
      }

      .dashboard-layout .calendar-day.complete {
        border-color: rgba(52, 211, 153, 0.35);
      }

      .dashboard-layout .calendar-day.incomplete {
        border-color: rgba(251, 191, 36, 0.4);
      }

      .dashboard-layout .calendar-day.empty {
        opacity: 0.82;
      }

      .dashboard-layout .calendar-day-head {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        align-items: baseline;
      }

      .dashboard-layout .calendar-day-head strong {
        font-size: 1rem;
      }

      .dashboard-layout .calendar-day-meta {
        font-size: 0.8rem;
        color: var(--ink-soft);
        display: grid;
        gap: 0.15rem;
      }

      .dashboard-layout .calendar-events {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: auto;
      }

      .dashboard-layout .calendar-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 999px;
        padding: 0.22rem 0.5rem;
        background: rgba(255,255,255,0.06);
        color: var(--ink);
        font-size: 0.76rem;
        white-space: nowrap;
      }

      .dashboard-layout .calendar-chip.in {
        background: rgba(52, 211, 153, 0.15);
      }

      .dashboard-layout .calendar-chip.out {
        background: rgba(251, 146, 60, 0.16);
      }

      .dashboard-layout .calendar-empty {
        border: 1px dashed var(--line);
        border-radius: 14px;
        background: var(--surface-2);
        padding: 1rem;
        color: var(--ink-soft);
      }

      @media (max-width: 700px) {
        .dashboard-layout .manual-grid {
          grid-template-columns: 1fr;
        }

        .dashboard-layout .manual-grid .actions {
          width: 100%;
        }

        .dashboard-layout .calendar-grid {
          grid-template-columns: 1fr;
        }

        .dashboard-layout .calendar-weekday,
        .dashboard-layout .calendar-spacer {
          display: none;
        }
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
          <?php if ($user['role'] === 'admin'): ?><a href="admin.php">🔧 Admin</a><?php endif; ?>
            <a href="corrections.php">✏️ Corrections</a>
            <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
          <a href="logout.php">🚪 Logout</a>
        </div>
      </div>
    </nav>

    <main class="layout dashboard-layout">
      <header class="hero">
        <p class="kicker">PME - 20 collaborateurs</p>
        <h1>Tableau de bord</h1>
        <p class="subtitle">Vue d'ensemble des présences et des actions RH à traiter.</p>
      </header>

      <section class="panel panel-manual" aria-labelledby="manual-title">
        <h2 id="manual-title">Pointage manuel</h2>
        <form id="manual-form" class="manual-grid">
          <label for="employee-select">Employe</label>
          <select id="employee-select" required></select>
          <div class="actions">
            <button type="button" id="btn-in" class="btn-in">Entree</button>
            <button type="button" id="btn-out" class="btn-out">Sortie</button>
          </div>
        </form>
      </section>

      <section class="stats" aria-label="Indicateurs">
        <article class="stat-card">
          <p>Total equipe</p>
          <strong id="stat-total">0</strong>
        </article>
        <article class="stat-card present">
          <p>Presents</p>
          <strong id="stat-present">0</strong>
        </article>
        <article class="stat-card absent">
          <p>Absents</p>
          <strong id="stat-absent">0</strong>
        </article>
        <article class="stat-card">
          <p>Evenements aujourd'hui</p>
          <strong id="stat-events">0</strong>
        </article>
        <article class="stat-card pending">
          <p>Corrections a traiter</p>
          <strong id="stat-corrections">0</strong>
        </article>
      </section>

      <section class="panel" aria-labelledby="team-title">
        <div class="panel-head">
          <h2 id="team-title">Statut equipe</h2>
          <button id="refresh-btn" class="ghost">Actualiser</button>
        </div>
        <div id="employee-list" class="employee-list"></div>
      </section>

      <section class="panel" aria-labelledby="events-title">
        <h2 id="events-title">Derniers pointages</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Heure</th>
                <th>Employe</th>
                <th>Badge</th>
                <th>Action</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody id="events-body"></tbody>
          </table>
        </div>
      </section>

      <section id="toast" class="toast" role="status" aria-live="polite"></section>

      <section id="employee-calendar-modal" class="calendar-modal" aria-hidden="true">
        <div class="calendar-modal-card">
          <div class="calendar-modal-head">
            <div>
              <h3 id="employee-calendar-title">Pointages collaborateur</h3>
              <p id="employee-calendar-subtitle" class="subtitle" style="margin:0.2rem 0 0;">Chargement...</p>
            </div>
            <button type="button" id="employee-calendar-close" class="calendar-modal-close" aria-label="Fermer">✕</button>
          </div>

          <div class="calendar-toolbar">
            <div>
              <label for="employee-calendar-period">Période</label>
              <select id="employee-calendar-period">
                <option value="week">Hebdomadaire</option>
                <option value="month">Mensuel</option>
                <option value="custom">Dates déterminées</option>
              </select>
            </div>
            <div>
              <label for="employee-calendar-anchor">Date de référence</label>
              <input id="employee-calendar-anchor" type="date" />
            </div>
            <div id="employee-calendar-from-wrap" style="display:none;">
              <label for="employee-calendar-from">Du</label>
              <input id="employee-calendar-from" type="date" />
            </div>
            <div id="employee-calendar-to-wrap" style="display:none;">
              <label for="employee-calendar-to">Au</label>
              <input id="employee-calendar-to" type="date" />
            </div>
            <div class="actions">
              <button type="button" id="employee-calendar-apply" class="btn-in">Afficher</button>
            </div>
          </div>

          <div id="employee-calendar-summary" class="calendar-summary"></div>
          <div id="employee-calendar-grid"></div>
        </div>
      </section>
    </main>

    <script src="static/js/app.js" defer></script>
  </body>
</html>

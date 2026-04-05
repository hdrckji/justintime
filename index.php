<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Just In Time | Pointage & Gestion des Présences</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #0a0a0a;
      --surface: #141414;
      --surface-2: #1c1c1c;
      --ink: #f5f5f5;
      --ink-soft: #a0a0a0;
      --accent: #e8571a;
      --accent-glow: rgba(232, 87, 26, 0.25);
      --teal: #0d7f89;
      --radius: 16px;
    }

    body {
      font-family: 'Sora', sans-serif;
      background: var(--bg);
      color: var(--ink);
      line-height: 1.6;
      overflow-x: hidden;
    }

    a { color: inherit; text-decoration: none; }
    img { max-width: 100%; height: auto; display: block; }

    /* ── NAV ── */
    .nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      background: rgba(10, 10, 10, 0.85);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .nav-inner {
      max-width: 1200px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.9rem 2rem;
    }
    .nav-logo {
      font-weight: 800; font-size: 1.3rem;
      background: linear-gradient(135deg, var(--accent), #ff8c42);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .nav-links { display: flex; align-items: center; gap: 2rem; }
    .nav-links a { font-size: 0.92rem; color: var(--ink-soft); transition: color 0.2s; }
    .nav-links a:hover { color: #fff; }
    .btn-nav {
      background: var(--accent); color: #fff; padding: 0.55rem 1.4rem;
      border-radius: 8px; font-weight: 600; font-size: 0.9rem;
      transition: filter 0.2s, transform 0.15s; border: none; cursor: pointer;
    }
    .btn-nav:hover { filter: brightness(1.1); transform: translateY(-1px); }

    /* ── HERO ── */
    .hero {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 6rem 2rem 4rem;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 60% 50% at 20% 50%, rgba(232, 87, 26, 0.08), transparent),
        radial-gradient(ellipse 50% 60% at 80% 30%, rgba(13, 127, 137, 0.06), transparent);
      pointer-events: none;
    }
    .hero-grid {
      max-width: 1200px; width: 100%; margin: 0 auto;
      display: grid; grid-template-columns: 1fr 1fr; gap: 4rem;
      align-items: center;
    }
    .hero-text h1 {
      font-size: clamp(2.4rem, 5vw, 3.8rem);
      font-weight: 800; line-height: 1.08;
      letter-spacing: -0.03em;
      margin-bottom: 1.2rem;
    }
    .hero-text h1 span { color: var(--accent); }
    .hero-text p {
      font-size: 1.15rem; color: var(--ink-soft);
      max-width: 48ch; margin-bottom: 2rem;
    }
    .hero-ctas { display: flex; gap: 1rem; flex-wrap: wrap; }
    .btn-primary {
      background: var(--accent); color: #fff; padding: 0.85rem 2rem;
      border-radius: 10px; font-weight: 700; font-size: 1rem;
      border: none; cursor: pointer;
      transition: filter 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 4px 20px var(--accent-glow);
    }
    .btn-primary:hover { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 8px 30px var(--accent-glow); }
    .btn-outline {
      background: transparent; color: var(--ink); padding: 0.85rem 2rem;
      border-radius: 10px; font-weight: 600; font-size: 1rem;
      border: 1px solid rgba(255,255,255,0.15); cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
    }
    .btn-outline:hover { border-color: var(--accent); background: rgba(232,87,26,0.06); }

    .hero-visual {
      position: relative; display: flex; justify-content: center;
    }
    .hero-visual img {
      max-height: 520px; width: auto;
      border-radius: 20px;
      filter: drop-shadow(0 20px 60px rgba(0,0,0,0.5));
    }

    /* ── SECTIONS ── */
    .section {
      padding: 5rem 2rem;
      position: relative;
    }
    .section-inner {
      max-width: 1200px; margin: 0 auto;
    }
    .section-tag {
      display: inline-block; font-size: 0.78rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.1em;
      color: var(--accent); margin-bottom: 0.8rem;
    }
    .section h2 {
      font-size: clamp(1.8rem, 3.5vw, 2.6rem);
      font-weight: 700; line-height: 1.15;
      margin-bottom: 1.2rem;
    }
    .section p.lead {
      font-size: 1.1rem; color: var(--ink-soft);
      max-width: 64ch; margin-bottom: 2rem;
    }

    /* ── CONSTAT (obligation) ── */
    .constat {
      background: var(--surface);
      border-top: 1px solid rgba(255,255,255,0.04);
      border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .constat-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 4rem;
      align-items: center;
    }
    .constat-visual img {
      border-radius: 16px;
      filter: drop-shadow(0 10px 40px rgba(0,0,0,0.4));
    }
    .constat-text ul {
      list-style: none; padding: 0; margin: 1.5rem 0 0;
      display: grid; gap: 1rem;
    }
    .constat-text li {
      display: flex; gap: 0.8rem; align-items: flex-start;
      font-size: 0.95rem; color: var(--ink-soft);
    }
    .constat-text li .icon {
      flex-shrink: 0; width: 28px; height: 28px;
      background: rgba(232, 87, 26, 0.12); color: var(--accent);
      border-radius: 8px; display: flex; align-items: center; justify-content: center;
      font-size: 0.85rem; font-weight: 700;
    }

    /* ── SOLUTION / FEATURES ── */
    .features-grid {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;
      margin-top: 2.5rem;
    }
    .feature-card {
      background: var(--surface);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: var(--radius); padding: 1.8rem;
      transition: border-color 0.3s, transform 0.2s;
    }
    .feature-card:hover { border-color: var(--accent); transform: translateY(-3px); }
    .feature-icon {
      width: 48px; height: 48px; border-radius: 12px;
      background: rgba(232, 87, 26, 0.1); color: var(--accent);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; margin-bottom: 1rem;
    }
    .feature-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.5rem; }
    .feature-card p { font-size: 0.9rem; color: var(--ink-soft); line-height: 1.55; }

    /* ── CTA FINAL ── */
    .cta-section {
      text-align: center;
      padding: 6rem 2rem;
      background:
        radial-gradient(ellipse 70% 50% at 50% 100%, rgba(232, 87, 26, 0.1), transparent),
        var(--bg);
    }
    .cta-section h2 { margin-bottom: 0.8rem; }
    .cta-section p { color: var(--ink-soft); max-width: 52ch; margin: 0 auto 2rem; font-size: 1.05rem; }

    /* ── FOOTER ── */
    .footer {
      border-top: 1px solid rgba(255,255,255,0.06);
      padding: 2rem; text-align: center;
      color: var(--ink-soft); font-size: 0.82rem;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .hero-grid, .constat-grid { grid-template-columns: 1fr; gap: 2.5rem; }
      .hero-visual { order: -1; }
      .features-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
      .nav-links a:not(.btn-nav) { display: none; }
      .hero { padding-top: 5rem; }
      .hero-text h1 { font-size: 2rem; }
    }

    /* ── Mobile menu ── */
    .nav-toggle { display: none; background: none; border: none; color: var(--ink); font-size: 1.5rem; cursor: pointer; padding: 0.3rem; }
    @media (max-width: 600px) {
      .nav-toggle { display: block; }
    }
  </style>
</head>
<body>

<!-- ═══════════ NAVIGATION ═══════════ -->
<nav class="nav">
  <div class="nav-inner">
    <a href="#" class="nav-logo">Just In Time</a>
    <div class="nav-links">
      <a href="#constat">Le constat</a>
      <a href="#solution">Solution</a>
      <a href="#contact">Contact</a>
      <a href="login.php" class="btn-nav">Administration</a>
    </div>
  </div>
</nav>

<!-- ═══════════ HERO ═══════════ -->
<section class="hero" id="top">
  <div class="hero-grid">
    <div class="hero-text">
      <h1>Ne perdez plus<br><span>une minute.</span></h1>
      <p>Just In Time automatise le pointage et la gestion des présences de vos collaborateurs. Simple, légal et prêt pour l'obligation belge de 2027.</p>
      <div class="hero-ctas">
        <a href="#contact" class="btn-primary">Demander une démo</a>
        <a href="#solution" class="btn-outline">Découvrir la solution</a>
      </div>
    </div>
    <div class="hero-visual">
      <img src="static/img/hero-phone.png" alt="Application Just In Time – écran de pointage sur smartphone" />
    </div>
  </div>
</section>

<!-- ═══════════ CONSTAT ═══════════ -->
<section class="section constat" id="constat">
  <div class="section-inner">
    <div class="constat-grid">
      <div class="constat-visual">
        <img src="static/img/hourglass-obligation.png" alt="Le temps presse – obligation légale 2027" />
      </div>
      <div class="constat-text">
        <span class="section-tag">Le constat</span>
        <h2>En Belgique, le pointage<br>devient obligatoire en 2027</h2>
        <p class="lead">La législation belge imposera à toutes les entreprises un système d'enregistrement du temps de travail. Les PME sont en première ligne et doivent s'y préparer dès maintenant.</p>
        <ul>
          <li>
            <div class="icon">⚖</div>
            <div><strong>Obligation légale</strong> – Conformité avec la directive européenne sur le temps de travail, transposée en droit belge.</div>
          </li>
          <li>
            <div class="icon">⏱</div>
            <div><strong>Temps perdu</strong> – Feuilles de calcul Excel, erreurs manuelles, oublis… la gestion artisanale coûte en moyenne 4h/semaine de productivité.</div>
          </li>
          <li>
            <div class="icon">€</div>
            <div><strong>Risque financier</strong> – Sans preuve d'enregistrement, les sanctions peuvent atteindre plusieurs milliers d'euros par infraction.</div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════ SOLUTION ═══════════ -->
<section class="section" id="solution">
  <div class="section-inner">
    <span class="section-tag">La solution</span>
    <h2>Tout ce dont votre PME a besoin,<br>rien de superflu</h2>
    <p class="lead">Just In Time est conçu spécialement pour les PME belges. Pointage RFID et manuel, suivi en temps réel, reporting automatique – le tout dans une interface claire et intuitive.</p>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">📡</div>
        <h3>Pointage RFID</h3>
        <p>Badge NFC/RFID sur borne dédiée. Vos collaborateurs pointent en une seconde, sans intervention humaine.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📱</div>
        <h3>Pointage manuel</h3>
        <p>Interface web accessible depuis n'importe quel appareil. Entrée et sortie en deux clics.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3>Reporting automatique</h3>
        <p>Vue hebdomadaire des heures prestées vs prévues, avec export et alertes d'écart.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📅</div>
        <h3>Gestion des horaires</h3>
        <p>3 modes d'encodage : heures de référence, heures journalières ou heures hebdomadaires. Flexible et précis.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🏖</div>
        <h3>Congés & absences</h3>
        <p>Demandes, approbations et suivi des soldes. Tout est centralisé et traçable.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🤝</div>
        <h3>Compatible secrétariats sociaux</h3>
        <p>Export des données compatible avec les principaux secrétariats sociaux belges : SD Worx, Securex, Partena, Liantis, Acerta, Group S et bien d'autres.</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════ CTA ═══════════ -->
<section class="cta-section" id="contact">
  <div class="section-inner">
    <span class="section-tag">Prêt à commencer ?</span>
    <h2>Mettez votre PME en conformité<br>avant qu'il ne soit trop tard</h2>
    <p>Demandez une démonstration gratuite et découvrez comment Just In Time peut transformer la gestion du temps dans votre entreprise.</p>
    <a href="mailto:contact@justintime.be" class="btn-primary">Contactez-nous</a>
  </div>
</section>

<!-- ═══════════ FOOTER ═══════════ -->
<footer class="footer">
  <p>&copy; 2026 Just In Time — Solution de pointage pour PME belges</p>
</footer>

</body>
</html>

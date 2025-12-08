<?php
// index.php - single-file template using Bootstrap, CSS, JS and a bit of PHP
// To run locally: php -S localhost:8000 and open http://localhost:8000
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ReclaNova — Accueil</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="" crossorigin="anonymous">

  <style>
    :root{
      --nav-height:72px;
      --accent: #5bc0e7;
    }
    html,body{height:100%;}
    body{
      background:#0b1a24; /* page background matching dark frame */
      color:#fff;
      font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
      padding-top: 1rem;
    }

    /* Page framed card look */
    .page-frame{
      max-width: 980px;
      margin: 24px auto;
      background: #0e2733;
      border-radius: 8px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.6);
      overflow: hidden;
      border: 1px solid rgba(255,255,255,0.03);
    }

    /* NAV */
    .site-nav{
      display:flex; align-items:center; justify-content:space-between;
      padding:18px 28px; gap:12px;
    }
    .brand{font-family: 'Georgia', serif; font-weight:700; color:#9fe6ff;}
    .nav-links a{color:#cfeefc; text-decoration:none; margin:0 12px;}
    .nav-links a:hover{opacity:0.85}
    .btn-login{background:linear-gradient(180deg,#2bb4d8,#0e7ea2); border:none; color:white; padding:8px 14px; border-radius:18px}

    /* HERO */
    .hero{
      position:relative;
      min-height:420px;
      padding:48px 40px;
      display:flex;
      align-items:center;
      gap:30px;
    }

    /* Background image layer (where user should place their image) */
    .hero::before{
      content:'';
      position:absolute; inset:0;
      z-index:0;
      background-size:cover;
      background-position:center center;
      /* PUT YOUR BACKGROUND IMAGE PATH BELOW (replace the URL) */
      /* Example: background-image: url('/mnt/data/dbaacdc0-0439-474c-9efb-f47cbcfb27ba.png'); */
      /* ======= PLACE BACKGROUND IMAGE HERE ======= */
      background-image: url('back.jpg');
      /* ========================================= */
      filter: blur(0.6px) saturate(120%);
      opacity:1;
    }

    /* decorative dark overlay and gradient */
    .hero::after{
      content:''; position:absolute; inset:0; z-index:1;
      background: linear-gradient(90deg, rgba(5,14,24,0.72) 0%, rgba(10,26,38,0.35) 50%, rgba(4,8,12,0.6) 100%);
      mix-blend-mode: multiply;
    }

    .hero-content{position:relative; z-index:2; max-width:560px}
    .hero h1{font-size:56px; line-height:0.95; margin:0 0 16px; font-weight:700}
    .hero p.lead{color:rgba(255,255,255,0.85); margin-bottom:22px}

    .cta-row{display:flex; gap:10px; align-items:center}
    .btn-primary-cta{background:linear-gradient(180deg,#5b7bff,#3a3bd6); border:none; padding:10px 18px; border-radius:8px}
    .btn-outline-ghost{background:transparent; border:1px solid rgba(255,255,255,0.12); padding:8px 16px; border-radius:8px}

    /* thin teal separator under hero */
    .accent-sep{height:6px; background:linear-gradient(90deg,#19c6d0,#6b8cff); margin:0 28px}

    /* About section */
    .about{background:#fff; color:#1b2630; padding:34px 40px}
    .about h3{color:#222}
    .feature-cards{display:flex; gap:18px; margin-top:18px}
    .card-feature{flex:1; padding:20px; border-radius:18px; background: #eaf6fb; color:#113; min-height:110px}
    .card-feature.alt{background:#dfe8ff}
    .card-feature.alt2{background:#eadcff}

    /* footer gradient */
    .site-footer{padding:32px 40px; background: linear-gradient(180deg,#5d6de6,#8fb8ff); color:white}
    .footer-columns{display:flex; gap:28px}
    .social-icons a{color:rgba(255,255,255,0.92); font-size:18px; display:inline-block;margin-right:10px}

    /* small screens */
    @media(max-width: 800px){
      .hero{padding:28px 18px; min-height:360px}
      .hero h1{font-size:40px}
      .page-frame{margin:12px}
      .feature-cards{flex-direction:column}
      .site-footer{padding:24px}
    }
  </style>
</head>
<body>

<div class="page-frame">
  <!-- NAV -->
  <div class="site-nav">
    <div class="d-flex align-items-center">
      <div class="brand">Recla<span style="color:#ffffff">Nova</span></div>
    </div>

    <div class="d-flex align-items-center nav-links">
      <a href="#">Accueil</a>
      <a href="#apropos">A propos</a>
      <a href="#contact">contact</a>
    </div>

    <div>
      <!-- simple PHP example: show login if not logged in (placeholder) -->
      <?php $logged_in = false; ?>
      <?php if(!$logged_in): ?>
        <a href="#" class="btn-login">se connecter</a>
      <?php else: ?>
        <a href="#" class="btn-login">Mon compte</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- HERO -->
  <section class="hero">
    <div class="hero-content">
      <h1>Votre voix<br>compte</h1>
      <p class="lead">Centralisez, traitez et suivez vos réclamations plus facilement que jamais.</p>

      <div class="cta-row">
        <button class="btn-primary-cta">créer un compte</button>
        <button class="btn-outline-ghost">En savoir plus</button>
      </div>
    </div>

    <!-- right side accent box (decorative) -->
    <div class="ms-auto d-none d-md-block" style="width:260px; height:160px; border-radius:14px; background:rgba(255,255,255,0.03); box-shadow: inset 0 0 30px rgba(80,120,200,0.06)"></div>
  </section>

  <div class="accent-sep"></div>

  <!-- ABOUT -->
  <section id="apropos" class="about">
    <h3>A propos de Recla Nova</h3>
    <p style="margin-top:8px;">Recla Nova est une plateforme moderne dédiée à la gestion intelligente des réclamations. Elle offre aux organisations un système centralisé, automatisé et entièrement traçable pour améliorer leur efficacité interne et renforcer la satisfaction de leurs usagers.</p>

    <div class="feature-cards">
      <div class="card-feature">
        <strong>les réclamations sont traitées automatiquement</strong>
        <p style="margin-top:8px; font-size:14px;">les règles réduisent les erreurs et le temps de traitement — efficacité maximale.</p>
      </div>

      <div class="card-feature alt">
        <strong>chaque étape de la réclamation est visible et traçable</strong>
        <p style="margin-top:8px; font-size:14px;">permettant un compte-rendu total et une meilleure communication avec le client — transparence totale.</p>
      </div>

      <div class="card-feature alt2">
        <strong>toutes les réclamations sont regroupées</strong>
        <p style="margin-top:8px; font-size:14px;">un espace unique facilite l'organisation et la prise de décision rapide — satisfaction client améliorée.</p>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="d-flex justify-content-between align-items-start">
      <div style="max-width:320px">
        <h4 style="font-family: Georgia, serif; font-weight:700">ReclaNova</h4>
        <p style="margin-top:8px; font-size:14px">Réinventer la gestion des réclamations avec une solution rapide, intelligente et accessible.</p>
      </div>

      <div class="d-flex gap-4">
        <div>
          <h6>liens rapides</h6>
          <ul style="list-style: none; padding-left:0; margin-top:8px">
            <li>Accueil</li>
            <li>A propos</li>
            <li>Contact</li>
          </ul>
        </div>

        <div>
          <h6>nos médias sociaux</h6>
          <div class="social-icons" style="margin-top:8px">
            <a href="#" aria-label="x"><i class="fa-brands fa-x"></i></a>
            <a href="#" aria-label="instagram"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" aria-label="facebook"><i class="fa-brands fa-facebook"></i></a>
          </div>
        </div>
      </div>
    </div>
  </footer>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // small JS helpers if required
  document.querySelectorAll('.btn-primary-cta').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      alert('CTA clicked — connect this to your signup flow');
    })
  })
</script>

</body>
</html>

<section class="hero">
  <div class="container">
    <div>
      <span class="hero-badge">🇸🇦 <?= t('hero.badge') ?></span>
      <h1><?= t('hero.title') ?></h1>
      <p class="lead"><?= t('hero.lead') ?></p>
      <div class="hero-actions">
        <a href="/register" class="btn btn-primary"><?= t('hero.cta_primary') ?></a>
        <a href="/features" class="btn btn-outline"><?= t('hero.cta_secondary') ?></a>
      </div>
    </div>
    <div class="hero-visual">
      <div class="kpi-grid" style="grid-template-columns:1fr 1fr;margin-bottom:0;">
        <div class="kpi"><div class="label">Active Projects</div><div class="value">18</div><div class="delta">+3 this month</div></div>
        <div class="kpi"><div class="label">Est. Accuracy</div><div class="value">96%</div><div class="delta">+4% vs manual</div></div>
        <div class="kpi"><div class="label">Outstanding</div><div class="value">SAR 128K</div></div>
        <div class="kpi"><div class="label">Paid this month</div><div class="value">SAR 412K</div></div>
      </div>
      <div class="stat-row">
        <div><strong>2,400+</strong><span><?= t('hero.stat1') ?></span></div>
        <div><strong>15,000+</strong><span><?= t('hero.stat2') ?></span></div>
        <div><strong>6 hrs/wk</strong><span><?= t('hero.stat3') ?></span></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= t('features.eyebrow') ?></div>
      <h2><?= t('features.title') ?></h2>
      <p style="color:var(--muted)"><?= t('features.subtitle') ?></p>
    </div>
    <div class="grid grid-3">
      <div class="card feature-card"><div class="icon">🧮</div><h3><?= t('features.estimating.title') ?></h3><p><?= t('features.estimating.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">📈</div><h3><?= t('features.jobcosting.title') ?></h3><p><?= t('features.jobcosting.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">📅</div><h3><?= t('features.scheduling.title') ?></h3><p><?= t('features.scheduling.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">💳</div><h3><?= t('features.invoicing.title') ?></h3><p><?= t('features.invoicing.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">👥</div><h3><?= t('features.clients.title') ?></h3><p><?= t('features.clients.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">🤝</div><h3><?= t('features.team.title') ?></h3><p><?= t('features.team.desc') ?></p></div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container" style="text-align:center;">
    <h2><?= t('cta.title') ?></h2>
    <p style="color:var(--muted)"><?= t('cta.subtitle') ?></p>
    <a href="/register" class="btn btn-primary" style="margin-top:10px;"><?= t('cta.button') ?></a>
  </div>
</section>

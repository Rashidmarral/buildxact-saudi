<?php
use App\Core\View;
$featured = 'professional';
?>
<section class="section" style="padding-top:56px;">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= t('pricing.eyebrow') ?></div>
      <h1><?= t('pricing.title') ?></h1>
      <p style="color:var(--muted)"><?= t('pricing.subtitle') ?></p>
    </div>

    <div class="grid grid-3">
      <?php foreach ($plans as $plan): $features = json_decode($plan['features'], true) ?: []; ?>
        <div class="card pricing-card <?= $plan['slug'] === $featured ? 'featured' : '' ?>">
          <?php if ($plan['slug'] === $featured): ?><span class="badge-featured"><?= t('pricing.most_popular') ?></span><?php endif; ?>
          <h3><?= View::e($plan['name']) ?></h3>
          <p style="color:var(--muted);font-size:13.5px;min-height:36px;"><?= View::e($plan['tagline']) ?></p>
          <div class="price"><?= number_format((float)$plan['price_monthly'], 0) ?> <small>SAR <?= t('pricing.per_month') ?></small></div>
          <p class="help-text"><?= number_format((float)$plan['price_yearly'], 0) ?> SAR <?= t('pricing.per_year') ?></p>
          <p class="help-text">
            <?= $plan['max_users'] >= 999 ? '∞' : $plan['max_users'] ?> <?= t('pricing.users') ?> ·
            <?= $plan['max_projects'] >= 999 ? '∞' : $plan['max_projects'] ?> <?= t('pricing.projects') ?>
          </p>
          <ul class="plan-features">
            <?php foreach ($features as $f): ?><li><?= View::e($f) ?></li><?php endforeach; ?>
          </ul>
          <a href="/register?plan=<?= urlencode($plan['slug']) ?>" class="btn <?= $plan['slug'] === $featured ? 'btn-primary' : 'btn-outline' ?> btn-block"><?= t('pricing.cta') ?></a>
        </div>
      <?php endforeach; ?>
    </div>

    <p style="text-align:center;color:var(--muted);margin-top:36px;font-size:13.5px;">
      All plans include a 14-day free trial. Prices exclude 15% Saudi VAT. Need a custom plan for a large enterprise? <a href="/contact">Talk to sales</a>.
    </p>
  </div>
</section>

<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Billing & Subscription</h1>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3>Current plan</h3>
  <?php if ($currentPlan): ?>
    <p style="font-size:20px;font-weight:800;color:var(--brand-dark);"><?= View::e($currentPlan['name']) ?></p>
    <p class="help-text">
      Status: <span class="badge badge-<?= $company['status']==='active'?'green':'yellow' ?>"><?= View::e($company['status']) ?></span>
      <?php if ($subscription): ?> · Billing cycle: <?= View::e(ucfirst($subscription['billing_cycle'])) ?> · Renews: <?= View::e($subscription['current_period_end']) ?><?php endif; ?>
    </p>
  <?php else: ?>
    <p class="help-text">No active plan.</p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3>Change plan</h3>
  <div class="grid grid-3">
    <?php foreach ($plans as $plan): $features = json_decode($plan['features'], true) ?: []; ?>
      <div class="pricing-card card <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'featured' : '' ?>">
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?><span class="badge-featured">Current</span><?php endif; ?>
        <h3><?= View::e($plan['name']) ?></h3>
        <div class="price"><?= number_format((float)$plan['price_monthly'],0) ?> <small>SAR/mo</small></div>
        <ul class="plan-features"><?php foreach ($features as $f): ?><li><?= View::e($f) ?></li><?php endforeach; ?></ul>
        <form method="post" action="/app/billing/upgrade">
          <?= Csrf::field() ?>
          <input type="hidden" name="plan" value="<?= View::e($plan['slug']) ?>">
          <input type="hidden" name="cycle" value="monthly">
          <button type="submit" class="btn <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'btn-light' : 'btn-primary' ?> btn-block" <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'disabled' : '' ?>>
            <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'Current plan' : 'Switch to this plan' ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="help-text" style="margin-top:16px;">Payments are processed via mada, Visa/Mastercard, or Apple Pay through our Saudi payment gateway partner. This demo simulates a successful charge.</p>
</div>

<div class="card">
  <h3>Payment history</h3>
  <?php if (empty($payments)): ?>
    <p class="help-text">No payments yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= View::e($p['created_at']) ?></td>
          <td><?= View::e($p['reference']) ?></td>
          <td><?= View::e(strtoupper($p['method'])) ?></td>
          <td><?= View::money((float)$p['amount']) ?></td>
          <td><span class="badge badge-green"><?= View::e($p['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Platform Overview</h1>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">MRR</div><div class="value"><?= View::money($mrr) ?></div></div>
  <div class="kpi"><div class="label">Revenue This Month</div><div class="value"><?= View::money($revenueThisMonth) ?></div></div>
  <div class="kpi"><div class="label">Total Companies</div><div class="value"><?= $totalCompanies ?></div><div class="delta">+<?= $signupsThisMonth ?> this month</div></div>
  <div class="kpi"><div class="label">Active / Trial</div><div class="value"><?= $activeCompanies ?> / <?= $trialCompanies ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Recent Companies</h3>
    <?php if (empty($recentCompanies)): ?>
      <p class="help-text">No companies yet.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Company</th><th>Plan</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentCompanies as $c): ?>
          <tr>
            <td><a href="/admin/companies/<?= $c['id'] ?>"><?= View::e($c['name']) ?></a></td>
            <td><?= View::e($c['plan_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $c['status']==='active'?'green':($c['status']==='suspended'?'red':'yellow') ?>"><?= View::e($c['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Companies by Plan</h3>
    <table class="data">
      <thead><tr><th>Plan</th><th>Companies</th></tr></thead>
      <tbody>
      <?php foreach ($planCounts as $pc): ?>
        <tr><td><?= View::e($pc['name']) ?></td><td><?= (int)$pc['company_count'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

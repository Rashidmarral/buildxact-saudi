<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($company['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= View::e($company['email']) ?> · <?= View::e($company['city']) ?></p>
  </div>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/status" style="display:flex;gap:8px;">
    <?= Csrf::field() ?>
    <select name="status">
      <?php foreach (['trial'=>'Trial','active'=>'Active','suspended'=>'Suspended','cancelled'=>'Cancelled'] as $val=>$label): ?>
        <option value="<?= $val ?>" <?= $company['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Update status</button>
  </form>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-<?= $company['status']==='active'?'green':($company['status']==='suspended'?'red':'yellow') ?>"><?= View::e($company['status']) ?></span></div></div>
  <div class="kpi"><div class="label">Team members</div><div class="value"><?= count($users) ?></div></div>
  <div class="kpi"><div class="label">Projects</div><div class="value"><?= $projectCount ?></div></div>
  <div class="kpi"><div class="label">Trial ends</div><div class="value" style="font-size:16px;"><?= View::e($company['trial_ends_at'] ?: '—') ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Team members</h3>
    <table class="data">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><?= View::e($u['name']) ?></td><td><?= View::e($u['email']) ?></td><td><span class="badge badge-blue"><?= View::e(ucfirst($u['role'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3>Subscription history</h3>
    <table class="data">
      <thead><tr><th>Plan</th><th>Cycle</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($subscriptions as $s): ?>
        <tr><td><?= View::e($s['plan_name']) ?></td><td><?= View::e($s['billing_cycle']) ?></td><td><span class="badge badge-gray"><?= View::e($s['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Payments</h3>
  <?php if (empty($payments)): ?>
    <p class="help-text">No payments recorded.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr><td><?= View::e($p['created_at']) ?></td><td><?= View::e($p['reference']) ?></td><td><?= View::money((float)$p['amount']) ?></td><td><span class="badge badge-green"><?= View::e($p['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

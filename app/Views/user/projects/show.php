<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($project['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;">Client: <?= View::e($client['name'] ?? '—') ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app/projects/<?= $project['id'] ?>/edit" class="btn btn-light">Edit</a>
    <form method="post" action="/app/projects/<?= $project['id'] ?>/delete" onsubmit="return confirm('Delete this project?');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-danger">Delete</button>
    </form>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$project['status'])) ?></span></div></div>
  <div class="kpi"><div class="label">Budget</div><div class="value"><?= View::money((float)$project['budget']) ?></div></div>
  <div class="kpi"><div class="label">Start</div><div class="value" style="font-size:16px;"><?= View::e($project['start_date'] ?: '—') ?></div></div>
  <div class="kpi"><div class="label">End</div><div class="value" style="font-size:16px;"><?= View::e($project['end_date'] ?: '—') ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Estimates</h3>
    <?php if (empty($estimates)): ?><p class="help-text">No estimates linked to this project.</p><?php else: ?>
      <table class="data"><thead><tr><th>Title</th><th>Status</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($estimates as $e): ?>
        <tr><td><a href="/app/estimates/<?= $e['id'] ?>"><?= View::e($e['title']) ?></a></td><td><span class="badge badge-gray"><?= View::e($e['status']) ?></span></td><td><?= View::money((float)$e['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>Invoices</h3>
    <?php if (empty($invoices)): ?><p class="help-text">No invoices linked to this project.</p><?php else: ?>
      <table class="data"><thead><tr><th>#</th><th>Status</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr><td><a href="/app/invoices/<?= $inv['id'] ?>"><?= View::e($inv['invoice_number']) ?></a></td><td><span class="badge badge-<?= $inv['status']==='paid'?'green':'yellow' ?>"><?= View::e($inv['status']) ?></span></td><td><?= View::money((float)$inv['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Schedule</h3>
  <?php if (empty($tasks)): ?><p class="help-text">No scheduled tasks for this project yet. Add some from the <a href="/app/schedule">Schedule</a> page.</p><?php else: ?>
    <table class="data"><thead><tr><th>Task</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($tasks as $tk): ?>
      <tr><td><?= View::e($tk['title']) ?></td><td><?= View::e($tk['start_date']) ?></td><td><?= View::e($tk['end_date']) ?></td><td><span class="badge badge-gray"><?= View::e(str_replace('_',' ',$tk['status'])) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

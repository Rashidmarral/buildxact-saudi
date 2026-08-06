<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($estimate['title']) ?></h1>
    <p class="help-text" style="margin-top:4px;">Client: <?= View::e($client['name'] ?? '—') ?><?php if ($project): ?> · Project: <a href="/app/projects/<?= $project['id'] ?>"><?= View::e($project['name']) ?></a><?php endif; ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$estimate['status']] ?? 'gray' ?>" style="font-size:13px;padding:6px 14px;"><?= View::e($estimate['status']) ?></span>
    <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/delete" onsubmit="return confirm('Delete this estimate?');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-danger">Delete</button>
    </form>
  </div>
</div>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit cost</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= View::e($it['description']) ?></td><td><?= View::e($it['qty']) ?></td><td><?= View::money((float)$it['unit_cost']) ?></td><td><?= View::money((float)$it['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total-row" style="margin-top:14px;">Total: <?= View::money((float)$estimate['total']) ?></div>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3>Update status</h3>
  <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/status" style="display:flex;gap:10px;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <select name="status">
        <?php foreach (['draft'=>'Draft','sent'=>'Sent to client','accepted'=>'Accepted','declined'=>'Declined'] as $val=>$label): ?>
          <option value="<?= $val ?>" <?= $estimate['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
  </form>
</div>

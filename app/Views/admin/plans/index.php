<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Subscription Plans</h1>
  <a href="/admin/plans/create" class="btn btn-primary">+ New Plan</a>
</div>

<table class="data">
  <thead><tr><th>Name</th><th>Monthly</th><th>Yearly</th><th>Limits</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($plans as $p): ?>
    <tr>
      <td><?= View::e($p['name']) ?> <span class="help-text">(<?= View::e($p['slug']) ?>)</span></td>
      <td><?= View::money((float)$p['price_monthly']) ?></td>
      <td><?= View::money((float)$p['price_yearly']) ?></td>
      <td class="help-text"><?= $p['max_users'] ?> users · <?= $p['max_projects'] ?> projects</td>
      <td><span class="badge badge-<?= $p['is_active'] ? 'green' : 'gray' ?>"><?= $p['is_active'] ? 'Active' : 'Hidden' ?></span></td>
      <td style="display:flex;gap:8px;">
        <a href="/admin/plans/<?= $p['id'] ?>/edit" class="btn btn-sm btn-light">Edit</a>
        <form method="post" action="/admin/plans/<?= $p['id'] ?>/delete" onsubmit="return confirm('Delete this plan?');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

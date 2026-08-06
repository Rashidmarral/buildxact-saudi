<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Companies</h1>
</div>

<?php if (empty($companies)): ?>
  <div class="card empty-state"><div class="icon">🏢</div><h3>No companies yet</h3></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Company</th><th>Email</th><th>Plan</th><th>Status</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($companies as $c): ?>
      <tr>
        <td><a href="/admin/companies/<?= $c['id'] ?>"><?= View::e($c['name']) ?></a></td>
        <td><?= View::e($c['email']) ?></td>
        <td><?= View::e($c['plan_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $c['status']==='active'?'green':($c['status']==='suspended'?'red':'yellow') ?>"><?= View::e($c['status']) ?></span></td>
        <td class="help-text"><?= View::e($c['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

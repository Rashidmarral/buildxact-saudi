<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Estimates</h1>
  <a href="/app/estimates/create" class="btn btn-primary">+ New Estimate</a>
</div>

<?php if (empty($estimates)): ?>
  <div class="card empty-state">
    <div class="icon">🧾</div>
    <h3>No estimates yet</h3>
    <p>Build a detailed estimate and convert it into a project once accepted.</p>
    <a href="/app/estimates/create" class="btn btn-primary">+ New Estimate</a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Title</th><th>Client</th><th>Status</th><th>Total</th></tr></thead>
    <tbody>
    <?php foreach ($estimates as $e): ?>
      <tr>
        <td><a href="/app/estimates/<?= $e['id'] ?>"><?= View::e($e['title']) ?></a></td>
        <td><?= View::e($e['client_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$e['status']] ?? 'gray' ?>"><?= View::e($e['status']) ?></span></td>
        <td><?= View::money((float)$e['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

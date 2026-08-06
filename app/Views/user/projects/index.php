<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Projects</h1>
  <a href="/app/projects/create" class="btn btn-primary">+ New Project</a>
</div>

<?php if (empty($projects)): ?>
  <div class="card empty-state">
    <div class="icon">🏗️</div>
    <h3>No projects yet</h3>
    <p>Create your first project to start tracking budget, schedule, and invoices.</p>
    <a href="/app/projects/create" class="btn btn-primary">+ New Project</a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Project</th><th>Client</th><th>Status</th><th>Budget</th><th>Dates</th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
      <tr>
        <td><a href="/app/projects/<?= $p['id'] ?>"><?= View::e($p['name']) ?></a></td>
        <td><?= View::e($p['client_name'] ?? '—') ?></td>
        <td><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$p['status'])) ?></span></td>
        <td><?= View::money((float)$p['budget']) ?></td>
        <td class="help-text"><?= View::e($p['start_date']) ?> → <?= View::e($p['end_date']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

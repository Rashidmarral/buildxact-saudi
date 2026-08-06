<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Clients</h1>
  <a href="/app/clients/create" class="btn btn-primary">+ New Client</a>
</div>

<?php if (empty($clients)): ?>
  <div class="card empty-state">
    <div class="icon">👥</div>
    <h3>No clients yet</h3>
    <p>Add clients to link them to projects, estimates, and invoices.</p>
    <a href="/app/clients/create" class="btn btn-primary">+ New Client</a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr>
        <td><?= View::e($c['name']) ?></td>
        <td><?= View::e($c['email']) ?></td>
        <td><?= View::e($c['phone']) ?></td>
        <td class="help-text"><?= View::e($c['address']) ?></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/clients/<?= $c['id'] ?>/edit" class="btn btn-sm btn-light">Edit</a>
          <form method="post" action="/app/clients/<?= $c['id'] ?>/delete" onsubmit="return confirm('Remove this client?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

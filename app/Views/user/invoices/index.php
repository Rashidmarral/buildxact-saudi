<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Invoices</h1>
  <a href="/app/invoices/create" class="btn btn-primary">+ New Invoice</a>
</div>

<?php if (empty($invoices)): ?>
  <div class="card empty-state">
    <div class="icon">💳</div>
    <h3>No invoices yet</h3>
    <p>Create an invoice and track payments through to completion.</p>
    <a href="/app/invoices/create" class="btn btn-primary">+ New Invoice</a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>#</th><th>Client</th><th>Status</th><th>Total</th><th>Due</th></tr></thead>
    <tbody>
    <?php foreach ($invoices as $i): ?>
      <tr>
        <td><a href="/app/invoices/<?= $i['id'] ?>"><?= View::e($i['invoice_number']) ?></a></td>
        <td><?= View::e($i['client_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$i['status']] ?? 'yellow' ?>"><?= View::e($i['status']) ?></span></td>
        <td><?= View::money((float)$i['total']) ?></td>
        <td class="help-text"><?= View::e($i['due_date']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

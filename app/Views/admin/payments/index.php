<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Payments</h1>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(1,220px);">
  <div class="kpi"><div class="label">Total collected</div><div class="value"><?= View::money($total) ?></div></div>
</div>

<?php if (empty($payments)): ?>
  <div class="card empty-state"><div class="icon">💵</div><h3>No payments yet</h3></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Date</th><th>Company</th><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= View::e($p['created_at']) ?></td>
        <td><a href="/admin/companies/<?= $p['company_id'] ?>"><?= View::e($p['company_name']) ?></a></td>
        <td><?= View::e($p['reference']) ?></td>
        <td><?= View::e(strtoupper($p['method'])) ?></td>
        <td><?= View::money((float)$p['amount']) ?></td>
        <td><span class="badge badge-green"><?= View::e($p['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

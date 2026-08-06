<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>New Estimate</h1>
  <a href="/app/estimates" class="btn btn-light">← Back to estimates</a>
</div>

<form method="post" action="/app/estimates" class="card" style="max-width:820px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Estimate title</label><input type="text" name="title" required placeholder="e.g. Villa Renovation Estimate"></div>
    <div class="form-group">
      <label>Client</label>
      <select name="client_id">
        <option value="">— No client —</option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group">
    <label>Link to project (optional)</label>
    <select name="project_id">
      <option value="">— None —</option>
      <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>

  <label>Line items</label>
  <table class="line-items" id="items-table">
    <thead><tr><th style="width:50%">Description</th><th>Qty</th><th>Unit cost (SAR)</th><th>Line total</th><th></th></tr></thead>
    <tbody id="items-body">
      <tr>
        <td><input type="text" name="item_description[]" placeholder="e.g. Demolition & site prep"></td>
        <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
        <td><input type="number" step="0.01" name="item_cost[]" value="0" class="cost"></td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
    </tbody>
  </table>
  <button type="button" id="add-row" class="btn btn-sm btn-outline">+ Add line item</button>
  <div class="total-row">Total: <span id="grand-total">0.00</span> SAR</div>

  <button type="submit" class="btn btn-primary" style="margin-top:16px;">Create estimate</button>
</form>

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="item_description[]" placeholder="Description"></td>
      <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
      <td><input type="number" step="0.01" name="item_cost[]" value="0" class="cost"></td>
      <td class="line-total">0.00</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  function recalc() {
    let total = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost').value) || 0;
      const lineTotal = qty * cost;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      total += lineTotal;
    });
    grandTotal.textContent = total.toFixed(2);
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
      recalc();
    }
  });
  recalc();
})();
</script>

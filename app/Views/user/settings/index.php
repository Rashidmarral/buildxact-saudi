<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Settings</h1>
</div>

<form method="post" action="/app/settings" class="card" style="max-width:640px;">
  <?= Csrf::field() ?>
  <div class="form-group"><label>Company name</label><input type="text" name="name" value="<?= View::e($company['name']) ?>" <?= Auth::isCompanyOwner() ? '' : 'disabled' ?>></div>
  <div class="form-row">
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= View::e($company['phone']) ?>" <?= Auth::isCompanyOwner() ? '' : 'disabled' ?>></div>
    <div class="form-group"><label>City</label><input type="text" name="city" value="<?= View::e($company['city']) ?>" <?= Auth::isCompanyOwner() ? '' : 'disabled' ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>CR number</label><input type="text" name="cr_number" value="<?= View::e($company['cr_number']) ?>" <?= Auth::isCompanyOwner() ? '' : 'disabled' ?>></div>
    <div class="form-group"><label>VAT number</label><input type="text" name="vat_number" value="<?= View::e($company['vat_number']) ?>" <?= Auth::isCompanyOwner() ? '' : 'disabled' ?>></div>
  </div>
  <?php if (Auth::isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary">Save changes</button>
  <?php else: ?>
    <p class="help-text">Only the company owner can edit these settings.</p>
  <?php endif; ?>
</form>

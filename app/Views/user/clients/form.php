<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= $client ? 'Edit Client' : 'New Client' ?></h1>
  <a href="/app/clients" class="btn btn-light">← Back to clients</a>
</div>

<form method="post" action="<?= $client ? '/app/clients/' . $client['id'] : '/app/clients' ?>" class="card" style="max-width:600px;">
  <?= Csrf::field() ?>
  <div class="form-group"><label>Name</label><input type="text" name="name" required value="<?= View::e($client['name'] ?? '') ?>"></div>
  <div class="form-row">
    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= View::e($client['email'] ?? '') ?>"></div>
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx" value="<?= View::e($client['phone'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label>Address</label><textarea name="address"><?= View::e($client['address'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $client ? 'Save changes' : 'Add client' ?></button>
</form>

<?php use App\Core\View; use App\Core\Csrf; $features = $plan ? implode("\n", json_decode($plan['features'], true) ?: []) : ''; ?>
<div class="page-head">
  <h1><?= $plan ? 'Edit Plan' : 'New Plan' ?></h1>
  <a href="/admin/plans" class="btn btn-light">← Back to plans</a>
</div>

<form method="post" action="<?= $plan ? '/admin/plans/' . $plan['id'] : '/admin/plans' ?>" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Plan name</label><input type="text" name="name" required value="<?= View::e($plan['name'] ?? '') ?>"></div>
    <div class="form-group"><label>Slug</label><input type="text" name="slug" required value="<?= View::e($plan['slug'] ?? '') ?>" placeholder="e.g. professional"></div>
  </div>
  <div class="form-group"><label>Tagline</label><input type="text" name="tagline" value="<?= View::e($plan['tagline'] ?? '') ?>"></div>
  <div class="form-row">
    <div class="form-group"><label>Price / month (SAR)</label><input type="number" step="0.01" name="price_monthly" value="<?= View::e((string)($plan['price_monthly'] ?? 0)) ?>"></div>
    <div class="form-group"><label>Price / year (SAR)</label><input type="number" step="0.01" name="price_yearly" value="<?= View::e((string)($plan['price_yearly'] ?? 0)) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Max users</label><input type="number" name="max_users" value="<?= View::e((string)($plan['max_users'] ?? 5)) ?>"></div>
    <div class="form-group"><label>Max projects</label><input type="number" name="max_projects" value="<?= View::e((string)($plan['max_projects'] ?? 10)) ?>"></div>
  </div>
  <div class="form-group">
    <label>Features (one per line)</label>
    <textarea name="features" rows="6"><?= View::e($features) ?></textarea>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="<?= View::e((string)($plan['sort_order'] ?? 0)) ?>"></div>
    <div class="form-group">
      <label>Visible on pricing page</label>
      <select name="is_active">
        <option value="1" <?= (!$plan || $plan['is_active']) ? 'selected' : '' ?>>Yes</option>
        <option value="0" <?= ($plan && !$plan['is_active']) ? 'selected' : '' ?>>No</option>
      </select>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><?= $plan ? 'Save changes' : 'Create plan' ?></button>
</form>

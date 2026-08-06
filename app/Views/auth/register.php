<?php use App\Core\Csrf; use App\Core\View; ?>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:560px;">
    <h2><?= t('auth.register_title') ?></h2>
    <p style="color:var(--muted);margin-bottom:20px;"><?= t('auth.register_sub') ?></p>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <form method="post" action="/register">
      <?= Csrf::field() ?>
      <div class="form-row">
        <div class="form-group"><label><?= t('auth.company_name') ?></label><input type="text" name="company_name" required value="<?= View::old('company_name') ?>"></div>
        <div class="form-group"><label><?= t('auth.your_name') ?></label><input type="text" name="name" required value="<?= View::old('name') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label><?= t('auth.email') ?></label><input type="email" name="email" required value="<?= View::old('email') ?>"></div>
        <div class="form-group"><label><?= t('auth.phone') ?></label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx" value="<?= View::old('phone') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label><?= t('auth.city') ?></label><input type="text" name="city" value="<?= View::old('city') ?>" placeholder="Riyadh"></div>
        <div class="form-group"><label><?= t('auth.password') ?></label><input type="password" name="password" required minlength="8"></div>
      </div>

      <div class="form-group">
        <label><?= t('auth.choose_plan') ?></label>
        <div class="auth-plans">
          <?php foreach ($plans as $plan): ?>
            <label class="plan-pick <?= $plan['slug'] === $selected ? 'active' : '' ?>">
              <input type="radio" name="plan" value="<?= View::e($plan['slug']) ?>" <?= $plan['slug'] === $selected ? 'checked' : '' ?>>
              <div class="name"><?= View::e($plan['name']) ?></div>
              <div class="p"><?= number_format((float)$plan['price_monthly'], 0) ?> SAR/mo</div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block"><?= t('auth.register_btn') ?></button>
    </form>
    <p style="margin-top:16px;font-size:14px;"><?= t('auth.have_account') ?> <a href="/login"><?= t('nav.login') ?></a></p>
  </div>
</div>
<script>
document.querySelectorAll('.plan-pick').forEach(el => el.addEventListener('click', () => {
  document.querySelectorAll('.plan-pick').forEach(x => x.classList.remove('active'));
  el.classList.add('active');
}));
</script>

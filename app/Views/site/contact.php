<?php use App\Core\Csrf; ?>
<section class="section" style="padding-top:56px;">
  <div class="container" style="max-width:640px;">
    <div class="eyebrow"><?= t('nav.contact') ?></div>
    <h1>Talk to our sales team</h1>
    <p style="color:var(--muted);">Have questions about pricing, a custom enterprise plan, or migrating your existing data? Send us a message and we'll get back to you within one business day.</p>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= \App\Core\View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <form method="post" action="/contact" class="card">
      <?= Csrf::field() ?>
      <div class="form-row">
        <div class="form-group"><label>Full name</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Company</label><input type="text" name="company"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx"></div>
      </div>
      <div class="form-group"><label>Message</label><textarea name="message" required></textarea></div>
      <button type="submit" class="btn btn-primary btn-block">Send message</button>
    </form>
  </div>
</section>

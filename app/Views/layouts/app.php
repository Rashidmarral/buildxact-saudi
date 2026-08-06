<?php

use App\Core\Lang;
use App\Core\Auth;

$user = Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isActive = fn(string $p) => str_starts_with($path, $p) ? 'active' : '';
$otherLang = Lang::locale() === 'ar' ? 'en' : 'ar';
$otherLangLabel = Lang::locale() === 'ar' ? 'EN' : 'AR';
?><!doctype html>
<html lang="<?= Lang::locale() ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? \App\Core\View::e($pageTitle) . ' · ' : '' ?>BuildXact Saudi</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><span class="mark" style="background:#fff;color:var(--brand-dark)">BX</span> BuildXact</div>
    <nav>
      <a href="/app" class="<?= $isActive('/app') === 'active' && $path === '/app' ? 'active' : '' ?>">📊 <?= t('side.dashboard') ?></a>
      <a href="/app/projects" class="<?= $isActive('/app/projects') ?>">🏗️ <?= t('side.projects') ?></a>
      <a href="/app/estimates" class="<?= $isActive('/app/estimates') ?>">🧾 <?= t('side.estimates') ?></a>
      <a href="/app/invoices" class="<?= $isActive('/app/invoices') ?>">💳 <?= t('side.invoices') ?></a>
      <a href="/app/clients" class="<?= $isActive('/app/clients') ?>">👥 <?= t('side.clients') ?></a>
      <a href="/app/schedule" class="<?= $isActive('/app/schedule') ?>">📅 <?= t('side.schedule') ?></a>
      <a href="/app/team" class="<?= $isActive('/app/team') ?>">🧑‍💼 <?= t('side.team') ?></a>
      <a href="/app/billing" class="<?= $isActive('/app/billing') ?>">💰 <?= t('side.billing') ?></a>
      <a href="/app/settings" class="<?= $isActive('/app/settings') ?>">⚙️ <?= t('side.settings') ?></a>
    </nav>
    <div class="foot">
      <a href="/" style="color:#a9c4bd">← <?= t('side.back_site') ?></a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="who"><?= \App\Core\View::e($user['name'] ?? '') ?> · <span class="badge badge-blue"><?= \App\Core\View::e(ucfirst($user['role'] ?? '')) ?></span></div>
      <div class="header-actions">
        <a class="lang-switch" href="?lang=<?= $otherLang ?>"><?= $otherLangLabel ?></a>
        <form method="post" action="/logout" style="margin:0">
          <?= \App\Core\Csrf::field() ?>
          <button type="submit" class="btn btn-light btn-sm"><?= t('side.logout') ?></button>
        </form>
      </div>
    </div>
    <div class="content">
      <?php if (!empty($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $type => $messages): ?>
          <?php foreach ($messages as $m): ?>
            <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= \App\Core\View::e($m) ?></div>
          <?php endforeach; ?>
        <?php endforeach; unset($_SESSION['flash']); endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
</body>
</html>

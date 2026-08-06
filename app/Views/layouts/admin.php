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
<title><?= isset($pageTitle) ? \App\Core\View::e($pageTitle) . ' · ' : '' ?>Admin · BuildXact Saudi</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" style="background:#151f1e">
    <div class="brand"><span class="mark" style="background:#fff;color:#151f1e">BX</span> Platform Admin</div>
    <nav>
      <a href="/admin" class="<?= $path === '/admin' ? 'active' : '' ?>">📊 <?= t('aside.dashboard') ?></a>
      <a href="/admin/companies" class="<?= $isActive('/admin/companies') ?>">🏢 <?= t('aside.companies') ?></a>
      <a href="/admin/plans" class="<?= $isActive('/admin/plans') ?>">📦 <?= t('aside.plans') ?></a>
      <a href="/admin/payments" class="<?= $isActive('/admin/payments') ?>">💵 <?= t('aside.payments') ?></a>
      <a href="/admin/admins" class="<?= $isActive('/admin/admins') ?>">🛡️ <?= t('aside.admins') ?></a>
    </nav>
    <div class="foot">
      <a href="/" style="color:#a9c4bd">← <?= t('side.back_site') ?></a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="who"><?= \App\Core\View::e($user['name'] ?? '') ?> · <span class="badge badge-gray">Super Admin</span></div>
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

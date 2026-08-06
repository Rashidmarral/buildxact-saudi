<?php

use App\Core\Lang;

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
<header class="site-header">
  <div class="container">
    <a href="/" class="logo"><span class="mark">BX</span> BuildXact <span style="color:#d4a017">السعودية</span></a>
    <div class="header-actions">
      <a class="lang-switch" href="?lang=<?= $otherLang ?>"><?= $otherLangLabel ?></a>
    </div>
  </div>
</header>
<?= $content ?>
</body>
</html>

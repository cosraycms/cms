<!DOCTYPE html>
<html lang="<?= $locale->id ?? 'en' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $node->title ?></title>
</head>
<body>
    <h1><?= $node->title() ?></h1>
    <?php if (isset($node->content)): ?>
        <div class="content">
            <?= $node->content ?>
        </div>
    <?php endif; ?>
</body>
</html>

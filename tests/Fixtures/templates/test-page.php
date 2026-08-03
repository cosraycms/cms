<!DOCTYPE html>
<html lang="<?= $locale->id ?? 'en' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($node->title()); ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($node->title()); ?></h1>
    <?php if (isset($node->content)): ?>
        <div class="content">
            <?= $node->content ?>
        </div>
    <?php endif; ?>
</body>
</html>

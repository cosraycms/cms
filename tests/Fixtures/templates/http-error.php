<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $exception->title(); ?></title>
</head>
<body>
    <h1><?= $exception->title(); ?></h1>
    <?php if ($debug && $exception->getMessage()): ?>
        <p><?= $exception->getMessage() ?></p>
    <?php endif; ?>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 Internal Server Error</title>
</head>
<body>
    <h1>500 Internal Server Error</h1>
    <?php if ($debug && $exception->getMessage()): ?>
        <p><?= $exception->getMessage() ?></p>
        <pre><?= $exception->getTraceAsString() ?></pre>
    <?php endif; ?>
</body>
</html>

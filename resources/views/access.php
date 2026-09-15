<!doctype html>
<html lang="<?= $locale ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= __('Protected content') ?></title>
</head>
<body>
	<main>
		<h1><?= __('Protected content') ?></h1>
		<?php if ($message !== null): ?>
			<p role="alert"><?= $message ?></p>
		<?php endif ?>
		<form action="<?= $action ?>" method="post">
			<input type="hidden" name="_token" value="<?= $token ?>">
			<input type="hidden" name="next" value="<?= $next ?>">
			<label for="password"><?= __('Password') ?></label>
			<input id="password" name="password" type="password" autocomplete="current-password" required>
			<button type="submit"><?= __('Unlock') ?></button>
		</form>
	</main>
</body>
</html>

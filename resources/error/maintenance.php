<?php

$texts = [
	'en' => [
		'heading' => "We'll be right back",
		'full' => 'This site is being updated and is unavailable for a moment.',
		'writes' => 'This site is being updated. Changes cannot be saved right now, please try again in a moment.',
		'reload' => 'This page reloads by itself.',
	],
	'de' => [
		'heading' => 'Gleich wieder da',
		'full' => 'Die Website wird gerade aktualisiert und ist kurz nicht erreichbar.',
		'writes' => 'Die Website wird gerade aktualisiert. Änderungen lassen sich im Moment nicht speichern, bitte versuchen Sie es gleich noch einmal.',
		'reload' => 'Diese Seite lädt sich von selbst neu.',
	],
][(string) $lang === 'de' ? 'de' : 'en'];

?><!doctype html>
<html lang="<?= $lang ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<meta http-equiv="refresh" content="<?= $retry ?>">
	<title><?= $title ?></title>
	<style>
		:root { color-scheme: light dark; --text: #1f2328; --muted: #59636e; --bg: #f6f7f9; --card: #fff; }
		@media (prefers-color-scheme: dark) { :root { --text: #e6edf3; --muted: #9198a1; --bg: #0d1117; --card: #161b22; } }
		body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; box-sizing: border-box;
			background: var(--bg); color: var(--text); font: 1rem/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
		main { max-width: 32rem; padding: 2rem; border-radius: 0.75rem; background: var(--card); }
		h1 { margin: 0 0 0.75rem; font-size: 1.5rem; line-height: 1.25; }
		p { margin: 0 0 0.75rem; }
		.site, .reload { color: var(--muted); font-size: 0.875rem; }
	</style>
</head>
<body>
	<main>
		<p class="site"><?= $title ?></p>
		<h1><?= $texts['heading'] ?></h1>
		<p><?= $full ? $texts['full'] : $texts['writes'] ?></p>
	<?php if ($message !== null) : ?>
		<p><?= $message ?></p>
	<?php endif ?>
		<p class="reload"><?= $texts['reload'] ?></p>
	</main>
</body>
</html>

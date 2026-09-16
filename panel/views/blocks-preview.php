<?php

use function Cosray\escape;

// The layout preview sheet: a blocks field as the site's render path
// emits it, on a white ground with the shipped reference stylesheet and a
// small typographic base — no site CSS reaches the panel, so the sheet
// shows the structure and the proportions, not the site's look. The
// layout-preview behavior loads it into a frame that runs no script.

$html = (string) $this->unwrap($html);
$stylesheet = (string) $this->unwrap($stylesheet);
$locale = (string) $this->unwrap($locale);
?>
<!doctype html>
<html lang="<?= escape($locale) ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= escape(__('editor:layout-preview')) ?></title>
<style>
:root {
	color-scheme: light;
}

*,
*::before,
*::after {
	box-sizing: border-box;
}

html {
	background: #fff;
	color: #1f1f1f;
	font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
	-webkit-text-size-adjust: 100%;
}

body {
	margin: 0;
	padding: 2rem;
}

@media (max-width: 42rem) {
	body {
		padding: 1rem;
	}
}

h1,
h2,
h3,
h4,
h5,
h6 {
	margin: 0 0 0.5em;
	line-height: 1.2;
}

h1 {
	font-size: 2.25rem;
}

h2 {
	font-size: 1.75rem;
}

h3 {
	font-size: 1.375rem;
}

h4 {
	font-size: 1.125rem;
}

h5,
h6 {
	font-size: 1rem;
}

p,
ul,
ol,
blockquote,
pre {
	margin: 0 0 1em;
}

blockquote {
	padding-left: 1rem;
	border-left: 3px solid #ccc;
}

a {
	color: #1d4ed8;
}

.cms-block > :last-child {
	margin-bottom: 0;
}

img,
video,
iframe {
	max-width: 100%;
}

.cms-figure {
	margin: 0;
}

.cms-figure img,
.cms-blocks-images img,
video {
	display: block;
	width: 100%;
	height: auto;
}

.cms-figure figcaption {
	margin-top: 0.5rem;
	color: #666;
	font-size: 0.875rem;
}

.cms-blocks-images {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
	gap: 1rem;
}
</style>
<style>
<?= $stylesheet ?>
</style>
</head>
<body>
<?= $html ?>
</body>
</html>

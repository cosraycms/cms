<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

$systemUrl = u($systemUrl ?? null);

if (!is_string($systemUrl)) {
	return;
}

$oob = (bool) ($oob ?? false);
$label = __('nav:system');

?>
<a
	id="system-link"
	class="cms-masthead-button"
	href="<?= escape($systemUrl) ?>"
	hx-target="#frame"
	aria-label="<?= escape($label) ?>"
	title="<?= escape($label) ?>"
	<?= (string) ($area ?? '') === 'system' ? 'aria-current="page"' : '' ?>
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<?= Icon::render('gear') ?>
</a>

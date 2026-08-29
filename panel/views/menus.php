<?php

use function Cosray\escape;

// The area's landing screen for a project without menus. With menus around
// the rail lists them and the entry point opens the first one, so this is
// only ever reached empty.

$this->layout('layer/main');

$notice = $this->unwrap($notice ?? null);
$createUrl = (string) $menuCreateUrl;
?>

<div class="page cms-menus">
	<header class="head">
		<div class="line">
			<h1><?= escape(__('menu:menus')) ?></h1>
		</div>

		<?php if ($manages): ?>
			<div class="actions">
				<a class="cms-button primary" href="<?= escape($createUrl) ?>"><?= escape(
					__('menu:new'),
				) ?></a>
			</div>
		<?php endif ?>
	</header>

	<div class="body">
		<?php if (is_string($notice)): ?>
			<div class="cms-notice" role="status">
				<p><?= escape($notice) ?></p>
			</div>
		<?php endif ?>

		<div class="card">
			<div class="empty">
				<div class="icon" aria-hidden="true">☰</div>
				<strong><?= escape(__('menu:empty')) ?></strong>
				<?php if ($manages): ?>
					<p><?= escape(__('menu:empty-help')) ?></p>
				<?php endif ?>
			</div>
		</div>
	</div>
</div>

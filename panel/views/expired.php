<?php

use function Cosray\escape;

$this->layout('layer/document');

?>
<main id="main" class="cms-login">
	<header class="head">
		<?php if ($logo !== null): ?>
			<img class="logo" src="<?= escape((string) $logo) ?>" alt="<?= escape(__('panel:logo')) ?>" />
		<?php endif ?>
		<h1><?= escape(__('auth:expired-title')) ?></h1>
	</header>

	<section class="card" aria-label="<?= escape(__('auth:expired-title')) ?>">
		<p class="intro" role="status"><?= escape(__('auth:expired-text')) ?></p>
		<a class="cms-button primary submit" href="<?= escape($next) ?>" hx-boost="false"><?= escape(__('auth:expired-back')) ?></a>
	</section>
</main>

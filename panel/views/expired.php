<?php

use function Cosray\escape;

$this->layout('layer/document');

?>
<main id="main" class="cms-login">
	<section class="card" aria-labelledby="auth-title">
		<header class="head">
			<?php if ($logo !== null): ?>
				<img class="logo" src="<?= escape((string) $logo) ?>" alt="<?= escape(__('panel:logo')) ?>" />
			<?php endif ?>
			<h1 id="auth-title"><?= escape(__('auth:expired-title')) ?></h1>
		</header>

		<p class="intro" role="status"><?= escape(__('auth:expired-text')) ?></p>
		<a class="cms-button primary submit" href="<?= escape($next) ?>" hx-boost="false"><?= escape(__('auth:expired-back')) ?></a>
	</section>
</main>

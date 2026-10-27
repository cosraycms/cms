<?php

use function Cosray\escape;

$this->layout('layer/document');
$step = u($step);

?>
<main id="main" class="cms-login">
	<header class="head">
		<?php if ($logo !== null): ?>
			<img class="logo" src="<?= escape((string) $logo) ?>" alt="<?= escape(__('panel:logo')) ?>" />
		<?php endif ?>
		<h1><?= escape($step === 'choose' ? __('auth:reset-choose') : __('auth:reset-title')) ?></h1>
	</header>

	<section class="card" aria-label="<?= escape(__('auth:reset-title')) ?>">
		<?php if ($step === 'request'): ?>
			<form method="post" action="<?= escape($panelPath) ?>/password" hx-boost="false" novalidate>
				<p class="intro"><?= escape(__('auth:reset-intro')) ?></p>

				<?php if ($message !== null): ?>
					<p class="message" role="alert"><?= escape($message) ?></p>
				<?php endif ?>

				<div class="field">
					<label for="login"><?= escape(__('auth:login-label')) ?></label>
					<input
						class="cms-input"
						id="login"
						name="login"
						type="text"
						autocomplete="username"
						value="<?= escape($login) ?>"
						required />
				</div>

				<button class="cms-button primary submit" type="submit"><?= escape(__('auth:reset-send')) ?></button>
			</form>
		<?php elseif ($step === 'choose'): ?>
			<form method="post" action="<?= escape($panelPath) ?>/password/<?= escape($token) ?>" hx-boost="false" novalidate>
				<?php if ($message !== null): ?>
					<p class="message" role="alert"><?= escape($message) ?></p>
				<?php endif ?>

				<div class="field">
					<label for="password"><?= escape(__('user:new-password')) ?></label>
					<input
						class="cms-input"
						id="password"
						name="password"
						type="password"
						autocomplete="new-password"
						minlength="12"
						aria-describedby="password-help"
						required />
					<p class="help" id="password-help"><?= escape(__('user:password-help')) ?></p>
				</div>

				<div class="field">
					<label for="password_repeat"><?= escape(__('user:password-repeat')) ?></label>
					<input
						class="cms-input"
						id="password_repeat"
						name="password_repeat"
						type="password"
						autocomplete="new-password"
						required />
				</div>

				<button class="cms-button primary submit" type="submit"><?= escape(__('auth:reset-save')) ?></button>
			</form>
		<?php else: ?>
			<p class="intro" role="status"><?= escape(match ($step) {
				'sent' => __('auth:reset-sent'),
				'invalid' => __('auth:reset-invalid'),
				default => __('auth:reset-done'),
			}) ?></p>

			<?php if ($step === 'invalid'): ?>
				<a class="cms-button primary submit" href="<?= escape($panelPath) ?>/password" hx-boost="false"><?= escape(__('auth:reset-request-new')) ?></a>
			<?php endif ?>
		<?php endif ?>

		<?php if ($step !== 'choose'): ?>
			<div class="options">
				<a class="forgot" href="<?= escape($panelPath) ?>/login" hx-boost="false"><?= escape(__('auth:back-to-sign-in')) ?></a>
			</div>
		<?php endif ?>
	</section>
</main>

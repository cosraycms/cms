<?php

use function Cosray\escape;

$this->layout('layer/document');

?>
<main id="main" class="cms-login">
	<section class="cms-login-card" aria-labelledby="auth-title">
		<header class="cms-login-head">
			<?php if ($logo !== null): ?>
				<img class="cms-login-logo" src="<?= escape((string) $logo) ?>" alt="<?= escape(__('panel:logo')) ?>" />
			<?php endif ?>
			<h1 id="auth-title"><?= escape(__('auth:sign-in-title')) ?></h1>
		</header>

		<form method="post" action="<?= escape($panelPath) ?>/login" hx-boost="false">
			<?php $this->include('component/csrf') ?>
			<input type="hidden" name="next" value="<?= escape($next) ?>" />

			<?php if ($message !== null): ?>
				<p class="cms-login-message" role="alert"><?= escape($message) ?></p>
			<?php endif ?>

			<div class="cms-login-field">
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

			<div class="cms-login-field">
				<label for="password"><?= escape(__('auth:password')) ?></label>
				<input
					class="cms-input"
					id="password"
					name="password"
					type="password"
					autocomplete="current-password"
					required />
			</div>

			<div class="cms-login-options">
				<label class="cms-login-remember" for="rememberme">
					<input
						class="cms-checkbox"
						id="rememberme"
						type="checkbox"
						name="rememberme"
						value="1"
						<?= $rememberme ? 'checked' : '' ?> />
					<?= escape(__('auth:remember-me')) ?>
				</label>

				<?php if ($passwordReset): ?>
					<a class="cms-login-link" href="<?= escape($panelPath) ?>/password" hx-boost="false"><?= escape(__('auth:forgot-password')) ?></a>
				<?php endif ?>
			</div>

			<button class="cms-button cms-button-primary cms-login-submit" type="submit"><?= escape(__('auth:sign-in')) ?></button>
		</form>

		<?php $this->include('component/auth-locale') ?>
	</section>
</main>

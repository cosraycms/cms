<?php

use function Cosray\escape;

$this->layout('base');

?>
<main id="main" class="login-page">
	<header class="login-header">
		<?php if ($logo !== null): ?>
			<img class="login-logo" src="<?= escape((string) $logo) ?>" alt="<?= escape(__('panel:logo')) ?>" />
		<?php endif ?>
		<h1 class="login-title"><?= escape(__('auth:sign-in-title')) ?></h1>
	</header>

	<section class="login-card" aria-label="<?= escape(__('auth:sign-in')) ?>">
		<form class="login-form" method="post" action="<?= escape($panelPath) ?>/login" hx-boost="false">
			<input type="hidden" name="next" value="<?= escape($next) ?>" />

			<?php if ($message !== null): ?>
				<p class="login-message" role="alert"><?= escape($message) ?></p>
			<?php endif ?>

			<div class="login-field">
				<label class="login-label" for="login"><?= escape(__('auth:login-label')) ?></label>
				<input
					class="login-input"
					id="login"
					name="login"
					type="text"
					autocomplete="username"
					value="<?= escape($login) ?>"
					required />
			</div>

			<div class="login-field">
				<label class="login-label" for="password"><?= escape(__('auth:password')) ?></label>
				<input
					class="login-input"
					id="password"
					name="password"
					type="password"
					autocomplete="current-password"
					required />
			</div>

			<div class="login-options">
				<label class="login-remember" for="rememberme">
					<input
						class="login-checkbox"
						id="rememberme"
						type="checkbox"
						name="rememberme"
						value="1"
						<?= $rememberme ? 'checked' : '' ?> />
					<?= escape(__('auth:remember-me')) ?>
				</label>

				<a class="login-forgot" href="#" hx-boost="false" onclick="event.preventDefault();"><?= escape(__('auth:forgot-password')) ?></a>
			</div>

			<button class="cms-button primary login-submit" type="submit"><?= escape(__('auth:sign-in')) ?></button>
		</form>
	</section>
</main>

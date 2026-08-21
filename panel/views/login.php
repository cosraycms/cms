<?php

use function Cosray\escape;

$this->layout('layer/document');

?>
<main id="main" class="cms-login">
	<header class="head">
		<?php if ($logo !== null): ?>
			<img class="logo" src="<?= escape((string) $logo) ?>" alt="<?= escape(__('panel:logo')) ?>" />
		<?php endif ?>
		<h1><?= escape(__('auth:sign-in-title')) ?></h1>
	</header>

	<section class="card" aria-label="<?= escape(__('auth:sign-in')) ?>">
		<form method="post" action="<?= escape($panelPath) ?>/login" hx-boost="false">
			<input type="hidden" name="next" value="<?= escape($next) ?>" />

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

			<div class="field">
				<label for="password"><?= escape(__('auth:password')) ?></label>
				<input
					class="cms-input"
					id="password"
					name="password"
					type="password"
					autocomplete="current-password"
					required />
			</div>

			<div class="options">
				<label class="remember" for="rememberme">
					<input
						class="cms-checkbox"
						id="rememberme"
						type="checkbox"
						name="rememberme"
						value="1"
						<?= $rememberme ? 'checked' : '' ?> />
					<?= escape(__('auth:remember-me')) ?>
				</label>

				<a class="forgot" href="#" hx-boost="false" onclick="event.preventDefault();"><?= escape(__('auth:forgot-password')) ?></a>
			</div>

			<button class="cms-button primary submit" type="submit"><?= escape(__('auth:sign-in')) ?></button>
		</form>
	</section>
</main>

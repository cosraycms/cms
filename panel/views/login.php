<?php

use function Cosray\escape;

$this->layout('base');

?>
<main id="main" class="login-page">
	<header class="login-header">
		<?php if ($logo !== null): ?>
			<img class="login-logo" src="<?= escape((string) $logo) ?>" alt="Panel Logo" />
		<?php endif ?>
		<h1 class="login-title">Sign in to your account</h1>
	</header>

	<section class="login-card" aria-label="Sign in">
		<form class="login-form" method="post" action="<?= escape($panelPath) ?>/login" hx-boost="false">
			<input type="hidden" name="next" value="<?= escape($next) ?>" />

			<?php if ($message !== null): ?>
				<p class="login-message" role="alert"><?= escape($message) ?></p>
			<?php endif ?>

			<div class="login-field">
				<label class="login-label" for="login">Username or email</label>
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
				<label class="login-label" for="password">Password</label>
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
					Remember me
				</label>

				<a class="login-forgot" href="#" hx-boost="false" onclick="event.preventDefault();">Forgot password?</a>
			</div>

			<button class="cms-button primary login-submit" type="submit">Sign in</button>
		</form>
	</section>
</main>

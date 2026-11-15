<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// Cast before comparing: the template receives strings wrapped in a proxy, and
// `===` against one is false however equal the values are.
$localeId = (string) ($localeId ?? 'en');
$panelLocales = (array) (u($panelLocales ?? null) ?? []);
$account = u($account ?? null);
$dark = u($panelCookie ?? null)?->is('theme', 'dark') ?? false;

// Without a visible name the avatar alone would leave the button unnamed.
$label = is_array($account) && $account['name'] === null
	? __('nav:account', ['name' => $account['title']])
	: null;

?>
<header class="cms-masthead">
	<?php $this->include('component/logo') ?>

	<?php $this->include('component/area-nav') ?>

	<div class="cms-masthead-tools">
		<button
			type="button"
			class="cms-masthead-theme"
			data-theme-toggle
			aria-pressed="<?= $dark ? 'true' : 'false' ?>"
			aria-label="<?= escape(__('nav:dark-theme')) ?>"
			title="<?= escape(__('nav:dark-theme')) ?>">
			<?= Icon::render('moon', 'cms-masthead-theme-moon') ?>
			<?= Icon::render('sun', 'cms-masthead-theme-sun') ?>
		</button>

		<?php if (is_array($account)): ?>
			<div class="cms-masthead-account">
				<button
					type="button"
					class="cms-masthead-account-trigger"
					popovertarget="cms-account-menu"
					aria-haspopup="menu"<?php if ($label !== null): ?> aria-label="<?= escape($label) ?>"<?php endif ?>>
					<span class="cms-masthead-avatar" aria-hidden="true"><?= escape($account['initials']) ?></span>
					<?php if ($account['name'] !== null): ?>
						<span class="cms-masthead-name"><?= escape($account['name']) ?></span>
					<?php endif ?>
				</button>
				<div id="cms-account-menu" class="cms-action-menu cms-masthead-account-menu" popover="auto" data-action-menu data-align="end">
					<?php

					// The trigger already names the menu; menus may only hold items,
					// groups and separators, so the identity is visual only.
					?>
					<div class="cms-masthead-identity" aria-hidden="true">
						<span class="cms-masthead-identity-title"><?= escape($account['title']) ?></span>
						<?php if ($account['detail'] !== ''): ?>
							<span class="cms-masthead-identity-detail"><?= escape($account['detail']) ?></span>
						<?php endif ?>
					</div>
					<hr />
					<a href="<?= $panelPath ?>/profile" role="menuitem" hx-target="#frame"><?= escape(__('nav:profile')) ?></a>
					<hr />
					<form method="post" action="<?= $panelPath ?>/logout" hx-boost="false">
						<?php $this->include('component/csrf') ?>
						<button type="submit"><?= escape(__('nav:logout')) ?></button>
					</form>
					<?php if (count($panelLocales) > 1): ?>
						<hr />
						<form
							class="cms-masthead-locale"
							method="post"
							action="<?= $panelPath ?>/locale"
							hx-boost="false"
							data-autosubmit>
							<?php $this->include('component/csrf') ?>
							<label class="cms-masthead-group-label" for="cms-masthead-locale"><?= escape(__('nav:language')) ?></label>
							<select class="cms-select" id="cms-masthead-locale" name="locale">
								<?php foreach ($panelLocales as $id => $title): ?>
									<?php $selected = $id === $localeId ? ' selected' : '' ?>
									<option value="<?= escape($id) ?>" lang="<?= escape($id) ?>"<?= $selected ?>><?= escape($title) ?></option>
								<?php endforeach ?>
							</select>
							<noscript><button class="cms-button" type="submit"><?= escape(__('common:apply')) ?></button></noscript>
						</form>
					<?php endif ?>
				</div>
			</div>
		<?php endif ?>
	</div>
</header>

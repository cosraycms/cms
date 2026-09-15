<?php

use function Cosray\escape;

// Cast before comparing: the template receives strings wrapped in a proxy, and
// `===` against one is false however equal the values are.
$localeId = (string) ($localeId ?? 'en');
$panelLocales = (array) ($this->unwrap($panelLocales ?? null) ?? []);
$account = $this->unwrap($account ?? null);

// Without a visible name the avatar alone would leave the button unnamed.
$label = is_array($account) && $account['name'] === null
	? __('nav:account', ['name' => $account['title']])
	: null;

?>
<header class="cms-masthead">
	<?php $this->insert('component/logo') ?>

	<?php $this->insert('component/area-nav') ?>

	<?php if (is_array($account)): ?>
		<div class="account">
			<button
				type="button"
				class="account-trigger"
				popovertarget="cms-account-menu"
				aria-haspopup="menu"<?php if ($label !== null): ?> aria-label="<?= escape($label) ?>"<?php endif ?>>
				<span class="avatar" aria-hidden="true"><?= escape($account['initials']) ?></span>
				<?php if ($account['name'] !== null): ?>
					<span class="name"><?= escape($account['name']) ?></span>
				<?php endif ?>
			</button>
			<div id="cms-account-menu" class="cms-action-menu account-menu" popover="auto" data-action-menu data-align="end">
				<?php

				// The trigger already names the menu; menus may only hold items,
				// groups and separators, so the identity is visual only.
				?>
				<div class="identity" aria-hidden="true">
					<span class="title"><?= escape($account['title']) ?></span>
					<?php if ($account['detail'] !== ''): ?>
						<span class="detail"><?= escape($account['detail']) ?></span>
					<?php endif ?>
				</div>
				<hr />
				<?php if (count($panelLocales) > 1): ?>
					<form
						class="panel-locale"
						method="post"
						action="<?= $panelPath ?>/locale"
						hx-boost="false"
						role="group"
						aria-label="<?= escape(__('nav:language')) ?>">
						<span class="group-label" aria-hidden="true"><?= escape(__('nav:language')) ?></span>
						<?php foreach ($panelLocales as $id => $title): ?>
							<button
								type="submit"
								name="locale"
								value="<?= escape($id) ?>"
								role="menuitemradio"
								aria-checked="<?= $id === $localeId ? 'true' : 'false' ?>"><?= escape($title) ?></button>
						<?php endforeach ?>
					</form>
					<hr />
				<?php endif ?>
				<form method="post" action="<?= $panelPath ?>/logout" hx-boost="false">
					<button type="submit"><?= escape(__('nav:logout')) ?></button>
				</form>
			</div>
		</div>
	<?php endif ?>
</header>

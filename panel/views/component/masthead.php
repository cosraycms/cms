<?php

use function Cosray\escape;

// Cast before comparing: the template receives strings wrapped in a proxy, and
// `===` against one is false however equal the values are.
$localeId = (string) ($localeId ?? 'en');
$panelLocales = (array) ($this->unwrap($panelLocales ?? null) ?? []);

?>
<header class="cms-masthead">
	<?php $this->insert('component/logo') ?>

	<?php $this->insert('component/area-nav') ?>

	<div class="account">
		<?php if (count($panelLocales) > 1): ?>
			<form class="panel-locale" method="post" action="<?= $panelPath ?>/locale" hx-boost="false">
				<label>
					<span class="sr-only"><?= escape(__('nav:language')) ?></span>
					<select name="locale" onchange="this.form.submit()">
						<?php foreach ($panelLocales as $id => $title): ?>
							<option value="<?= escape($id) ?>"<?= $id === $localeId
	? ' selected'
	: '' ?>><?= escape($title) ?></option>
						<?php endforeach ?>
					</select>
				</label>
			</form>
		<?php endif ?>
		<form method="post" action="<?= $panelPath ?>/logout" hx-boost="false">
			<button class="action" type="submit"><?= escape(__('nav:logout')) ?></button>
		</form>
	</div>
</header>

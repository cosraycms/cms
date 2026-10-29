<?php

use function Cosray\escape;

?>
<?php if (count($panelLocales) > 1): ?>
	<form class="locale" method="post" action="<?= escape($panelPath) ?>/login/locale" hx-boost="false" data-autosubmit>
		<?php $this->include('component/csrf') ?>
		<label class="sr-only" for="auth-locale"><?= escape(__('nav:language')) ?></label>
		<select class="cms-select" id="auth-locale" name="locale">
			<?php foreach ($panelLocales as $id => $title): ?>
				<option value="<?= escape($id) ?>" lang="<?= escape($id) ?>" <?= u($id) === u($localeId) ? 'selected' : '' ?>><?= escape(
					$title,
				) ?></option>
			<?php endforeach ?>
		</select>
		<noscript><button class="cms-button" type="submit"><?= escape(__('common:apply')) ?></button></noscript>
	</form>
<?php endif ?>

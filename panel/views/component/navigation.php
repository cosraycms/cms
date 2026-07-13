<?php

use function Cosray\escape;

?>
<aside class="cms-sidebar">
	<header class="header">
		<?php $this->insert('component/logo') ?>
	</header>

	<nav class="navigation" aria-label="Panel navigation">
		<div class="scroll">
			<ul class="nav-list level-0">
				<li class="nav-item">
					<a
						class="nav-link"
						href="<?= $panelPath ?>"
						hx-target="#main"
						<?= (string) $currentPath === (string) $panelPath ? 'aria-current="page"' : '' ?>>
						<?= escape(__('nav:dashboard')) ?>
					</a>
				</li>
				<li class="nav-item">
					<a
						class="nav-link"
						href="<?= $panelPath ?>/media"
						hx-target="#main"
						<?= (string) $currentPath === (string) $panelPath . '/media' ? 'aria-current="page"' : '' ?>>
						<?= escape(__('nav:media')) ?>
					</a>
				</li>
			</ul>

			<?php $this->insert('component/collections', ['level' => 0]) ?>
		</div>
	</nav>

	<footer class="footer">
<?php if (count($panelLocales ?? []) > 1): ?>
		<form class="panel-locale" method="post" action="<?= $panelPath ?>/locale" hx-boost="false">
			<label>
				<span class="visually-hidden"><?= escape(__('nav:language')) ?></span>
				<select name="locale" onchange="this.form.submit()">
<?php foreach ($panelLocales as $id => $title): ?>
					<option value="<?= escape($id) ?>"<?= $id === $localeId ? ' selected' : '' ?>><?= escape($title) ?></option>
<?php endforeach ?>
				</select>
			</label>
		</form>
<?php endif ?>
		<form method="post" action="<?= $panelPath ?>/logout" hx-boost="false">
			<button class="action" type="submit"><?= escape(__('nav:logout')) ?></button>
		</form>
	</footer>
</aside>

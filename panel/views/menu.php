<?php

use function Cosray\escape;

$this->layout('layer/main');

$menu = (string) $menu;
$description = (string) $description;
$itemCount = (int) $itemCount;
$tree = (array) $this->unwrap($tree);
$preview = (string) $this->unwrap($preview);
$pane = $this->unwrap($pane);
$notice = $this->unwrap($notice ?? null);
$undo = $this->unwrap($undo ?? null);
$urls = (array) $this->unwrap($urls);
?>

<div class="page cms-menus cms-menu-tree">
	<header class="head">
		<div class="titles">
			<div class="line">
				<h1><?= escape($description) ?></h1>
				<span class="cms-count"><?= escape(__n(
					'menu:item-count',
					'menu:item-count-plural',
					$itemCount,
				)) ?></span>
			</div>
		</div>
	</header>

	<div class="body">
		<?php $this->insert('menu/properties') ?>

		<?php if (is_string($notice)): ?>
			<div class="cms-notice" role="status">
				<p><?= escape($notice) ?></p>
				<?php if (is_array($undo)): ?>
					<form method="post" action="<?= escape((string) $undo['action']) ?>">
						<input type="hidden" name="parent" value="<?= escape(
							(string) $undo['parent'],
						) ?>" />
						<input type="hidden" name="index" value="<?= escape(
							(string) $undo['index'],
						) ?>" />
						<button type="submit" class="undo"><?= escape(__('menu:undo')) ?></button>
					</form>
				<?php endif ?>
			</div>
		<?php endif ?>

		<div class="workspace">
			<div class="tree">
				<?php if (count($tree) === 0): ?>
					<div class="empty">
						<div class="icon" aria-hidden="true">☰</div>
						<strong><?= escape(__('menu:tree-empty')) ?></strong>
						<p><?= escape(__('menu:tree-empty-help')) ?></p>
					</div>
				<?php else: ?>
					<?php // The drag behavior posts drops through this form, so
					// a move rides the boosted pipeline like every other action. ?>
					<form
						id="menu-drag"
						method="post"
						hidden
						data-menu-drag-action="<?= escape($urls['tree']) ?>/item/__item__/move">
						<input type="hidden" name="parent" value="" />
						<input type="hidden" name="index" value="" />
					</form>
					<?php // ARIA `tree`: the list is one tab stop, exactly one row
					// carries tabindex 0, and the behavior moves it. That is
					// what frees Tab and Shift+Tab for indenting. ?>
					<ul
						class="menu-tree"
						role="tree"
						tabindex="-1"
						aria-label="<?= escape($description) ?>"
						data-menu-tree="<?= escape($menu) ?>"
						data-menu-list
						data-parent="">
						<?php foreach ($tree as $row): ?>
							<?php $this->insert('menu/node', [
								'row' => $row,
								'treeUrl' => $urls['tree'],
								'selected' => is_array($pane) ? (string) ($pane['item'] ?? '') : '',
							]) ?>
						<?php endforeach ?>
					</ul>
				<?php endif ?>

				<?php if ($preview !== ''): ?>
					<details class="preview">
						<summary><?= escape(__('menu:preview')) ?></summary>
						<?php // hx-boost off: preview links leave the panel for
						// the real pages instead of swapping them into #main. ?>
						<div class="preview-body" hx-boost="false">
							<?= $preview ?>
						</div>
					</details>
				<?php endif ?>
			</div>

			<aside class="pane" id="menu-item-pane">
				<?php if (is_array($pane)): ?>
					<?php $this->insert('menu/pane', ['pane' => $pane]) ?>
				<?php else: ?>
					<div class="pane-empty">
						<p><?= escape(__('menu:pane-empty')) ?></p>
						<a class="cms-button primary" href="<?= escape($urls['add']) ?>"><?= escape(
							__('menu:add-item'),
						) ?></a>
					</div>
				<?php endif ?>
			</aside>
		</div>
	</div>
</div>

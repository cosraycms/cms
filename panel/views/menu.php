<?php

use function Cosray\escape;

$this->layout('layer/main');

$menu = (string) $menu;
$description = (string) $description;
$itemCount = (int) $itemCount;
$tree = (array) $this->unwrap($tree);
$pane = $this->unwrap($pane);
$notice = $this->unwrap($notice ?? null);
$urls = (array) $this->unwrap($urls);
?>

<div class="page cms-menus cms-menu-tree">
	<header class="head">
		<div class="titles">
			<nav class="breadcrumb" aria-label="<?= escape(__('menu:breadcrumb')) ?>">
				<a href="<?= escape($urls['menus']) ?>"><?= escape(__('menu:menus')) ?></a>
				<span aria-hidden="true">/</span>
				<span><?= escape($menu) ?></span>
			</nav>
			<div class="line">
				<h1><?= escape($description) ?></h1>
				<span class="cms-count"><?= escape(__n(
					'menu:item-count',
					'menu:item-count-plural',
					$itemCount,
				)) ?></span>
			</div>
		</div>

		<div class="actions">
			<a class="cms-button secondary" href="<?= escape($urls['edit']) ?>"><?= escape(
				__('menu:edit-title'),
			) ?></a>
			<a class="cms-button primary" href="<?= escape($urls['add']) ?>"><?= escape(
				__('menu:add-item'),
			) ?></a>
		</div>
	</header>

	<div class="body">
		<?php if (is_string($notice)): ?>
			<div class="cms-notice" role="status">
				<p><?= escape($notice) ?></p>
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
					<ul class="menu-tree" data-menu-list data-parent="">
						<?php foreach ($tree as $row): ?>
							<?php $this->insert('menu/node', [
								'row' => $row,
								'treeUrl' => $urls['tree'],
								'selected' => is_array($pane) ? (string) ($pane['item'] ?? '') : '',
							]) ?>
						<?php endforeach ?>
					</ul>
				<?php endif ?>
			</div>

			<aside class="pane" id="menu-item-pane">
				<?php if (is_array($pane)): ?>
					<?php $this->insert('menu/pane', ['pane' => $pane]) ?>
				<?php else: ?>
					<div class="pane-empty">
						<p><?= escape(__('menu:pane-empty')) ?></p>
					</div>
				<?php endif ?>
			</aside>
		</div>
	</div>
</div>

<?php

use function Cosray\escape;

// One item card plus, nested below it, the list of its children. The
// partial recurses through `menu/node` for every child.

$row = (array) $this->unwrap($row);
$treeUrl = (string) $treeUrl;
$selected = (string) $selected;

$id = (string) $row['id'];
$title = (string) $row['title'] !== '' ? (string) $row['title'] : __('menu:untitled');
$href = $row['href'] ?? null;
$children = (array) $row['children'];
$descendants = (int) $row['descendants'];

// Literal ids so the i18n scanner sees every key.
$typeLabel = match ((string) $row['type']) {
	'node' => __('menu:type-node'),
	'url' => __('menu:type-url'),
	'asset' => __('menu:type-asset'),
	'label' => __('menu:type-label'),
	default => (string) $row['type'],
};

$confirm = $descendants === 0
	? __('menu:confirm-item-delete', ['title' => $title])
	: __n(
		'menu:confirm-item-delete-children',
		'menu:confirm-item-delete-children-plural',
		$descendants,
		['title' => $title],
	);
?>
<li class="menu-node" data-uid="<?= escape($id) ?>">
	<div class="menu-card<?= $id === $selected ? ' is-selected' : '' ?>">
		<?php if (count($children) > 0): ?>
			<button
				type="button"
				class="collapse"
				data-menu-collapse
				aria-expanded="true"
				aria-label="<?= escape(__('menu:collapse', ['title' => $title])) ?>"></button>
		<?php endif ?>
		<span class="grip" data-menu-grip aria-hidden="true"></span>
		<a class="text" href="<?= escape($treeUrl) ?>?item=<?= escape(rawurlencode($id)) ?>">
			<strong><?= escape($title) ?></strong>
			<small>
				<?= escape($typeLabel) ?><?= is_string($href) && $href !== ''
	? ' · ' . escape($href)
	: '' ?>
			</small>
		</a>
		<details class="kebab">
			<summary aria-label="<?= escape(__('menu:item-actions')) ?>"></summary>
			<div class="kebab-menu">
				<a href="<?= escape($treeUrl) ?>?add=<?= escape(rawurlencode($id)) ?>"><?= escape(
					__('menu:add-child'),
				) ?></a>
				<form method="post" action="<?= escape($treeUrl) ?>/item/<?= escape(
					rawurlencode($id),
				) ?>/move">
					<input type="hidden" name="direction" value="up" />
					<button type="submit"<?= $row['first'] ? ' disabled' : '' ?>><?= escape(
						__('menu:move-up'),
					) ?></button>
				</form>
				<form method="post" action="<?= escape($treeUrl) ?>/item/<?= escape(
					rawurlencode($id),
				) ?>/move">
					<input type="hidden" name="direction" value="down" />
					<button type="submit"<?= $row['last'] ? ' disabled' : '' ?>><?= escape(
						__('menu:move-down'),
					) ?></button>
				</form>
				<form
					method="post"
					action="<?= escape($treeUrl) ?>/item/<?= escape(rawurlencode($id)) ?>/delete"
					hx-confirm="<?= escape($confirm) ?>">
					<button type="submit" class="danger"><?= escape(__('menu:delete')) ?></button>
				</form>
			</div>
		</details>
	</div>
	<?php if (count($children) > 0): ?>
		<ul class="menu-children" data-menu-list data-parent="<?= escape($id) ?>">
			<?php foreach ($children as $child): ?>
				<?php $this->insert('menu/node', [
					'row' => $child,
					'treeUrl' => $treeUrl,
					'selected' => $selected,
				]) ?>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
</li>

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
$hidden = (bool) $row['hidden'];
$nested = (bool) $row['nested'];
$level = (int) $row['level'];
$hasChildren = count($children) > 0;

// Literal ids so the i18n scanner sees every key.
$typeLabel = match ((string) $row['type']) {
	'node' => __('menu:type-node'),
	'url' => __('menu:type-url'),
	'asset' => __('menu:type-asset'),
	'label' => __('menu:type-label'),
	'children' => __('menu:type-children'),
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
<li
	class="menu-node"
	role="treeitem"
	aria-level="<?= escape((string) $level) ?>"
	<?= $hasChildren ? 'aria-expanded="true"' : '' ?>
	tabindex="-1"
	data-uid="<?= escape($id) ?>">
	<div
		class="menu-card<?= $id === $selected ? ' is-selected' : '' ?><?= $hidden
	? ' is-hidden'
	: '' ?>">
		<?php // The row owns `aria-expanded`, so the toggle is a pointer
		// affordance only; the arrow keys drive it for everyone else. ?>
		<?php if ($hasChildren): ?>
			<button
				type="button"
				class="collapse"
				tabindex="-1"
				aria-hidden="true"
				data-menu-collapse></button>
		<?php endif ?>
		<span class="grip" data-menu-grip aria-hidden="true"></span>
		<a
			class="text"
			tabindex="-1"
			href="<?= escape($treeUrl) ?>?item=<?= escape(rawurlencode($id)) ?>">
			<strong><?= escape($title) ?></strong>
			<small>
				<?= $hidden ? escape(__('menu:item-hidden-mark')) . ' · ' : '' ?><?= escape(
	$typeLabel,
) ?><?= is_string($href) && $href !== '' ? ' · ' . escape($href) : '' ?>
			</small>
		</a>
		<details class="kebab">
			<summary tabindex="-1" aria-label="<?= escape(
				__('menu:item-actions'),
			) ?>"></summary>
			<div class="kebab-menu">
				<a
					data-menu-add="after"
					href="<?= escape($treeUrl) ?>?after=<?= escape(rawurlencode($id)) ?>"><?= escape(
					__('menu:add-after'),
				) ?></a>
				<a
					data-menu-add="child"
					href="<?= escape($treeUrl) ?>?add=<?= escape(rawurlencode($id)) ?>"><?= escape(
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
				<?php // Indenting needs a sibling above to become a child of;
				// outdenting needs a parent to be lifted out of. ?>
				<form method="post" action="<?= escape($treeUrl) ?>/item/<?= escape(
					rawurlencode($id),
				) ?>/move">
					<input type="hidden" name="direction" value="in" />
					<button type="submit"<?= $row['first'] ? ' disabled' : '' ?>><?= escape(
						__('menu:move-in'),
					) ?></button>
				</form>
				<form method="post" action="<?= escape($treeUrl) ?>/item/<?= escape(
					rawurlencode($id),
				) ?>/move">
					<input type="hidden" name="direction" value="out" />
					<button type="submit"<?= $nested ? '' : ' disabled' ?>><?= escape(
						__('menu:move-out'),
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
	<?php // Rendered even without children: every card owns a drop zone
	// for the drag behavior, marked `no-children` while it is empty. ?>
	<ul
		class="menu-children<?= $hasChildren ? '' : ' no-children' ?>"
		role="<?= $hasChildren ? 'group' : 'presentation' ?>"
		data-menu-list
		data-parent="<?= escape($id) ?>">
		<?php foreach ($children as $child): ?>
			<?php $this->insert('menu/node', [
				'row' => $child,
				'treeUrl' => $treeUrl,
				'selected' => $selected,
			]) ?>
		<?php endforeach ?>
	</ul>
</li>

<?php

use function Cosray\escape;

// The item form the tree's side pane holds: create (`?add=`) or edit
// (`?item=`). Type-specific sections carry `data-menu-section`; the
// menu behavior toggles them when the type select changes, and without
// JavaScript every section simply stays visible.

$pane = (array) $this->unwrap($pane);

$mode = (string) $pane['mode'];
$action = (string) $pane['action'];
$parent = $pane['parent'] ?? null;
$anchor = $pane['anchor'] ?? null;
$parentTitle = $pane['parentTitle'] ?? null;
$values = (array) $pane['values'];
$errors = (array) $pane['errors'];
$cancelUrl = (string) $pane['cancelUrl'];
$locales = (array) $pane['locales'];
$defaultLocale = (string) $pane['defaultLocale'];
$searchUrls = (array) $pane['searchUrls'];

$type = (string) $values['type'];
$types = ['node', 'url', 'asset', 'label', 'children'];
$typeLabels = [
	'node' => __('menu:type-node'),
	'url' => __('menu:type-url'),
	'asset' => __('menu:type-asset'),
	'label' => __('menu:type-label'),
	'children' => __('menu:type-children'),
];
$orders = [
	'title' => __('menu:order-title'),
	'created' => __('menu:order-created'),
	'created desc' => __('menu:order-created-desc'),
	'changed desc' => __('menu:order-changed-desc'),
];
$section = static fn(string $names): bool => !in_array($type, explode(' ', $names), true);
?>
<div class="pane-form">
	<h2><?= escape($mode === 'create' ? __('menu:item-create-title') : __('menu:item-edit-title')) ?></h2>
	<?php if (is_string($parentTitle)): ?>
		<p class="pane-hint"><?= escape(__('menu:item-below', ['title' => $parentTitle])) ?></p>
	<?php endif ?>

	<form method="post" action="<?= escape($action) ?>">
		<?php if ($parent !== null): ?>
			<input type="hidden" name="parent" value="<?= escape((string) $parent) ?>" />
		<?php endif ?>
		<?php // The sibling the new item is inserted next to, and on which
		// side; without it the item appends to the end of its group. ?>
		<?php if (is_array($anchor)): ?>
			<input
				type="hidden"
				name="<?= escape((string) $anchor['side']) ?>"
				value="<?= escape((string) $anchor['item']) ?>" />
		<?php endif ?>

		<div class="cms-field<?= isset($errors['type']) ? ' has-error' : '' ?>">
			<label class="label" for="menu-item-type"><div><?= escape(__('menu:item-type')) ?></div></label>
			<div class="control">
				<select class="cms-input" id="menu-item-type" name="type" data-menu-type>
					<?php foreach ($types as $option): ?>
						<option value="<?= escape($option) ?>"<?= $option === $type ? ' selected' : '' ?>><?= escape(
							$typeLabels[$option],
						) ?></option>
					<?php endforeach ?>
					<?php if (!in_array($type, $types, true)): ?>
						<option value="<?= escape($type) ?>" selected><?= escape($type) ?></option>
					<?php endif ?>
				</select>
			</div>
			<?php if (isset($errors['type'])): ?>
				<p class="error"><?= escape((string) $errors['type']) ?></p>
			<?php endif ?>
		</div>

		<?php $this->insert('menu/localized', [
			'name' => 'title',
			'label' => __('menu:item-title'),
			'values' => $values['title'],
			'error' => $errors['title'] ?? null,
			'id' => 'menu-item-title',
			'locales' => $locales,
			'defaultLocale' => $defaultLocale,
			'help' => __('menu:item-title-inherit-help'),
			'helpSection' => 'node',
			'helpHidden' => $type !== 'node',
			'sectionHide' => 'children',
			'sectionHidden' => $type === 'children',
		]) ?>

		<div
			class="cms-field<?= isset($errors['node']) ? ' has-error' : '' ?>"
			data-menu-section="node children"
			<?= $section('node children') ? 'hidden' : '' ?>>
			<label class="label" for="menu-item-node"><div><?= escape(__('menu:item-node')) ?></div></label>
			<div
				class="control menu-picker"
				data-menu-picker="nodes"
				data-menu-picker-url="<?= escape((string) $searchUrls['node']) ?>">
				<input
					type="hidden"
					name="node"
					value="<?= escape((string) $values['node']) ?>"
					data-menu-picker-value />
				<input
					class="cms-input"
					id="menu-item-node"
					type="text"
					autocomplete="off"
					placeholder="<?= escape(__('menu:picker-nodes')) ?>"
					value="<?= escape((string) $values['nodeLabel']) ?>"
					data-menu-picker-search
					<?= isset($errors['node']) ? 'aria-invalid="true"' : '' ?> />
				<div class="menu-picker-results" data-menu-picker-results hidden></div>
			</div>
			<?php if (isset($errors['node'])): ?>
				<p class="error"><?= escape((string) $errors['node']) ?></p>
			<?php endif ?>
		</div>

		<div class="cms-field" data-menu-section="children" <?= $section('children') ? 'hidden' : '' ?>>
			<label class="label" for="menu-item-levels"><div><?= escape(__('menu:item-levels')) ?></div></label>
			<div class="control">
				<input
					class="cms-input"
					id="menu-item-levels"
					name="levels"
					type="number"
					min="1"
					max="5"
					value="<?= escape((string) $values['levels']) ?>" />
			</div>
			<p class="help"><?= escape(__('menu:children-help')) ?></p>
		</div>

		<div class="cms-field" data-menu-section="children" <?= $section('children') ? 'hidden' : '' ?>>
			<label class="label" for="menu-item-order"><div><?= escape(__('menu:item-order')) ?></div></label>
			<div class="control">
				<select class="cms-input" id="menu-item-order" name="order">
					<?php foreach ($orders as $value => $label): ?>
						<option value="<?= escape($value) ?>"<?= $value === $values['order'] ? ' selected' : '' ?>><?= escape(
							$label,
						) ?></option>
					<?php endforeach ?>
				</select>
			</div>
		</div>

		<?php $this->insert('menu/localized', [
			'name' => 'path',
			'label' => __('menu:item-path'),
			'values' => $values['path'],
			'error' => $errors['path'] ?? null,
			'id' => 'menu-item-path',
			'locales' => $locales,
			'defaultLocale' => $defaultLocale,
			'section' => 'url',
			'sectionHidden' => $section('url'),
		]) ?>

		<div
			class="cms-field<?= isset($errors['asset']) ? ' has-error' : '' ?>"
			data-menu-section="asset"
			<?= $section('asset') ? 'hidden' : '' ?>>
			<label class="label" for="menu-item-asset"><div><?= escape(__('menu:item-asset')) ?></div></label>
			<div
				class="control menu-picker"
				data-menu-picker="assets"
				data-menu-picker-url="<?= escape((string) $searchUrls['asset']) ?>">
				<input
					type="hidden"
					name="asset"
					value="<?= escape((string) $values['asset']) ?>"
					data-menu-picker-value />
				<input
					class="cms-input"
					id="menu-item-asset"
					type="text"
					autocomplete="off"
					placeholder="<?= escape(__('menu:picker-assets')) ?>"
					value="<?= escape((string) $values['assetLabel']) ?>"
					data-menu-picker-search
					<?= isset($errors['asset']) ? 'aria-invalid="true"' : '' ?> />
				<div class="menu-picker-results" data-menu-picker-results hidden></div>
			</div>
			<?php if (isset($errors['asset'])): ?>
				<p class="error"><?= escape((string) $errors['asset']) ?></p>
			<?php endif ?>
		</div>

		<div
			class="cms-field"
			data-menu-section="node url asset"
			<?= $section('node url asset') ? 'hidden' : '' ?>>
			<label class="target">
				<input
					class="cms-checkbox"
					type="checkbox"
					name="target"
					value="_blank"
					<?= $values['target'] ? 'checked' : '' ?> />
				<span><?= escape(__('menu:item-target')) ?></span>
			</label>
		</div>

		<div class="cms-field">
			<label class="target">
				<input
					class="cms-checkbox"
					type="checkbox"
					name="hidden"
					value="1"
					<?= $values['hidden'] ? 'checked' : '' ?> />
				<span><?= escape(__('menu:item-hidden')) ?></span>
			</label>
			<p class="help"><?= escape(__('menu:item-hidden-help')) ?></p>
		</div>

		<div
			class="cms-field<?= isset($errors['image']) ? ' has-error' : '' ?>"
			data-menu-section-hide="children"
			<?= $type === 'children' ? 'hidden' : '' ?>>
			<label class="label" for="menu-item-image"><div><?= escape(__('menu:item-image')) ?></div></label>
			<div
				class="control menu-picker"
				data-menu-picker="assets"
				data-menu-picker-url="<?= escape((string) $searchUrls['image']) ?>">
				<input
					type="hidden"
					name="image"
					value="<?= escape((string) $values['image']) ?>"
					data-menu-picker-value />
				<input
					class="cms-input"
					id="menu-item-image"
					type="text"
					autocomplete="off"
					placeholder="<?= escape(__('menu:picker-assets')) ?>"
					value="<?= escape((string) $values['imageLabel']) ?>"
					data-menu-picker-search
					<?= isset($errors['image']) ? 'aria-invalid="true"' : '' ?> />
				<div class="menu-picker-results" data-menu-picker-results hidden></div>
			</div>
			<?php if (isset($errors['image'])): ?>
				<p class="error"><?= escape((string) $errors['image']) ?></p>
			<?php endif ?>
		</div>

		<div
			class="cms-field<?= isset($errors['class']) ? ' has-error' : '' ?>"
			data-menu-section-hide="children"
			<?= $type === 'children' ? 'hidden' : '' ?>>
			<label class="label" for="menu-item-class"><div><?= escape(__('menu:item-class')) ?></div></label>
			<div class="control">
				<input
					class="cms-input"
					id="menu-item-class"
					name="class"
					type="text"
					maxlength="64"
					value="<?= escape((string) $values['class']) ?>"
					<?= isset($errors['class']) ? 'aria-invalid="true"' : '' ?> />
			</div>
			<?php if (isset($errors['class'])): ?>
				<p class="error"><?= escape((string) $errors['class']) ?></p>
			<?php endif ?>
		</div>

		<footer class="form-actions">
			<a class="cms-button secondary" href="<?= escape($cancelUrl) ?>"><?= escape(
				__('menu:cancel'),
			) ?></a>
			<button type="submit" class="cms-button primary"><?= escape(
				$mode === 'create' ? __('menu:add-item') : __('menu:save'),
			) ?></button>
		</footer>
	</form>
</div>

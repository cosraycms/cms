<?php

use function Cosray\escape;

// Wrapper for a single field: label, locale tabs, control variants and
// description. Cross-cutting concerns live here — control views only
// render their input. Receives: field, data, locales, defaultLocale.

$field = (array) $this->unwrap($field);
$data = (array) ($this->unwrap($data ?? null) ?? []);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$node = (string) ($node ?? '');
$assets = (array) ($this->unwrap($assets ?? null) ?? []);

$control = $field['control'] ?? ['name' => '', 'props' => []];
$controlName = (string) ($control['name'] ?? '');
$fieldName = (string) ($field['name'] ?? '');

// Entries rows re-enter this wrapper with a deeper root; top-level
// fields keep the default derivation from the field name.
$nameRoot = (string) ($nameRoot ?? "content[{$fieldName}]");
$idRoot = (string) ($idRoot ?? "field-{$fieldName}");
$value = $data['value'] ?? [];
$value = is_array($value) ? $value : [];

// Primitives rendered once per locale. Element controls receive the
// whole locale map and handle locales internally — they still get tabs.
// A blocks field has one row list per locale only in asymmetric mode;
// a symmetric list is shared and its rows translate their own sub-fields.
// A typed repeater row switches its sub-fields as one, and then owns the
// pills; the sub-field wrappers inside it render none. The only field of
// a block renders its label for screen readers only — the block's own
// label already names it.
$ownLocales = (bool) ($this->unwrap($ownLocales ?? null) ?? true);
$bareLabel = (bool) ($this->unwrap($bareLabel ?? null) ?? false);
$localized = ['text', 'textarea', 'iframe'];
$translate = (bool) ($field['translate'] ?? false);
$asymmetric = $controlName === 'blocks' && ($field['translateMode'] ?? null) === 'asymmetric';
$variants = $translate && (in_array($controlName, $localized, true) || $asymmetric);
$tabs = $ownLocales && $translate && ($variants || $controlName === 'element');
$neutral = 'zxx';

$labelFor = $idRoot . '-' . ($variants ? $defaultLocale : $neutral);
$description = $field['description'] ?? null;
$required = (bool) ($field['required'] ?? false);
$when = $field['when'] ?? null;
$metaControl = $field['metaControl'] ?? null;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div
	class="cms-field<?= $required ? ' required' : '' ?>"
	<?= $tabs ? 'data-locale-scope' : '' ?>
	data-meta-owner
	<?= $required ? 'data-required="true"' : '' ?>
	<?= is_array($when) ? "data-when='" . json_encode($when, $jsonFlags) . "'" : '' ?>>
	<?php // Kept in the tree when hidden: the control needs its name. ?>
	<label
		for="<?= escape($labelFor) ?>"
		class="label<?= $bareLabel && !is_array($metaControl) ? ' sr-only' : '' ?>">
		<div<?= $bareLabel ? ' class="sr-only"' : '' ?>><?= escape(
			(string) ($field['label'] ?? $fieldName),
		) ?></div>
		<?php if (is_array($metaControl)): ?>
			<button type="button" class="meta-button" data-meta-open>
				<?= escape(__('field:meta')) ?>
			</button>
		<?php endif ?>
		<?php if ($tabs): ?>
			<span class="cms-locales">
				<?php foreach ($locales as $locale): ?>
					<button
						type="button"
						class="tab<?= $locale['id'] === $defaultLocale ? ' active' : '' ?>"
						data-locale-tab="<?= escape($locale['id']) ?>">
						<?= escape(strtoupper($locale['id'])) ?>
					</button>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</label>
	<div class="control<?= $controlName === 'checkbox' ? ' cms-checkbox-wrap' : '' ?>">
		<?php if ($variants): ?>
			<?php foreach ($locales as $locale): ?>
				<div
					class="variant"
					data-locale="<?= escape($locale['id']) ?>"
					<?= $locale['id'] === $defaultLocale ? '' : 'hidden' ?>>
					<?php // Required applies to the default locale only — the same

					// rule the server-side shape validates. ?>
					<?php $this->insert('field/control', [
						'field' => ['required' => $locale['id'] === $defaultLocale && $required] + $field,
						'control' => $control,
						'id' => "{$idRoot}-{$locale['id']}",
						'name' => "{$nameRoot}[value][{$locale['id']}]",
						'nameRoot' => $nameRoot,
						'value' => $value[$locale['id']] ?? null,
						'data' => $data,
						'node' => $node,
						'locales' => $locales,
						'defaultLocale' => $defaultLocale,
						'assets' => $assets,
					]) ?>
				</div>
			<?php endforeach ?>
		<?php else: ?>
			<?php $this->insert('field/control', [
				'field' => $field,
				'control' => $control,
				'id' => "{$idRoot}-{$neutral}",
				'name' => "{$nameRoot}[value][{$neutral}]",
				'nameRoot' => $nameRoot,
				'value' => $value[$neutral] ?? null,
				'data' => $data,
				'node' => $node,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'assets' => $assets,
			]) ?>
		<?php endif ?>
	</div>
	<?php if (is_string($description) && $description !== ''): ?>
		<div class="description"><?= escape($description) ?></div>
	<?php endif ?>
	<?php if (is_array($metaControl)): ?>
		<dialog class="cms-meta" data-meta>
			<div class="head">
				<span class="title">
					<?= escape((string) ($field['label'] ?? $fieldName)) ?> — <?= escape(__('field:meta')) ?>
				</span>
				<button type="button" class="cms-button" data-meta-close>
					<?= escape(__('field:close')) ?>
				</button>
			</div>
			<?php $this->insert('field/meta', [
				'field' => $field,
				'control' => $metaControl,
				'meta' => $data['meta'] ?? null,
				'id' => "{$idRoot}-meta",
				'nameRoot' => $nameRoot,
			]) ?>
		</dialog>
	<?php endif ?>
</div>

<?php

use function Cosray\escape;

// Wrapper for a single field: label, locale tabs, control variants and
// description. Cross-cutting concerns live here — control views only
// render their input. Receives: field, data, locales, defaultLocale.

$field = (array) $this->unwrap($field);
$data = (array) ($this->unwrap($data) ?? []);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$node = (string) ($node ?? '');

$control = $field['control'] ?? ['name' => '', 'props' => []];
$controlName = (string) ($control['name'] ?? '');
$fieldName = (string) ($field['name'] ?? '');
$value = $data['value'] ?? [];
$value = is_array($value) ? $value : [];

// Primitives rendered once per locale. Element controls receive the
// whole locale map and handle locales internally — they still get tabs.
$localized = ['text', 'textarea', 'iframe'];
$translate = (bool) ($field['translate'] ?? false);
$variants = $translate && in_array($controlName, $localized, true);
$tabs = $translate && ($variants || $controlName === 'element');
$neutral = 'zxx';

$inputId = static fn(string $locale): string => "field-{$fieldName}-{$locale}";
$labelFor = $variants ? $inputId($defaultLocale) : $inputId($neutral);
$description = $field['description'] ?? null;
$required = (bool) ($field['required'] ?? false);
$when = $field['when'] ?? null;
$metaControl = $field['metaControl'] ?? null;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div
	class="cms-field<?= $required ? ' required' : '' ?>"
	<?= $required ? 'data-required="true"' : '' ?>
	<?= is_array($when) ? "data-when='" . json_encode($when, $jsonFlags) . "'" : '' ?>>
	<label for="<?= escape($labelFor) ?>" class="cms-field-label">
		<div><?= escape((string) ($field['label'] ?? $fieldName)) ?></div>
		<?php if (is_array($metaControl)): ?>
			<button type="button" class="cms-meta-button" data-meta-open>
				<?= escape(_('Meta')) ?>
			</button>
		<?php endif ?>
		<?php if ($tabs): ?>
			<span class="locale-tabs">
				<?php foreach ($locales as $locale): ?>
					<button
						type="button"
						class="locale-tab<?= $locale['id'] === $defaultLocale ? ' active' : '' ?>"
						data-locale-tab="<?= escape($locale['id']) ?>">
						<?= escape(strtoupper($locale['id'])) ?>
					</button>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</label>
	<div class="cms-field-control<?= $controlName === 'checkbox' ? ' cms-checkbox-wrap' : '' ?>">
		<?php if ($variants): ?>
			<?php foreach ($locales as $locale): ?>
				<div
					class="cms-locale-variant"
					data-locale="<?= escape($locale['id']) ?>"
					<?= $locale['id'] === $defaultLocale ? '' : 'hidden' ?>>
					<?php $this->insert('field/control', [
						'field' => $field,
						'control' => $control,
						'id' => $inputId($locale['id']),
						'name' => "content[{$fieldName}][value][{$locale['id']}]",
						'value' => $value[$locale['id']] ?? null,
						'data' => $data,
						'node' => $node,
						'locales' => $locales,
						'defaultLocale' => $defaultLocale,
					]) ?>
				</div>
			<?php endforeach ?>
		<?php else: ?>
			<?php $this->insert('field/control', [
				'field' => $field,
				'control' => $control,
				'id' => $inputId($neutral),
				'name' => "content[{$fieldName}][value][{$neutral}]",
				'value' => $value[$neutral] ?? null,
				'data' => $data,
				'node' => $node,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
			]) ?>
		<?php endif ?>
	</div>
	<?php if (is_string($description) && $description !== ''): ?>
		<div class="cms-field-description"><?= escape($description) ?></div>
	<?php endif ?>
	<?php if (is_array($metaControl)): ?>
		<dialog class="cms-meta-dialog" data-meta>
			<div class="cms-meta-head">
				<span class="cms-meta-title">
					<?= escape((string) ($field['label'] ?? $fieldName)) ?> — <?= escape(_('Meta')) ?>
				</span>
				<button type="button" class="cms-button" data-meta-close>
					<?= escape(_('Schließen')) ?>
				</button>
			</div>
			<?php $this->insert('field/meta', [
				'field' => $field,
				'control' => $metaControl,
				'meta' => $data['meta'] ?? null,
				'id' => "meta-{$fieldName}",
			]) ?>
		</dialog>
	<?php endif ?>
</div>

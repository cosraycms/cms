<?php

use function Cosray\escape;

// Custom-element control rendered through the form-associated host: the
// host loads the module, assigns the element contract (value, meta,
// field, node, locale, locales) from the embedded payload and carries
// every edit into the form submission as one JSON value.

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$data = (array) ($this->unwrap($data ?? null) ?? []);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$node = (string) ($node ?? '');
$fieldName = (string) ($field['name'] ?? '');

$props = (array) ($control['props'] ?? []);
$tag = (string) ($props['tag'] ?? '');
$module = (string) ($props['module'] ?? '');

$payload = [
	'value' => $data['value'] ?? null,
	'meta' => $data['meta'] ?? null,
	'field' => $field,
	'locales' => [
		'default' => $defaultLocale,
		'all' => $locales,
	],
];
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<cosray-host
	name="content[<?= escape($fieldName) ?>][json]"
	tag="<?= escape($tag) ?>"
	module="<?= escape($module) ?>"
	node="<?= escape($node) ?>"
	locale="<?= escape($field['translate'] ?? false ? $defaultLocale : 'zxx') ?>">
	<script type="application/json"><?= json_encode($payload, $jsonFlags) ?></script>
</cosray-host>

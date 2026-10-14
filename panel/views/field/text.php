<?php

$field = (array) u($field);
$control = (array) u($control);
$props = (array) ($control['props'] ?? []);

$this->include('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'text',
	'fallbackPreview' => $fallbackPreview ?? false,
	'attrs' => ['placeholder' => $props['placeholder'] ?? $field['placeholder'] ?? null],
]);

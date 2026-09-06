<?php

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);

$this->insert('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'text',
	'fallbackPreview' => $fallbackPreview ?? false,
	'attrs' => ['placeholder' => $props['placeholder'] ?? $field['placeholder'] ?? null],
]);

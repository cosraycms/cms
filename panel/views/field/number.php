<?php

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);

$this->insert('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'number',
	'attrs' => [
		'step' => $props['step'] ?? null,
		'min' => $props['min'] ?? null,
		'max' => $props['max'] ?? null,
	],
]);

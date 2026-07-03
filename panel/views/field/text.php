<?php

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);

$this->insert('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'text',
	'attrs' => ['placeholder' => $props['placeholder'] ?? null],
]);

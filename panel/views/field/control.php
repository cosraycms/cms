<?php

// Dispatches a control descriptor to its view. Receives: field, control,
// id, name (form input name), value (scalar for primitives, map for
// group, list for repeater) and data (the full content entry, used by
// element controls).

$control = (array) $this->unwrap($control);
$controlName = (string) ($control['name'] ?? '');

$views = [
	'text' => 'field/text',
	'number' => 'field/number',
	'date' => 'field/date',
	'time' => 'field/time',
	'datetime' => 'field/datetime',
	'hidden' => 'field/hidden',
	'textarea' => 'field/textarea',
	'iframe' => 'field/iframe',
	'checkbox' => 'field/checkbox',
	'option' => 'field/option',
	'element' => 'field/element',
	'group' => 'field/group',
	'repeater' => 'field/repeater',
];

$this->insert($views[$controlName] ?? 'field/unknown', [
	'field' => $field,
	'control' => $control,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'data' => $data ?? null,
]);

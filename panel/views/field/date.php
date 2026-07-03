<?php

$this->insert('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'date',
]);

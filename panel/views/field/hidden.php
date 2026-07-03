<?php

// The hidden control keeps its historical visible text-input rendering.
$this->insert('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'text',
]);

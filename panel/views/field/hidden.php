<?php

// The hidden control keeps its historical visible text-input rendering.
$this->include('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value ?? null,
	'type' => 'text',
]);

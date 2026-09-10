<?php

use Cosray\DateTime\Codec;
use Cosray\Field\Field;

$data = (array) ($this->unwrap($data ?? null) ?? []);
$timezone = $data['meta']['timezone'] ?? null;

if (is_array($timezone)) {
	$timezone = $timezone[Field::NEUTRAL_LOCALE] ?? null;
}

$timezone = Codec::timezone($timezone) ?? Codec::utc();
$value = $this->unwrap($value ?? null);

if (is_string($value) && $value !== '') {
	$value = Codec::input($value, $timezone) ?? $value;
}

$this->insert('field/input', [
	'field' => $field,
	'id' => $id,
	'name' => $name,
	'value' => $value,
	'type' => 'datetime-local',
	'attrs' => ['step' => 1],
]);

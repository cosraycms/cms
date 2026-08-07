<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
?>
<div class="cms-control-unknown">
	<?= escape(__('field:unknown-control', [
		'control' => (string) ($control['name'] ?? ''),
		'field' => (string) ($field['name'] ?? ''),
		'type' => (string) ($field['type'] ?? ''),
	])) ?>
</div>

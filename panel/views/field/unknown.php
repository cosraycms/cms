<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
?>
<div class="cms-control-unknown">
	Unknown control "<?= escape((string) ($control['name'] ?? '')) ?>"
	for field "<?= escape((string) ($field['name'] ?? '')) ?>"
	(<?= escape((string) ($field['type'] ?? '')) ?>)
</div>

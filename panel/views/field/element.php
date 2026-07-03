<?php

use function Cosray\escape;

// Placeholder host for custom-element controls; the form-associated
// host that assigns the element contract and carries the value into the
// submission replaces this box.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$tag = (string) ($props['tag'] ?? '');
$module = (string) ($props['module'] ?? '');
?>
<div
	class="cms-control-element-pending"
	data-element-tag="<?= escape($tag) ?>"
	data-element-module="<?= escape($module) ?>">
	Editor component <code><?= escape($tag) ?></code> is not available yet.
</div>

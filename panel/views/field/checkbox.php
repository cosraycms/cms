<?php

use function Cosray\escape;

// The hidden input is the presence marker: an unchecked checkbox is
// absent from the submission, the marker keeps the key present with an
// empty value so the save path can cast it to false.

$field = (array) $this->unwrap($field);
$value = $this->unwrap($value ?? null);
?>
<div class="cms-checkbox-input-wrap">
	<input type="hidden" name="<?= escape($name) ?>" value="" />
	<input
		id="<?= escape($id) ?>"
		name="<?= escape($name) ?>"
		type="checkbox"
		class="cms-checkbox"
		value="1"
		<?= $value ? 'checked' : '' ?>
		<?= $field['immutable'] ?? false ? 'disabled' : '' ?> />
</div>

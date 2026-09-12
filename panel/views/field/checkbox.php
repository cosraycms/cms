<?php

use function Cosray\escape;

// The hidden input is the presence marker: an unchecked checkbox is
// absent from the submission, the marker keeps the key present with an
// empty value so the save path can cast it to false. An immutable
// checkbox submits nothing at all, so it carries no marker either — one
// would report the field as unchecked and clear it.

$field = (array) $this->unwrap($field);
$immutable = (bool) ($field['immutable'] ?? false);
$value = $this->unwrap($value ?? null);
?>
<div class="cms-checkbox-input-wrap">
	<?php if (!$immutable): ?>
		<input type="hidden" name="<?= escape($name) ?>" value="" />
	<?php endif ?>
	<input
		id="<?= escape($id) ?>"
		name="<?= escape($name) ?>"
		type="checkbox"
		class="cms-checkbox"
		value="1"
		<?= $value ? 'checked' : '' ?>
		<?= $immutable ? 'disabled' : '' ?> />
</div>

<?php

$dimension = (string) $this->unwrap($dimension);
$value = (int) $value;
$low = (int) $low;
$high = (int) $high;
$less = (string) $this->unwrap($less);
$more = (string) $this->unwrap($more);
?>
<span class="stepper">
	<button
		type="button"
		class="step"
		data-layout-step="<?= $this->escape("{$dimension}:-1") ?>"
		aria-label="<?= $this->escape($less) ?>"
		title="<?= $this->escape($less) ?>"
		<?= $value <= $low ? 'disabled' : '' ?>>−</button>
	<span class="badge" data-layout-badge="<?= $this->escape($dimension) ?>"><?= $value ?></span>
	<button
		type="button"
		class="step"
		data-layout-step="<?= $this->escape("{$dimension}:+1") ?>"
		aria-label="<?= $this->escape($more) ?>"
		title="<?= $this->escape($more) ?>"
		<?= $value >= $high ? 'disabled' : '' ?>>+</button>
</span>

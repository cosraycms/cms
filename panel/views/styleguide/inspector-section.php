<?php

use function Cosray\escape;

// The sample content of the styleguide's inspector section.
// Receives: form.

$form = (string) u($form);
?>
<div class="cms-kit-inspector-row">
	<span>Review on 1 Dec 2026, 08:00</span>
	<button type="button" class="cms-button cms-button-secondary cms-button-small">Cancel</button>
</div>
<div class="cms-kit-inspector-field">
	<label for="styleguide-inspector-date">Review again</label>
	<div class="cms-kit-inspector-row">
		<input id="styleguide-inspector-date" class="cms-input" type="datetime-local" form="<?= escape($form) ?>" />
		<button type="button" class="cms-button cms-button-secondary cms-button-small">Plan</button>
	</div>
	<p class="cms-kit-inspector-help">Acts at once, apart from saving the node.</p>
</div>

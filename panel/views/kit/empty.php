<?php

use function Cosray\escape;

// What a kit list shows without entries. Receives: title, text (optional).

$text = u($text ?? null);
?>
<div class="cms-kit-empty">
	<strong><?= escape((string) u($title)) ?></strong>
	<?php if (is_string($text) && $text !== ''): ?>
		<p><?= escape($text) ?></p>
	<?php endif ?>
</div>

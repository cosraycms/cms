<?php

use function Cosray\escape;

// The footer of a kit list: the range shown and links to the pages before
// and after. Receives: start, end, total, previous and next (URLs or null).

$previous = u($previous ?? null);
$next = u($next ?? null);
?>
<footer class="cms-kit-foot">
	<span class="cms-kit-range"><?= escape(__('collection:showing', [
		'start' => (int) u($start),
		'end' => (int) u($end),
		'total' => (int) u($total),
	])) ?></span>
	<nav class="cms-kit-pagination" aria-label="<?= escape(__('collection:pagination')) ?>">
		<?php foreach ([[$previous, __('collection:previous')], [$next, __('collection:next')]] as [$url, $label]): ?>
			<?php if (is_string($url)): ?>
				<a class="cms-kit-page-link" href="<?= escape($url) ?>"><?= escape($label) ?></a>
			<?php else: ?>
				<span class="cms-kit-page-link" aria-disabled="true"><?= escape($label) ?></span>
			<?php endif ?>
		<?php endforeach ?>
	</nav>
</footer>

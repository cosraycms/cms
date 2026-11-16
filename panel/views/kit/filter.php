<?php

use function Cosray\escape;

// Segmented filter links with optional counts. Receives: label (for screen
// readers), links (list of [label, url, active, count?]).

$links = (array) u($links);
?>
<nav class="cms-kit-filter" aria-label="<?= escape((string) u($label)) ?>">
	<?php foreach ($links as $link): ?>
		<?php

		$link = (array) $link;
		$active = (bool) ($link['active'] ?? false);
		?>
		<a
			class="cms-kit-filter-link"
			href="<?= escape((string) $link['url']) ?>"
			<?= $active ? 'aria-current="page"' : '' ?>>
			<?= escape((string) $link['label']) ?>
			<?php if (isset($link['count'])): ?>
				<span class="cms-kit-filter-count"><?= (int) $link['count'] ?></span>
			<?php endif ?>
		</a>
	<?php endforeach ?>
</nav>

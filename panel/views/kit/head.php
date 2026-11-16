<?php

use function Cosray\escape;

// The head of a kit page: breadcrumb links, the title with an optional
// count, and actions. Receives: title, crumbs (list of [label, url]),
// count (string, optional), actions (list of kit/button arguments).

$crumbs = (array) u($crumbs ?? []);
$actions = (array) u($actions ?? []);
$count = u($count ?? null);
?>
<header class="cms-page-head">
	<div class="cms-page-titles">
		<div class="cms-page-line">
			<?php if ($crumbs !== []): ?>
				<nav class="cms-page-breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
					<?php foreach ($crumbs as [$label, $url]): ?>
						<a href="<?= escape((string) $url) ?>"><?= escape((string) $label) ?></a>
						<span class="cms-page-sep" aria-hidden="true">/</span>
					<?php endforeach ?>
				</nav>
			<?php endif ?>
			<h1><?= escape((string) u($title)) ?></h1>
			<?php if (is_string($count) && $count !== ''): ?>
				<span class="cms-count"><?= escape($count) ?></span>
			<?php endif ?>
		</div>
	</div>
	<?php if ($actions !== []): ?>
		<div class="cms-page-actions">
			<?php foreach ($actions as $action): ?>
				<?php $this->include('kit/button', (array) $action) ?>
			<?php endforeach ?>
		</div>
	<?php endif ?>
</header>

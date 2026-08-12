<?php

use function Cosray\escape;

$chevronSvgPath = __DIR__ . '/../icons/chevron.svg';
$chevronSvg = is_file($chevronSvgPath)
	? trim((string) file_get_contents($chevronSvgPath))
	: '';
$chevronSvg = str_replace(
	'<svg ',
	'<svg class="chevron" aria-hidden="true" focusable="false" ',
	$chevronSvg,
);

if (!$boosted) {
	$this->layout('panel');
}

// The title column takes the slack; the rest share what is left and the status
// column sizes to its badges. Selection and row actions are not built yet, and
// they arrive as extra tracks here rather than as a different layout.
// The title column absorbs the slack; the rest size to their content so dates
// and types are not clipped into ellipses. Selection and row action columns
// arrive as extra tracks here, not as a different layout.
$columns = 'minmax(12rem, 2fr)'
	. str_repeat(' minmax(5rem, auto)', max(count($page->headers) - 1, 0))
	. ' minmax(5rem, auto)';
?>

<div id="main" class="page cms-collection">
	<header class="head">
		<div class="titles">
			<?php if ($page->rootUrl !== null): ?>
				<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
					<a href="<?= escape($page->rootUrl) ?>" hx-target="#main"><?= escape($page->name) ?></a>
					<span aria-hidden="true">/</span>
					<span><?= escape($page->parentTitle ?? $page->query->parent) ?></span>
				</nav>
			<?php endif ?>
			<div class="line">
				<h1><?= escape($page->title) ?></h1>
				<span class="count-pill"><?= escape(__n(
					'collection:entry-count',
					'collection:entry-count-plural',
					$page->total,
				)) ?></span>
			</div>
		</div>

		<div class="actions">
			<?php if (count($page->viewLinks) > 0): ?>
				<nav class="view-toggle" aria-label="<?= escape(__('collection:view')) ?>">
					<?php foreach ($page->viewLinks as $link): ?>
						<a
							class="view-toggle-link<?= $link['active'] ? ' is-active' : '' ?>"
							href="<?= escape($link['url']) ?>"
							hx-target="#main">
							<?= escape($link['label']) ?>
						</a>
					<?php endforeach ?>
				</nav>
			<?php endif ?>
			<?php if ($page->query->parent !== null): ?>
				<?php if ($page->parentEditUrl !== null): ?>
					<a class="cms-button secondary" href="<?= escape($page->parentEditUrl) ?>" hx-target="#main"><?= escape(
						__('collection:edit-parent'),
					) ?></a>
				<?php endif ?>
				<?php if ($page->parentTreeUrl !== null): ?>
					<a class="cms-button secondary" href="<?= escape($page->parentTreeUrl) ?>" hx-target="#main"><?= escape(
						__('collection:show-in-tree'),
					) ?></a>
				<?php endif ?>
			<?php endif ?>
			<?php foreach ($page->createLinks as $link): ?>
				<a
					class="cms-button primary"
					href="<?= escape($link['url']) ?>"
					hx-target="#main">
					<?= escape(__('collection:new', ['name' => $link['name']])) ?>
				</a>
			<?php endforeach ?>
		</div>
	</header>

	<div class="body">
		<div class="toolbar">
			<form
				class="search"
				method="get"
				action="<?= escape($page->path) ?>"
				hx-target="#main">
				<label class="sr-only" for="collection-search"><?= escape(
					__('collection:search', ['name' => $page->name]),
				) ?></label>
				<span class="icon" aria-hidden="true">⌕</span>
				<input
					id="collection-search"
					name="q"
					type="search"
					value="<?= escape($page->query->q) ?>"
					placeholder="<?= escape(__('collection:search-placeholder')) ?>" />
				<?php foreach ($page->searchFields as $field): ?>
					<input
						type="hidden"
						name="<?= escape($field['name']) ?>"
						value="<?= escape($field['value']) ?>" />
				<?php endforeach ?>
			</form>

			<?php if ($page->clearSearchUrl !== null): ?>
				<a class="cms-button secondary" href="<?= escape($page->clearSearchUrl) ?>" hx-target="#main"><?= escape(
					__('collection:clear-search'),
				) ?></a>
			<?php endif ?>

			<?php if ($page->query->parent !== null): ?>
				<div class="parent-context">
					<?php if ($page->parentType !== null): ?>
						<span class="type-pill"><?= escape($page->parentType) ?></span>
					<?php endif ?>
					<?php foreach ($page->parentStatus as $badge): ?>
						<span class="cms-status is-<?= escape($badge['kind']) ?>"><?= escape(
						$badge['label'],
					) ?></span>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</div>

		<div class="card">
			<?php if (count($page->rows) === 0): ?>
				<div class="empty">
					<div class="icon" aria-hidden="true">⌁</div>
					<strong><?= escape(__('collection:empty')) ?></strong>
					<?php if ($page->query->q !== ''): ?>
						<p><?= escape(__('collection:empty-filter-help')) ?></p>
					<?php else: ?>
						<p><?= escape(__('collection:empty-help')) ?></p>
					<?php endif ?>
				</div>
			<?php else: ?>
				<div class="scroll">
					<?php // Laid out as a grid, so the implicit table roles are gone and

					// have to be spelled out for assistive technology. ?>
					<table class="cms-list" role="table" style="--columns: <?= escape($columns) ?>">
						<thead role="rowgroup">
							<tr role="row">
								<?php foreach ($page->headers as $header): ?>
									<th class="<?= escape($header['class']) ?>" role="columnheader">
										<?php if ($header['url'] === null): ?>
											<span class="inner"><?= escape($header['label']) ?></span>
										<?php else: ?>
											<a
												class="inner"
												href="<?= escape($header['url']) ?>"
												hx-target="#main">
												<?= escape($header['label']) ?>
												<span class="sort" aria-hidden="true">⌃</span>
											</a>
										<?php endif ?>
									</th>
								<?php endforeach ?>
								<th class="col-status" role="columnheader"><?= escape(__('collection:status')) ?></th>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($page->rows as $row): ?>
								<?php $this->insert('collection/row', [
									'row' => $row,
									'treeMode' => $page->treeMode,
									'showChildren' => $page->showChildren,
									'chevronSvg' => $chevronSvg,
								]) ?>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>

			<footer class="foot">
				<span class="range"><?= escape(__('collection:showing', [
					'start' => $page->rangeStart,
					'end' => $page->rangeEnd,
					'total' => $page->total,
				])) ?></span>
				<nav class="pagination" aria-label="<?= escape(__('collection:pagination')) ?>">
					<?php if ($page->previousUrl !== null): ?>
						<a class="page-link" href="<?= escape($page->previousUrl) ?>" hx-target="#main"><?= escape(
							__('collection:previous'),
						) ?></a>
					<?php else: ?>
						<span class="page-link is-disabled"><?= escape(__('collection:previous')) ?></span>
					<?php endif ?>

					<span class="pages"><?= escape(__('collection:page', [
						'page' => $page->currentPage,
						'pages' => $page->pageCount,
					])) ?></span>

					<?php if ($page->nextUrl !== null): ?>
						<a class="page-link" href="<?= escape($page->nextUrl) ?>" hx-target="#main"><?= escape(
							__('collection:next'),
						) ?></a>
					<?php else: ?>
						<span class="page-link is-disabled"><?= escape(__('collection:next')) ?></span>
					<?php endif ?>
				</nav>
			</footer>
		</div>
	</div>
</div>

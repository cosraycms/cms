<?php

use function Cosray\escape;

$chevronSvgPath = __DIR__ . '/../icons/chevron.svg';
$chevronSvg = is_file($chevronSvgPath)
	? trim((string) file_get_contents($chevronSvgPath))
	: '';
$chevronSvg = str_replace(
	'<svg ',
	'<svg class="tree-chevron" aria-hidden="true" focusable="false" ',
	$chevronSvg,
);

if (!$boosted) {
	$this->layout('panel');
}
?>

<div id="main" class="page collection">
	<header class="topbar topbar-collection">
		<div class="inner">
			<form
				class="search"
				method="get"
				action="<?= escape($page->path) ?>"
				hx-target="#main">
				<label class="sr-only" for="collection-search">Search <?= escape($page->name) ?></label>
				<span class="search-icon" aria-hidden="true">⌕</span>
				<input
					id="collection-search"
					name="q"
					type="search"
					value="<?= escape($page->query->q) ?>"
					placeholder="Search entries …" />
				<?php foreach ($page->searchFields as $field): ?>
					<input
						type="hidden"
						name="<?= escape($field['name']) ?>"
						value="<?= escape($field['value']) ?>" />
				<?php endforeach ?>
			</form>

			<div class="topbar-actions">
				<?php if ($page->clearSearchUrl !== null): ?>
					<a class="cms-button secondary" href="<?= escape($page->clearSearchUrl) ?>" hx-target="#main">Clear search</a>
				<?php endif ?>
				<?php if ($page->query->parent === null): ?>
					<?php foreach ($page->createLinks as $link): ?>
						<a
							class="cms-button primary"
							href="<?= escape($link['url']) ?>"
							hx-target="#main">
							New <?= escape($link['name']) ?>
						</a>
					<?php endforeach ?>
				<?php endif ?>
			</div>
		</div>
	</header>

	<section class="content">
		<div class="page-head">
			<?php if ($page->rootUrl !== null): ?>
				<nav class="breadcrumb" aria-label="Breadcrumb">
					<a href="<?= escape($page->rootUrl) ?>" hx-target="#main"><?= escape($page->name) ?></a>
					<span aria-hidden="true">/</span>
					<span><?= escape($page->parentTitle ?? $page->query->parent) ?></span>
				</nav>
			<?php endif ?>
			<h1><?= escape($page->title) ?></h1>
			<span class="count-pill"><?= $page->total ?> <?= $page->total === 1
	? 'entry'
	: 'entries' ?></span>

			<?php if (count($page->viewLinks) > 0): ?>
				<nav class="view-toggle" aria-label="Collection view">
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
				<div class="parent-context">
					<div class="parent-summary">
						<?php if ($page->parentType !== null): ?>
							<span class="type-pill"><?= escape($page->parentType) ?></span>
						<?php endif ?>
						<?php foreach ($page->parentStatus as $badge): ?>
							<span class="status status-<?= escape($badge['kind']) ?>"><?= escape(
							$badge['label'],
						) ?></span>
						<?php endforeach ?>
					</div>
					<div class="parent-actions">
						<?php if ($page->parentEditUrl !== null): ?>
							<a class="cms-button secondary" href="<?= escape($page->parentEditUrl) ?>" hx-target="#main">Edit parent</a>
						<?php endif ?>
						<?php if ($page->parentTreeUrl !== null): ?>
							<a class="cms-button secondary" href="<?= escape($page->parentTreeUrl) ?>" hx-target="#main">Show in tree</a>
						<?php endif ?>
						<?php foreach ($page->createLinks as $link): ?>
							<a
								class="cms-button primary"
								href="<?= escape($link['url']) ?>"
								hx-target="#main">
								New <?= escape($link['name']) ?>
							</a>
						<?php endforeach ?>
					</div>
				</div>
			<?php endif ?>
		</div>

		<div class="collection-panel">
			<?php if (count($page->rows) === 0): ?>
				<div class="collection-empty">
					<div class="empty-icon" aria-hidden="true">⌁</div>
					<strong>No entries found.</strong>
					<?php if ($page->query->q !== ''): ?>
						<p>Try a different search or clear the current filter.</p>
					<?php else: ?>
						<p>This collection does not contain entries yet.</p>
					<?php endif ?>
				</div>
			<?php else: ?>
				<div class="tablewrap">
					<table class="collection-list">
						<thead>
							<tr>
								<?php foreach ($page->headers as $header): ?>
									<th class="<?= escape($header['class']) ?>">
										<?php if ($header['url'] === null): ?>
											<span class="th-inner"><?= escape($header['label']) ?></span>
										<?php else: ?>
											<a
												class="th-inner"
												href="<?= escape($header['url']) ?>"
												hx-target="#main">
												<?= escape($header['label']) ?>
												<span class="sort-ind" aria-hidden="true">⌃</span>
											</a>
										<?php endif ?>
									</th>
								<?php endforeach ?>
								<th class="col-status">Status</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($page->rows as $row): ?>
								<tr
									class="collection-row<?= $page->treeMode ? ' is-tree-row' : '' ?>"
									data-uid="<?= escape($row['uid']) ?>"
									data-depth="<?= (int) $row['depth'] ?>"
									data-last="<?= $row['last'] ? 'true' : 'false' ?>"
									style="--tree-depth: <?= (int) $row['depth'] ?>">
									<?php foreach ($row['cells'] as $index => $cell): ?>
										<td class="<?= escape($cell['class']) ?>" data-label="<?= escape($cell['label']) ?>">
											<?php if ($index === 0 && $page->showChildren): ?>
												<div class="tree-title<?= $page->treeMode ? '' : ' is-flat' ?>">
													<?php if ($page->treeMode): ?>
														<?php if ($row['childrenUrl'] !== null): ?>
															<a
																class="tree-toggle<?= $row['expanded'] ? ' is-open' : '' ?>"
																href="<?= escape($row['childrenUrl']) ?>"
																hx-target="#main"
																aria-expanded="<?= $row['expanded'] ? 'true' : 'false' ?>"
																aria-label="<?= $row['expanded'] ? 'Collapse' : 'Expand' ?> children of <?= escape(
															$cell['value'],
														) ?>">
																<?= $chevronSvg !== '' ? $chevronSvg : ($row['expanded'] ? '⌄' : '›') ?>
															</a>
														<?php else: ?>
															<span class="tree-toggle tree-spacer" aria-hidden="true"></span>
														<?php endif ?>
													<?php endif ?>
													<span class="node-dot<?= $row['published']
												? ' is-published'
												: '' ?>" aria-hidden="true"></span>
													<?php if ($cell['editUrl'] !== null): ?>
														<a
															class="collection-value collection-edit-link tree-label"
															href="<?= escape($cell['editUrl']) ?>"
															hx-target="#main">
															<?= escape($cell['value']) ?>
														</a>
													<?php else: ?>
														<span class="collection-value tree-label"><?= escape($cell['value']) ?></span>
													<?php endif ?>
													<?php if (
														$row['focusedChildrenUrl'] !== null
														|| count($row['childCreateLinks']) > 0
													): ?>
														<span class="tree-actions">
															<?php if ($row['focusedChildrenUrl'] !== null): ?>
																<a
																	class="tree-meta"
																	href="<?= escape($row['focusedChildrenUrl']) ?>"
																	hx-target="#main">
																	Children
																</a>
															<?php endif ?>
															<?php foreach ($row['childCreateLinks'] as $link): ?>
																<a
																	class="tree-create"
																	href="<?= escape($link['url']) ?>"
																	hx-target="#main"
																	aria-label="Create <?= escape($link['name']) ?> under <?= escape(
																$cell['value'],
															) ?>">
																	+ <?= escape($link['name']) ?>
																</a>
															<?php endforeach ?>
														</span>
													<?php endif ?>
												</div>
											<?php elseif ($cell['editUrl'] !== null): ?>
												<a
													class="collection-value collection-edit-link"
													href="<?= escape($cell['editUrl']) ?>"
													hx-target="#main">
													<?= escape($cell['value']) ?>
												</a>
											<?php else: ?>
												<span class="collection-value"><?= escape($cell['value']) ?></span>
											<?php endif ?>
										</td>
									<?php endforeach ?>
									<td class="collection-cell col-status" data-label="Status">
										<div class="status-list">
											<?php foreach ($row['status'] as $badge): ?>
												<span class="status status-<?= escape($badge['kind']) ?>"><?= escape(
												$badge['label'],
											) ?></span>
											<?php endforeach ?>
										</div>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>

			<footer class="list-foot">
				<span class="fcount">Showing <?= $page->rangeStart ?>–<?= $page->rangeEnd ?> of <?= $page->total ?></span>
				<nav class="pagination" aria-label="Pagination">
					<?php if ($page->previousUrl !== null): ?>
						<a class="page-link" href="<?= escape($page->previousUrl) ?>" hx-target="#main">Previous</a>
					<?php else: ?>
						<span class="page-link is-disabled">Previous</span>
					<?php endif ?>

					<span class="page-status">Page <?= $page->currentPage ?> of <?= $page->pageCount ?></span>

					<?php if ($page->nextUrl !== null): ?>
						<a class="page-link" href="<?= escape($page->nextUrl) ?>" hx-target="#main">Next</a>
					<?php else: ?>
						<span class="page-link is-disabled">Next</span>
					<?php endif ?>
				</nav>
			</footer>
		</div>
	</section>
</div>

<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('app');
}
?>

<div id="main" class="page collection-page">
	<header class="topbar topbar-collection">
		<div class="content">
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
					<a class="cms-button cms-button-secondary" href="<?= escape($page->clearSearchUrl) ?>" hx-target="#main">Clear search</a>
				<?php endif ?>
				<?php foreach ($page->createLinks as $link): ?>
					<a
						class="cms-button cms-button-primary"
						href="<?= escape($link['url']) ?>"
						hx-target="#main">
						New <?= escape($link['name']) ?>
					</a>
				<?php endforeach ?>
			</div>
		</div>
	</header>

	<section class="content">
		<div class="page-head">
			<?php if ($page->rootUrl !== null): ?>
				<nav class="breadcrumb" aria-label="Breadcrumb">
					<a href="<?= escape($page->rootUrl) ?>" hx-target="#main"><?= escape($page->name) ?></a>
					<span aria-hidden="true">/</span>
					<span><?= escape($page->query->parent) ?></span>
				</nav>
			<?php endif ?>
			<h1><?= escape($page->name) ?></h1>
			<span class="count-pill"><?= $page->total ?> <?= $page->total === 1 ? 'entry' : 'entries' ?></span>
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
								<?php if ($page->showChildren): ?>
									<th class="col-children">Children</th>
								<?php endif ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($page->rows as $row): ?>
								<tr class="collection-row" data-uid="<?= escape($row['uid']) ?>">
									<?php foreach ($row['cells'] as $cell): ?>
										<td class="<?= escape($cell['class']) ?>" data-label="<?= escape($cell['label']) ?>">
											<?php if ($cell['editUrl'] !== null): ?>
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
												<span class="status status-<?= escape($badge['kind']) ?>"><?= escape($badge['label']) ?></span>
											<?php endforeach ?>
										</div>
									</td>
									<?php if ($page->showChildren): ?>
										<td class="collection-cell col-children" data-label="Children">
											<div class="child-actions">
												<?php foreach ($row['childLinks'] as $link): ?>
													<a
														class="child-link"
														href="<?= escape($link['url']) ?>"
														hx-target="#main">
														<?= escape($link['label']) ?>
													</a>
												<?php endforeach ?>
												<?php if (count($row['childLinks']) === 0): ?>
													<span class="child-muted">—</span>
												<?php endif ?>
											</div>
										</td>
									<?php endif ?>
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

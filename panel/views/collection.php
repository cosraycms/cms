<?php

use function Cosray\escape;

$this->layout('layer/main');

$chevronSvgPath = __DIR__ . '/../icons/chevron.svg';
$chevronSvg = is_file($chevronSvgPath)
	? trim((string) file_get_contents($chevronSvgPath))
	: '';
$chevronSvg = str_replace(
	'<svg ',
	'<svg class="chevron" aria-hidden="true" focusable="false" ',
	$chevronSvg,
);

// Track sizing per column kind. Badges and row actions size to their content
// because the translation decides a pill's width; the title absorbs the slack,
// and past the sum of the floors the list scrolls sideways.
$track = static fn(string $kind): string => match ($kind) {
	'date' => ' minmax(9rem, auto)',
	'badge' => ' max-content',
	default => ' minmax(7rem, auto)',
};

$hasRowActions = false;

foreach ($page->table->rows as $row) {
	if ($row['focusedChildrenUrl'] !== null || count($row['childCreateLinks']) > 0) {
		$hasRowActions = true;

		break;
	}
}

$bulk = count($page->table->rows) > 0;
$notice = (array) $this->unwrap($notice ?? []);

$columns = ($bulk ? 'var(--cms-list-select-width) ' : '') . 'minmax(12rem, 2fr)';

foreach (array_slice((array) $this->unwrap($page->table->headers), 1) as $header) {
	$columns .= $track((string) ($header['kind'] ?? 'text'));
}

$columns .= ' max-content' . ($hasRowActions ? ' max-content' : '');
?>

<div class="page cms-collection">
	<header class="head">
		<div class="titles">
			<?php if ($page->parent !== null): ?>
				<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
					<a href="<?= escape($page->parent->rootUrl) ?>"><?= escape($page->name) ?></a>
					<span aria-hidden="true">/</span>
					<span><?= escape($page->parent->title ?? $page->parent->uid) ?></span>
				</nav>
			<?php endif ?>
			<div class="line">
				<h1><?= escape($page->title) ?></h1>
				<span class="cms-count"><?= escape(__n(
					'collection:entry-count',
					'collection:entry-count-plural',
					$page->pager->total,
				)) ?></span>
			</div>
		</div>

		<div class="actions">
			<?php if (count($page->viewLinks) > 0): ?>
				<nav class="view-toggle" aria-label="<?= escape(__('collection:view')) ?>">
					<?php foreach ($page->viewLinks as $link): ?>
						<a
							class="view-toggle-link<?= $link['active'] ? ' is-active' : '' ?>"
							href="<?= escape($link['url']) ?>">
							<?= escape($link['label']) ?>
						</a>
					<?php endforeach ?>
				</nav>
			<?php endif ?>
			<?php if ($page->parent !== null): ?>
				<a class="cms-button secondary" href="<?= escape($page->parent->editUrl) ?>"><?= escape(
					__('collection:edit-parent'),
				) ?></a>
				<a class="cms-button secondary" href="<?= escape($page->parent->treeUrl) ?>"><?= escape(
					__('collection:show-in-tree'),
				) ?></a>
			<?php endif ?>
			<?php foreach ($page->createLinks as $link): ?>
				<a
					class="cms-button primary"
					href="<?= escape($link['url']) ?>">
					<?= escape(__('collection:new', ['name' => $link['name']])) ?>
				</a>
			<?php endforeach ?>
		</div>
	</header>

	<div class="body">
		<?php if (count($notice) > 0): ?>
			<div class="cms-notice" role="status">
				<?php foreach ($notice as $message): ?>
					<p><?= escape((string) $message) ?></p>
				<?php endforeach ?>
			</div>
		<?php endif ?>

		<div class="toolbar">
			<form
				class="search"
				method="get"
				action="<?= escape($page->search->action) ?>">
				<label class="sr-only" for="collection-search"><?= escape(
					__('collection:search', ['name' => $page->name]),
				) ?></label>
				<span class="icon" aria-hidden="true">⌕</span>
				<input
					id="collection-search"
					name="q"
					type="search"
					value="<?= escape($page->search->value) ?>"
					placeholder="<?= escape(__('collection:search-placeholder')) ?>" />
				<?php foreach ($page->search->fields as $field): ?>
					<input
						type="hidden"
						name="<?= escape($field['name']) ?>"
						value="<?= escape($field['value']) ?>" />
				<?php endforeach ?>
			</form>

			<?php if ($page->search->clearUrl !== null): ?>
				<a class="cms-button secondary" href="<?= escape($page->search->clearUrl) ?>"><?= escape(
					__('collection:clear-search'),
				) ?></a>
			<?php endif ?>

			<?php if ($page->parent !== null): ?>
				<div class="parent-context">
					<?php if ($page->parent->type !== null): ?>
						<span class="type-pill"><?= escape($page->parent->type) ?></span>
					<?php endif ?>
					<?php foreach ($page->parent->status as $badge): ?>
						<span class="cms-status is-<?= escape($badge['kind']) ?>"><?= escape(
						$badge['label'],
					) ?></span>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</div>

		<?php if ($bulk): ?>
			<?php // The form element stays empty: checkboxes and action buttons
			// associate through form="collection-bulk" so the bar, the table
			// and the dialogs need no shared wrapper. Submit buttons carry
			// their endpoint as formaction; without one a submit has nowhere
			// valid to go, which is the intended dead end. ?>
			<form id="collection-bulk" method="post" hidden></form>
			<div class="bulk-bar" data-bulk-bar hidden>
				<div class="info">
					<output
						class="count"
						data-bulk-count
						data-label-one="<?= escape(__('bulk:selected')) ?>"
						data-label-many="<?= escape(__('bulk:selected-plural')) ?>"></output>
					<button type="button" class="clear" data-bulk-clear><?= escape(
						__('bulk:clear'),
					) ?></button>
				</div>
				<div class="actions">
					<?php if ($page->bulk['showPublished']): ?>
						<button
							type="submit"
							class="action"
							form="collection-bulk"
							formaction="<?= escape($page->bulk['publishUrl']) ?>"
							name="state"
							value="published">
							<?= escape(__('bulk:publish')) ?>
						</button>
						<button
							type="submit"
							class="action"
							form="collection-bulk"
							formaction="<?= escape($page->bulk['publishUrl']) ?>"
							name="state"
							value="draft">
							<?= escape(__('bulk:unpublish')) ?>
						</button>
					<?php endif ?>
					<button type="button" class="action" data-bulk-open="duplicate">
						<?= escape(__('bulk:duplicate')) ?>
					</button>
					<button type="button" class="action" data-bulk-open="delete">
						<?= escape(__('bulk:delete')) ?>
					</button>
				</div>
			</div>
		<?php endif ?>

		<div class="card">
			<?php if (count($page->table->rows) === 0): ?>
				<div class="empty">
					<div class="icon" aria-hidden="true">⌁</div>
					<strong><?= escape(__('collection:empty')) ?></strong>
					<?php if ($page->search->value !== ''): ?>
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
								<?php if ($bulk): ?>
									<th class="col-select" role="columnheader">
										<input
											type="checkbox"
											data-bulk-all
											aria-label="<?= escape(__('bulk:select-all')) ?>" />
									</th>
								<?php endif ?>
								<?php foreach ($page->table->headers as $header): ?>
									<th class="<?= escape($header['class']) ?>" role="columnheader">
										<?php if ($header['url'] === null): ?>
											<span class="inner"><?= escape($header['label']) ?></span>
										<?php else: ?>
											<a
												class="inner"
												href="<?= escape($header['url']) ?>">
												<?= escape($header['label']) ?>
												<span class="sort" aria-hidden="true">⌃</span>
											</a>
										<?php endif ?>
									</th>
								<?php endforeach ?>
								<th class="col-status" role="columnheader"><?= escape(__('collection:status')) ?></th>
								<?php if ($hasRowActions): ?>
									<th class="col-actions" role="columnheader"></th>
								<?php endif ?>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($page->table->rows as $row): ?>
								<?php $this->insert('collection/row', [
									'row' => $row,
									'treeMode' => $page->table->treeMode,
									'showChildren' => $page->table->showChildren,
									'chevronSvg' => $chevronSvg,
									'hasRowActions' => $hasRowActions,
									'bulk' => $bulk,
								]) ?>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>

			<footer class="foot">
				<span class="range"><?= escape(__('collection:showing', [
					'start' => $page->pager->rangeStart,
					'end' => $page->pager->rangeEnd,
					'total' => $page->pager->total,
				])) ?></span>
				<nav class="pagination" aria-label="<?= escape(__('collection:pagination')) ?>">
					<?php if ($page->pager->previousUrl !== null): ?>
						<a class="page-link" href="<?= escape($page->pager->previousUrl) ?>"><?= escape(
							__('collection:previous'),
						) ?></a>
					<?php else: ?>
						<span class="page-link is-disabled"><?= escape(__('collection:previous')) ?></span>
					<?php endif ?>

					<span class="pages"><?= escape(__('collection:page', [
						'page' => $page->pager->currentPage,
						'pages' => $page->pager->pageCount,
					])) ?></span>

					<?php if ($page->pager->nextUrl !== null): ?>
						<a class="page-link" href="<?= escape($page->pager->nextUrl) ?>"><?= escape(
							__('collection:next'),
						) ?></a>
					<?php else: ?>
						<span class="page-link is-disabled"><?= escape(__('collection:next')) ?></span>
					<?php endif ?>
				</nav>
			</footer>
		</div>
	</div>

	<?php if ($bulk): ?>
		<dialog class="cms-confirm" data-bulk-dialog="delete">
			<h2><?= escape(__('bulk:delete')) ?></h2>
			<p
				class="question"
				data-bulk-question
				data-label-one="<?= escape(__('bulk:confirm-delete')) ?>"
				data-label-many="<?= escape(__('bulk:confirm-delete-plural')) ?>"></p>
			<?php // data-bulk-gate: deleting a parent without this opt-in would
			// only be skipped server-side, so the confirm button stays locked
			// until the box is ticked. ?>
			<label class="children" data-bulk-children data-bulk-gate hidden>
				<input type="checkbox" name="children" value="1" form="collection-bulk" />
				<span><?= escape(__('bulk:delete-children')) ?></span>
			</label>
			<footer>
				<button type="button" class="cms-button secondary" data-bulk-close><?= escape(
					__('bulk:cancel'),
				) ?></button>
				<button
					type="submit"
					class="cms-button danger"
					form="collection-bulk"
					formaction="<?= escape($page->bulk['deleteUrl']) ?>"
					data-bulk-confirm>
					<?= escape(__('bulk:delete')) ?>
				</button>
			</footer>
		</dialog>

		<dialog class="cms-confirm" data-bulk-dialog="duplicate">
			<h2><?= escape(__('bulk:duplicate')) ?></h2>
			<p
				class="question"
				data-bulk-question
				data-label-one="<?= escape(__('bulk:confirm-duplicate')) ?>"
				data-label-many="<?= escape(__('bulk:confirm-duplicate-plural')) ?>"></p>
			<?php // No data-bulk-gate: duplicating a parent without its children
			// is a legitimate copy, so the checkbox is a plain option. ?>
			<label class="children" data-bulk-children hidden>
				<input type="checkbox" name="children" value="1" form="collection-bulk" />
				<span><?= escape(__('bulk:duplicate-children')) ?></span>
			</label>
			<footer>
				<button type="button" class="cms-button secondary" data-bulk-close><?= escape(
					__('bulk:cancel'),
				) ?></button>
				<button
					type="submit"
					class="cms-button primary"
					form="collection-bulk"
					formaction="<?= escape($page->bulk['duplicateUrl']) ?>"
					data-bulk-confirm>
					<?= escape(__('bulk:duplicate')) ?>
				</button>
			</footer>
		</dialog>
	<?php endif ?>
</div>

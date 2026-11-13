<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

$this->layout('layer/main');

$page = u($page);

// Track sizing per column kind. Badges and row actions size to their content
// because the translation decides a pill's width; the title absorbs the slack,
// and past the sum of the floors the list scrolls sideways.
$hasRowActions = false;

$arranged = false;

foreach ($page->table->rows as $row) {
	$arranged = $arranged || $row['group'] !== null;

	if ($row['group'] !== null || $row['focusedChildrenUrl'] !== null || count($row['childCreateLinks']) > 0) {
		$hasRowActions = true;
	}
}

$bulk = count($page->table->rows) > 0;
$notice = (array) u($notice ?? []);

$columns = $bulk ? 'var(--cms-list-select-width)' : '';

foreach ($page->table->headers as $header) {
	$columns .= $header['lead'] ? ' minmax(12rem, 2fr)' : match ($header['kind']) {
		'date' => ' minmax(9rem, auto)',
		'badge', 'status' => ' max-content',
		default => ' minmax(7rem, auto)',
	};
}

$columns = ltrim($columns);

$columns .= $hasRowActions ? ' max-content' : '';
?>

<div class="cms-page cms-collection">
	<header class="cms-page-head">
		<div class="cms-page-titles">
			<div class="cms-page-line">
				<?php if ($page->parent !== null): ?>
					<nav class="cms-page-breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
						<a href="<?= escape($page->parent->rootUrl) ?>"><?= escape($page->name) ?></a>
						<span class="cms-page-sep" aria-hidden="true">/</span>
						<span><?= escape($page->parent->title ?? $page->parent->uid) ?></span>
						<span class="cms-page-sep" aria-hidden="true">/</span>
					</nav>
				<?php endif ?>
				<h1><?= escape($page->title) ?></h1>
				<span class="cms-count"><?= escape(__n(
					'collection:entry-count',
					'collection:entry-count-plural',
					$page->pager->total,
				)) ?></span>
			</div>
		</div>

		<div class="cms-page-actions">
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
				<a class="cms-button cms-button-secondary" href="<?= escape($page->parent->editUrl) ?>"><?= escape(
					__('collection:edit-parent'),
				) ?></a>
				<a class="cms-button cms-button-secondary" href="<?= escape($page->parent->treeUrl) ?>"><?= escape(
					__('collection:show-in-tree'),
				) ?></a>
			<?php endif ?>
			<?php if (count($page->createLinks) === 1): ?>
				<a class="cms-button cms-button-primary" href="<?= escape($page->createLinks[0]['url']) ?>" data-create-dialog hx-boost="false">
					<?= escape(__('collection:new-entry')) ?>
				</a>
			<?php elseif (count($page->createLinks) > 1): ?>
				<button type="button" class="cms-button cms-button-primary" popovertarget="collection-create" aria-haspopup="menu">
					<?= escape(__('collection:new-entry')) ?>
					<?= \Cosray\Panel\Icon::render('chevron-down') ?>
				</button>
				<div id="collection-create" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
					<?php foreach ($page->createLinks as $link): ?>
						<a href="<?= escape($link['url']) ?>" data-create-dialog hx-boost="false"><?= escape($link['name']) ?></a>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</div>
	</header>

	<div class="body">
		<?php foreach ($notice as $toast): ?>
			<?php $this->include('component/toast', (array) u($toast)) ?>
		<?php endforeach ?>

		<div class="cms-page-toolbar">
			<form
				class="cms-page-search"
				method="get"
				action="<?= escape($page->search->action) ?>">
				<label class="cms-sr-only" for="collection-search"><?= escape(
					__('collection:search', ['name' => $page->name]),
				) ?></label>
				<span class="cms-page-search-icon" aria-hidden="true">⌕</span>
				<input
					id="collection-search"
					class="cms-input"
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
				<a class="cms-button cms-button-secondary" href="<?= escape($page->search->clearUrl) ?>"><?= escape(
					__('collection:clear-search'),
				) ?></a>
			<?php endif ?>

			<?php if ($page->parent !== null): ?>
				<div class="parent-context">
					<?php if ($page->parent->type !== null): ?>
						<span class="type-pill"><?= escape($page->parent->type) ?></span>
					<?php endif ?>
					<?php if ($page->parent->dots !== null): ?>
						<?php $this->include('component/status-dots', ['status' => $page->parent->dots, 'size' => 'sm']) ?>
					<?php endif ?>
					<?php foreach ($page->parent->status as $badge): ?>
						<span class="cms-status is-<?= escape($badge['kind']) ?>"><?= escape(
							$badge['label'],
						) ?></span>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</div>

		<?php if ($page->stages !== []): ?>
			<?php // The status filters, each with its count and the icon of its stage. ?>
			<nav class="cms-stage-filters" aria-label="<?= escape(__('collection:stages')) ?>">
				<?php foreach ($page->stages as $chip): ?>
					<?php
					[$stage, $state, $icon] = match ($chip['key']) {
						'draft' => ['draft', 'present', 'people'],
						'mine' => ['mine', 'present', 'person'],
						'unpublished' => ['main', 'unpublished', 'globe'],
						default => [null, null, null],
					};
					?>
					<a class="chip" href="<?= escape($chip['url']) ?>" <?= $chip['active'] ? 'aria-current="true"' : '' ?>>
						<?php if ($stage !== null): ?>
							<span class="mark" data-stage="<?= $stage ?>" data-state="<?= $state ?>" aria-hidden="true">
								<?= Icon::render($icon) ?>
							</span>
						<?php endif ?>
						<span class="label"><?= escape($chip['label']) ?></span>
						<span class="count"><?= (int) $chip['count'] ?></span>
					</a>
				<?php endforeach ?>
			</nav>
		<?php endif ?>

		<?php if ($bulk): ?>
			<?php // The form element stays empty: checkboxes and action buttons

			// associate through form="collection-bulk" so the bar, the table
			// and the dialogs need no shared wrapper. Submit buttons carry
			// their endpoint as formaction; without one a submit has nowhere
			// valid to go, which is the intended dead end. ?>
			<form id="collection-bulk" method="post" hidden><?php $this->include('component/csrf') ?></form>
			<div class="bulk-bar" data-bulk-bar hidden>
				<div class="info">
					<output class="count" data-bulk-count></output>
					<button type="button" class="clear" data-bulk-clear><?= escape(
						__('bulk:clear'),
					) ?></button>
				</div>
				<div class="actions">
					<?php if ($page->bulk['publishable']): ?>
						<button type="button" class="action" data-bulk-open="publish">
							<?= escape(__('bulk:publish')) ?>
						</button>
						<button type="button" class="action" data-bulk-open="unpublished">
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

		<?php if ($arranged): ?>
			<?php // A drop fills this and submits it, so it takes the same boosted
			// route as the move buttons. ?>
			<form id="collection-order" method="post" action="<?= escape($page->table->positionUrl) ?>" hidden data-order-form>
				<?php $this->include('component/csrf') ?>
				<input type="hidden" name="node" />
				<input type="hidden" name="before" />
				<input type="hidden" name="after" />
			</form>
		<?php endif ?>

		<div class="listing">
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
									<th
										class="<?= escape(trim($header['class'] . ($header['lead'] ? ' is-lead' : ''))) ?>"
										role="columnheader"
										<?php if ($header['ariaSort'] !== null): ?>
											aria-sort="<?= escape($header['ariaSort']) ?>"
										<?php endif ?>>
										<?php if ($header['label'] === ''): ?>
											<span class="cms-sr-only"><?= escape($header['name']) ?></span>
										<?php elseif ($header['url'] === null): ?>
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
								<?php if ($hasRowActions): ?>
									<th class="col-actions" role="columnheader"></th>
								<?php endif ?>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($page->table->rows as $row): ?>
								<?php $this->include('collection/row', [
									'row' => $row,
									'treeMode' => $page->table->treeMode,
									'showChildren' => $page->table->showChildren,
									'hasRowActions' => $hasRowActions,
									'bulk' => $bulk,
									'positionUrl' => $page->table->positionUrl,
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
		<?php $this->component('component/dialog', ['name' => 'bulk-delete', 'size' => 'compact', 'title' => __('bulk:delete')]) ?>
			<div class="modal-body cms-confirm">
			<p class="question" data-bulk-question></p>
			<?php // data-bulk-gate: deleting a parent without this opt-in would

			// only be skipped server-side, so the confirm button stays locked
			// until the box is ticked. ?>
			<label class="children" data-bulk-children data-bulk-gate hidden>
				<input type="checkbox" name="children" value="1" form="collection-bulk" />
				<span><?= escape(__('bulk:delete-children')) ?></span>
			</label>
			</div>
			<footer class="modal-footer">
				<button type="button" class="cms-button cms-button-secondary" data-dialog-close data-dialog-focus><?= escape(
					__('bulk:cancel'),
				) ?></button>
				<button
					type="submit"
					class="cms-button cms-button-danger cms-button-solid"
					form="collection-bulk"
					formaction="<?= escape($page->bulk['deleteUrl']) ?>"
					data-bulk-confirm>
					<?= escape(__('bulk:delete')) ?>
				</button>
			</footer>
		<?php $this->end() ?>

		<?php $this->component('component/dialog', ['name' => 'bulk-duplicate', 'size' => 'compact', 'title' => __('bulk:duplicate')]) ?>
			<div class="modal-body cms-confirm">
			<p class="question" data-bulk-question></p>
			<?php // No data-bulk-gate: duplicating a parent without its children

			// is a legitimate copy, so the checkbox is a plain option. ?>
			<label class="children" data-bulk-children hidden>
				<input type="checkbox" name="children" value="1" form="collection-bulk" />
				<span><?= escape(__('bulk:duplicate-children')) ?></span>
			</label>
			</div>
			<footer class="modal-footer">
				<button type="button" class="cms-button cms-button-secondary" data-dialog-close data-dialog-focus><?= escape(
					__('bulk:cancel'),
				) ?></button>
				<button
					type="submit"
					class="cms-button cms-button-primary"
					form="collection-bulk"
					formaction="<?= escape($page->bulk['duplicateUrl']) ?>"
					data-bulk-confirm>
					<?= escape(__('bulk:duplicate')) ?>
				</button>
			</footer>
		<?php $this->end() ?>

		<?php if ($page->bulk['publishable']): ?>
			<?php $this->component('component/dialog', ['name' => 'bulk-publish', 'size' => 'compact', 'title' => __('bulk:publish')]) ?>
				<div class="modal-body cms-confirm">
				<p class="question" data-bulk-question></p>
				<label class="children" data-bulk-children hidden>
					<input type="checkbox" name="children" value="1" form="collection-bulk" />
					<span><?= escape(__('bulk:publish-children')) ?></span>
				</label>
				<label class="children" data-bulk-option>
					<input type="checkbox" name="changes" value="1" form="collection-bulk" />
					<span><?= escape(__('bulk:publish-changes')) ?></span>
				</label>
				</div>
				<footer class="modal-footer">
					<button type="button" class="cms-button cms-button-secondary" data-dialog-close data-dialog-focus><?= escape(
						__('bulk:cancel'),
					) ?></button>
					<button
						type="submit"
						class="cms-button cms-button-primary"
						form="collection-bulk"
						formaction="<?= escape($page->bulk['publishUrl']) ?>"
						name="state"
						value="published"
						data-bulk-confirm>
						<?= escape(__('bulk:publish')) ?>
					</button>
				</footer>
			<?php $this->end() ?>

			<?php $this->component('component/dialog', ['name' => 'bulk-unpublished', 'size' => 'compact', 'title' => __('bulk:unpublish')]) ?>
				<div class="modal-body cms-confirm">
				<p class="question" data-bulk-question></p>
				<label class="children" data-bulk-children hidden>
					<input type="checkbox" name="children" value="1" form="collection-bulk" />
					<span><?= escape(__('bulk:unpublish-children')) ?></span>
				</label>
				</div>
				<footer class="modal-footer">
					<button type="button" class="cms-button cms-button-secondary" data-dialog-close data-dialog-focus><?= escape(
						__('bulk:cancel'),
					) ?></button>
					<button
						type="submit"
						class="cms-button cms-button-primary"
						form="collection-bulk"
						formaction="<?= escape($page->bulk['publishUrl']) ?>"
						name="state"
						value="unpublished"
						data-bulk-confirm>
						<?= escape(__('bulk:unpublish')) ?>
					</button>
				</footer>
			<?php $this->end() ?>
		<?php endif ?>
	<?php endif ?>
</div>

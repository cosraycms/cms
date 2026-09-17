<?php

use function Cosray\escape;

$this->layout('layer/main');

$rows = (array) $this->unwrap($rows);
$types = (array) $this->unwrap($types);
$search = (string) $search;
$type = $this->unwrap($type ?? null);
$total = (int) $total;
$previousUrl = $this->unwrap($previousUrl ?? null);
$nextUrl = $this->unwrap($nextUrl ?? null);
$notice = $this->unwrap($notice ?? null);
$listUrl = (string) $listUrl;
?>

<div class="page cms-collection cms-users">
	<header class="head">
		<div class="titles">
			<div class="line">
				<h1><?= escape(__('user:users')) ?></h1>
				<span class="cms-count"><?= escape(__n('user:count', 'user:count-plural', $total)) ?></span>
			</div>
		</div>

		<div class="actions">
			<?php if (count($types) > 1): ?>
				<nav class="view-toggle" aria-label="<?= escape(__('user:type')) ?>">
					<a class="view-toggle-link<?= $type === null ? ' is-active' : '' ?>" href="<?= escape($listUrl) ?>">
						<?= escape(__('user:all-types')) ?>
					</a>
					<?php foreach ($types as $link): ?>
						<a
							class="view-toggle-link<?= $link['active'] ? ' is-active' : '' ?>"
							href="<?= escape((string) $link['filterUrl']) ?>">
							<?= escape((string) $link['label']) ?>
						</a>
					<?php endforeach ?>
				</nav>
			<?php endif ?>
			<?php if (count($types) === 1): ?>
				<a class="cms-button primary" href="<?= escape((string) $types[0]['createUrl']) ?>">
					<?= escape(__('user:add')) ?>
				</a>
			<?php else: ?>
				<button type="button" class="cms-button primary" popovertarget="users-create" aria-haspopup="menu">
					<?= escape(__('user:add')) ?>
					<?= \Cosray\Panel\Icon::render('chevron-down') ?>
				</button>
				<div id="users-create" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
					<?php foreach ($types as $link): ?>
						<a href="<?= escape((string) $link['createUrl']) ?>"><?= escape((string) $link['label']) ?></a>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</div>
	</header>

	<div class="body">
		<?php if (is_string($notice)): ?>
			<div class="cms-notice" role="status">
				<p><?= escape($notice) ?></p>
			</div>
		<?php endif ?>

		<div class="toolbar">
			<form class="search" method="get" action="<?= escape($listUrl) ?>">
				<label class="sr-only" for="users-search"><?= escape(__('user:search')) ?></label>
				<span class="icon" aria-hidden="true">⌕</span>
				<input
					id="users-search"
					class="cms-input"
					name="q"
					type="search"
					value="<?= escape($search) ?>"
					placeholder="<?= escape(__('user:search')) ?>" />
				<?php if (is_string($type)): ?>
					<input type="hidden" name="type" value="<?= escape($type) ?>" />
				<?php endif ?>
			</form>
		</div>

		<div class="listing">
			<?php if ($rows === []): ?>
				<div class="empty">
					<strong><?= escape(__('user:empty')) ?></strong>
				</div>
			<?php else: ?>
				<div class="scroll">
					<?php // Laid out as a grid, so the table roles have to be spelled out. ?>
					<table
						class="cms-list"
						role="table"
						style="--columns: minmax(12rem, 2fr) minmax(12rem, 2fr) minmax(6rem, 1fr) minmax(8rem, 1fr) max-content">
						<thead role="rowgroup">
							<tr role="row">
								<th role="columnheader"><span class="inner"><?= escape(__('user:name')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('user:email')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('user:type')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('user:roles')) ?></span></th>
								<th class="col-status" role="columnheader"><?= escape(__('collection:status')) ?></th>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($rows as $row): ?>
								<tr class="row" role="row">
									<td class="cell" role="cell">
										<?php if (is_string($row['url'])): ?>
											<a class="value link" href="<?= escape($row['url']) ?>"><?= escape((string) $row['title']) ?></a>
										<?php else: ?>
											<span class="value"><?= escape((string) $row['title']) ?></span>
										<?php endif ?>
									</td>
									<td class="cell" role="cell"><span class="value"><?= escape((string) $row['email']) ?></span></td>
									<td class="cell" role="cell"><span class="value"><?= escape((string) $row['type']) ?></span></td>
									<td class="cell" role="cell"><span class="value"><?= escape(implode(', ', $row['roles'])) ?></span></td>
									<td class="cell col-status" role="cell">
										<span class="cms-status <?= $row['active'] ? 'is-published' : 'is-unpublished' ?>">
											<?= escape($row['active'] ? __('user:active') : __('user:inactive')) ?>
										</span>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>

			<footer class="foot">
				<span class="range"><?= escape(__('collection:showing', [
					'start' => (int) $rangeStart,
					'end' => (int) $rangeEnd,
					'total' => $total,
				])) ?></span>
				<nav class="pagination" aria-label="<?= escape(__('collection:pagination')) ?>">
					<?php if (is_string($previousUrl)): ?>
						<a class="page-link" href="<?= escape($previousUrl) ?>"><?= escape(__('collection:previous')) ?></a>
					<?php else: ?>
						<span class="page-link is-disabled"><?= escape(__('collection:previous')) ?></span>
					<?php endif ?>
					<?php if (is_string($nextUrl)): ?>
						<a class="page-link" href="<?= escape($nextUrl) ?>"><?= escape(__('collection:next')) ?></a>
					<?php else: ?>
						<span class="page-link is-disabled"><?= escape(__('collection:next')) ?></span>
					<?php endif ?>
				</nav>
			</footer>
		</div>
	</div>
</div>

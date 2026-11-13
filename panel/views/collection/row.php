<?php

use function Cosray\escape;

// One listing row. Shared by the collection and the styleguide so the sampler
// shows the real thing rather than a copy that drifts.
// Receives: row, treeMode, showChildren, hasRowActions, bulk, positionUrl.
// A row in a manual order carries its group (the parent's uid, or empty
// for the collection's top level), a grip and the move forms.

$row = (array) u($row);
$treeMode = (bool) $treeMode;
$showChildren = (bool) $showChildren;
$hasRowActions = (bool) ($hasRowActions ?? false);
$bulk = (bool) ($bulk ?? false);
$group = $row['group'] ?? null;
$positionUrl = (string) ($positionUrl ?? '');
$lead = array_values(array_filter((array) $row['cells'], static fn(array $cell): bool => $cell['lead'] ?? false))[0] ?? null;
$name = (string) ($lead['value'] ?? $row['uid']);
$grip = $group === null
	? ''
	: '<span class="cms-list-grip" data-order-grip aria-hidden="true">' . \Cosray\Panel\Icon::render('grip-vertical') . '</span>';
?>
<tr
	class="cms-list-row<?= $treeMode ? ' is-tree' : '' ?>"
	role="row"
	data-uid="<?= escape((string) $row['uid']) ?>"
	data-depth="<?= (int) $row['depth'] ?>"
	data-last="<?= $row['last'] ? 'true' : 'false' ?>"
	<?php if ($group !== null): ?>
		data-group="<?= escape((string) $group) ?>"
	<?php endif ?>
	<?php if ($row['moved'] ?? false): ?>
		data-moved
	<?php endif ?>
	style="--tree-depth: <?= (int) $row['depth'] ?>">
	<?php if ($bulk): ?>
		<td class="cms-list-cell cms-list-col-select" role="cell">
			<input
				type="checkbox"
				name="nodes[]"
				value="<?= escape((string) $row['uid']) ?>"
				form="collection-bulk"
				data-bulk-check
				<?= $row['hasChildren'] ?? false ? 'data-has-children' : '' ?>
				aria-label="<?= escape(__('bulk:select-row', ['name' => $name])) ?>" />
		</td>
	<?php endif ?>
	<?php foreach ((array) $row['cells'] as $cell): ?>
		<?php $isLead = (bool) ($cell['lead'] ?? false); ?>
		<td
			class="cms-list-cell <?= escape((string) $cell['class']) ?><?= $isLead ? ' is-lead' : '' ?>"
			role="cell"
			data-label="<?= escape((string) $cell['label']) ?>">
			<?php if (is_array($cell['status'] ?? null)): ?>
				<?php // A status column: its stages, then hidden or locked. ?>
				<div class="cms-list-status">
					<?php if ($cell['status']['stages'] !== []): ?>
						<?php $this->include('component/status-dots', [
							'status' => $cell['status'],
							'size' => 'sm',
							'look' => $cell['status']['look'],
						]) ?>
					<?php endif ?>
					<?php foreach ($cell['status']['badges'] as $badge): ?>
						<span class="cms-status is-<?= escape((string) $badge['kind']) ?>"><?= escape(
							(string) $badge['label'],
						) ?></span>
					<?php endforeach ?>
				</div>
			<?php elseif ($isLead && $showChildren): ?>
				<div class="cms-list-title<?= $treeMode ? '' : ' is-flat' ?>">
					<?php if ($treeMode): ?>
						<?php foreach ((array) ($row['guides'] ?? []) as $guide): ?>
							<span class="cms-list-guide" style="--guide-depth: <?= (int) $guide ?>" aria-hidden="true"></span>
						<?php endforeach ?>
						<?php if ($row['childrenUrl'] !== null): ?>
							<a
								class="cms-list-toggle<?= $row['expanded'] ? ' is-open' : '' ?>"
								href="<?= escape((string) $row['childrenUrl']) ?>"
								aria-expanded="<?= $row['expanded'] ? 'true' : 'false' ?>"
								data-collection-toggle
								aria-label="<?= escape(
									$row['expanded']
										? __('collection:collapse-children', ['name' => $cell['value']])
										: __('collection:expand-children', ['name' => $cell['value']]),
								) ?>">
								<?= \Cosray\Panel\Icon::render('chevron-right') ?>
							</a>
						<?php else: ?>
							<span class="cms-list-toggle is-spacer" aria-hidden="true"></span>
						<?php endif ?>
					<?php endif ?>
					<?= $grip ?>
					<?php if ($cell['editUrl'] !== null): ?>
						<a class="cms-list-value" href="<?= escape((string) $cell['editUrl']) ?>">
							<?= escape((string) $cell['value']) ?>
						</a>
					<?php else: ?>
						<span class="cms-list-value"><?= escape((string) $cell['value']) ?></span>
					<?php endif ?>
				</div>
			<?php elseif ($cell['editUrl'] !== null): ?>
				<?= $isLead ? $grip : '' ?>
				<a class="cms-list-value" href="<?= escape((string) $cell['editUrl']) ?>">
					<?= escape((string) $cell['value']) ?>
				</a>
			<?php else: ?>
				<span class="cms-list-value"><?= escape((string) $cell['value']) ?></span>
			<?php endif ?>
		</td>
	<?php endforeach ?>
	<?php if ($hasRowActions): ?>
		<td class="cms-list-cell cms-list-col-actions" role="cell">
			<span class="cms-list-row-actions">
				<?php if ($group !== null): ?>
					<?php foreach (['up' => $row['moveUp'], 'down' => $row['moveDown']] as $direction => $enabled): ?>
						<form class="cms-list-move" method="post" action="<?= escape($positionUrl) ?>" data-order-move="<?= $direction ?>">
							<?php $this->include('component/csrf') ?>
							<input type="hidden" name="node" value="<?= escape((string) $row['uid']) ?>" />
							<input type="hidden" name="move" value="<?= $direction ?>" />
							<button
								type="submit"
								class="cms-list-chip is-icon"
								aria-label="<?= escape($direction === 'up'
									? __('collection:move-up', ['name' => $name])
									: __('collection:move-down', ['name' => $name])) ?>"
								<?= $enabled ? '' : 'disabled' ?>>
								<?= \Cosray\Panel\Icon::render($direction === 'up' ? 'chevron-up' : 'chevron-down') ?>
							</button>
						</form>
					<?php endforeach ?>
				<?php endif ?>
				<?php if ($row['focusedChildrenUrl'] !== null): ?>
					<a
						class="cms-list-chip"
						href="<?= escape((string) $row['focusedChildrenUrl']) ?>">
						<?= escape(__('collection:children')) ?>
					</a>
				<?php endif ?>
				<?php foreach ((array) $row['childCreateLinks'] as $link): ?>
					<a
						class="cms-list-chip is-create"
						href="<?= escape((string) $link['url']) ?>"
						data-create-dialog
						hx-boost="false"
						aria-label="<?= escape(__('collection:create-under', [
							'type' => $link['name'],
							'name' => $name,
						])) ?>">
						<?= \Cosray\Panel\Icon::render('plus') ?> <?= escape((string) $link['name']) ?>
					</a>
				<?php endforeach ?>
			</span>
		</td>
	<?php endif ?>
</tr>

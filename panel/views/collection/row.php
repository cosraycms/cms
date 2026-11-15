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
$titleGrip = $bulk ? '' : $grip;
$creates = (array) $row['childCreateLinks'];
$path = (array) ($row['path'] ?? []);
$menuId = 'row-' . (string) $row['uid'];
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
			<?= $grip ?>
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
							'fixed' => $cell['status']['fixed'] ?? false,
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
							<span class="cms-list-guide" style="--guide-depth: <?= (int) $guide ?>" data-guide="<?= (int) $guide ?>" aria-hidden="true"></span>
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
					<?= $titleGrip ?>
					<?php if ($path !== []): ?>
						<span class="cms-list-path">
							<?php foreach ($path as $part): ?>
								<span><?= escape((string) $part) ?></span><span aria-hidden="true">›</span>
							<?php endforeach ?>
						</span>
					<?php endif ?>
					<?php if ($cell['editUrl'] !== null): ?>
						<a class="cms-list-value" href="<?= escape((string) $cell['editUrl']) ?>">
							<?= escape((string) $cell['value']) ?>
						</a>
					<?php else: ?>
						<span class="cms-list-value"><?= escape((string) $cell['value']) ?></span>
					<?php endif ?>
				</div>
			<?php elseif ($cell['editUrl'] !== null): ?>
				<?= $isLead ? $titleGrip : '' ?>
				<a class="cms-list-value" href="<?= escape((string) $cell['editUrl']) ?>">
					<?= escape((string) $cell['value']) ?>
				</a>
			<?php elseif (is_array($cell['avatar'] ?? null) && ($cell['avatarOnly'] ?? false)): ?>
				<?php $this->include('component/avatar', ['avatar' => $cell['avatar'], 'size' => 'sm', 'named' => true]) ?>
			<?php elseif (is_array($cell['avatar'] ?? null)): ?>
				<span class="cms-list-user">
					<?php $this->include('component/avatar', ['avatar' => $cell['avatar'], 'size' => 'sm']) ?>
					<span class="cms-list-value"><?= escape((string) $cell['value']) ?></span>
				</span>
			<?php else: ?>
				<span class="cms-list-value"><?= escape((string) $cell['value']) ?></span>
			<?php endif ?>
		</td>
	<?php endforeach ?>
	<?php if ($hasRowActions): ?>
		<td class="cms-list-cell cms-list-col-actions" role="cell">
			<span class="cms-list-row-actions">
				<?php if (count($creates) === 1): ?>
					<a
						class="cms-list-action is-add"
						href="<?= escape((string) $creates[0]['url']) ?>"
						data-create-dialog
						hx-boost="false"
						title="<?= escape((string) $creates[0]['name']) ?>"
						aria-label="<?= escape(__('collection:create-under', [
							'type' => $creates[0]['name'],
							'name' => $name,
						])) ?>"><?= \Cosray\Panel\Icon::render('plus') ?></a>
				<?php elseif (count($creates) > 1): ?>
					<button
						type="button"
						class="cms-list-action is-add"
						popovertarget="<?= escape($menuId) ?>-create"
						aria-haspopup="menu"
						aria-label="<?= escape(__('collection:create-child', ['name' => $name])) ?>"><?= \Cosray\Panel\Icon::render(
							'plus',
						) ?></button>
					<div id="<?= escape($menuId) ?>-create" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
						<?php foreach ($creates as $link): ?>
							<a href="<?= escape((string) $link['url']) ?>" data-create-dialog hx-boost="false"><?= escape(
								(string) $link['name'],
							) ?></a>
						<?php endforeach ?>
					</div>
				<?php endif ?>
				<?php if ($row['focusedChildrenUrl'] !== null || $group !== null): ?>
					<button
						type="button"
						class="cms-list-action is-more"
						popovertarget="<?= escape($menuId) ?>-more"
						aria-haspopup="menu"
						aria-label="<?= escape(__('collection:row-actions', ['name' => $name])) ?>"><?= \Cosray\Panel\Icon::render(
							'three-dots-vertical',
						) ?></button>
					<div id="<?= escape($menuId) ?>-more" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
						<?php if ($row['focusedChildrenUrl'] !== null): ?>
							<a href="<?= escape((string) $row['focusedChildrenUrl']) ?>"><?= escape(__('collection:children')) ?></a>
						<?php endif ?>
						<?php if ($group !== null): ?>
							<?php foreach (['up' => $row['moveUp'], 'down' => $row['moveDown']] as $direction => $enabled): ?>
								<form method="post" action="<?= escape($positionUrl) ?>" data-order-move="<?= $direction ?>">
									<?php $this->include('component/csrf') ?>
									<input type="hidden" name="node" value="<?= escape((string) $row['uid']) ?>" />
									<input type="hidden" name="move" value="<?= $direction ?>" />
									<button type="submit" <?= $enabled ? '' : 'disabled' ?>><?= escape($direction === 'up'
										? __('collection:move-up')
										: __('collection:move-down')) ?></button>
								</form>
							<?php endforeach ?>
						<?php endif ?>
					</div>
				<?php endif ?>
			</span>
		</td>
	<?php endif ?>
</tr>

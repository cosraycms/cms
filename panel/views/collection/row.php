<?php

use function Cosray\escape;

// One listing row. Shared by the collection and the styleguide so the sampler
// shows the real thing rather than a copy that drifts.
// Receives: row, treeMode, showChildren, hasRowActions, bulk.

$row = (array) $this->unwrap($row);
$treeMode = (bool) $treeMode;
$showChildren = (bool) $showChildren;
$hasRowActions = (bool) ($hasRowActions ?? false);
$bulk = (bool) ($bulk ?? false);
?>
<tr
	class="row<?= $treeMode ? ' is-tree' : '' ?>"
	role="row"
	data-uid="<?= escape((string) $row['uid']) ?>"
	data-depth="<?= (int) $row['depth'] ?>"
	data-last="<?= $row['last'] ? 'true' : 'false' ?>"
	style="--tree-depth: <?= (int) $row['depth'] ?>">
	<?php if ($bulk): ?>
		<td class="cell col-select" role="cell">
			<input
				type="checkbox"
				name="nodes[]"
				value="<?= escape((string) $row['uid']) ?>"
				form="collection-bulk"
				data-bulk-check
				<?= $row['hasChildren'] ?? false ? 'data-has-children' : '' ?>
				aria-label="<?= escape(__('bulk:select-row', [
					'name' => (string) ($row['cells'][0]['value'] ?? $row['uid']),
				])) ?>" />
		</td>
	<?php endif ?>
	<?php foreach ((array) $row['cells'] as $index => $cell): ?>
		<td
			class="cell <?= escape((string) $cell['class']) ?>"
			role="cell"
			data-label="<?= escape((string) $cell['label']) ?>">
			<?php if ($index === 0 && $showChildren): ?>
				<div class="title<?= $treeMode ? '' : ' is-flat' ?>">
					<?php if ($treeMode): ?>
						<?php if ($row['childrenUrl'] !== null): ?>
							<a
								class="toggle<?= $row['expanded'] ? ' is-open' : '' ?>"
								href="<?= escape((string) $row['childrenUrl']) ?>"
								aria-expanded="<?= $row['expanded'] ? 'true' : 'false' ?>"
								aria-label="<?= escape(
									$row['expanded']
										? __('collection:collapse-children', ['name' => $cell['value']])
										: __('collection:expand-children', ['name' => $cell['value']]),
								) ?>">
								<?php $this->insert('icon/chevron.svg') ?>
							</a>
						<?php else: ?>
							<span class="toggle is-spacer" aria-hidden="true"></span>
						<?php endif ?>
					<?php endif ?>
					<span class="dot<?= $row['published'] ? ' is-published' : '' ?>" aria-hidden="true"></span>
					<?php if ($cell['editUrl'] !== null): ?>
						<a class="value link" href="<?= escape((string) $cell['editUrl']) ?>">
							<?= escape((string) $cell['value']) ?>
						</a>
					<?php else: ?>
						<span class="value"><?= escape((string) $cell['value']) ?></span>
					<?php endif ?>
				</div>
			<?php elseif ($cell['editUrl'] !== null): ?>
				<a class="value link" href="<?= escape((string) $cell['editUrl']) ?>">
					<?= escape((string) $cell['value']) ?>
				</a>
			<?php else: ?>
				<span class="value"><?= escape((string) $cell['value']) ?></span>
			<?php endif ?>
		</td>
	<?php endforeach ?>
	<td class="cell col-status" role="cell" data-label="<?= escape(__('collection:status')) ?>">
		<div class="status-list">
			<?php foreach ((array) $row['status'] as $badge): ?>
				<span class="cms-status is-<?= escape((string) $badge['kind']) ?>"><?= escape(
					(string) $badge['label'],
				) ?></span>
			<?php endforeach ?>
		</div>
	</td>
	<?php if ($hasRowActions): ?>
		<td class="cell col-actions" role="cell">
			<span class="row-actions">
				<?php if ($row['focusedChildrenUrl'] !== null): ?>
					<a
						class="chip"
						href="<?= escape((string) $row['focusedChildrenUrl']) ?>">
						<?= escape(__('collection:children')) ?>
					</a>
				<?php endif ?>
				<?php foreach ((array) $row['childCreateLinks'] as $link): ?>
					<a
						class="chip is-create"
						href="<?= escape((string) $link['url']) ?>"
						aria-label="<?= escape(__('collection:create-under', [
							'type' => $link['name'],
							'name' => (string) $row['cells'][0]['value'],
						])) ?>">
						+ <?= escape((string) $link['name']) ?>
					</a>
				<?php endforeach ?>
			</span>
		</td>
	<?php endif ?>
</tr>

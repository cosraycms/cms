<?php

use function Cosray\escape;

// One listing row. Shared by the collection and the styleguide so the sampler
// shows the real thing rather than a copy that drifts.
// Receives: row, treeMode, showChildren, chevronSvg, hasRowActions.

$row = (array) $this->unwrap($row);
$treeMode = (bool) $treeMode;
$showChildren = (bool) $showChildren;
// unwrap, not cast: the markup has to reach the page as markup.
$chevronSvg = (string) $this->unwrap($chevronSvg);
$hasRowActions = (bool) ($hasRowActions ?? false);
?>
<tr
	class="row<?= $treeMode ? ' is-tree' : '' ?>"
	role="row"
	data-uid="<?= escape((string) $row['uid']) ?>"
	data-depth="<?= (int) $row['depth'] ?>"
	data-last="<?= $row['last'] ? 'true' : 'false' ?>"
	style="--tree-depth: <?= (int) $row['depth'] ?>">
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
								hx-target="#main"
								aria-expanded="<?= $row['expanded'] ? 'true' : 'false' ?>"
								aria-label="<?= escape($row['expanded']
									? __('collection:collapse-children', ['name' => $cell['value']])
									: __('collection:expand-children', ['name' => $cell['value']])) ?>">
								<?= $chevronSvg !== '' ? $chevronSvg : ($row['expanded'] ? '⌄' : '›') ?>
							</a>
						<?php else: ?>
							<span class="toggle is-spacer" aria-hidden="true"></span>
						<?php endif ?>
					<?php endif ?>
					<span class="dot<?= $row['published'] ? ' is-published' : '' ?>" aria-hidden="true"></span>
					<?php if ($cell['editUrl'] !== null): ?>
						<a class="value link" href="<?= escape((string) $cell['editUrl']) ?>" hx-target="#main">
							<?= escape((string) $cell['value']) ?>
						</a>
					<?php else: ?>
						<span class="value"><?= escape((string) $cell['value']) ?></span>
					<?php endif ?>
				</div>
			<?php elseif ($cell['editUrl'] !== null): ?>
				<a class="value link" href="<?= escape((string) $cell['editUrl']) ?>" hx-target="#main">
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
						href="<?= escape((string) $row['focusedChildrenUrl']) ?>"
						hx-target="#main">
						<?= escape(__('collection:children')) ?>
					</a>
				<?php endif ?>
				<?php foreach ((array) $row['childCreateLinks'] as $link): ?>
					<a
						class="chip is-create"
						href="<?= escape((string) $link['url']) ?>"
						hx-target="#main"
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

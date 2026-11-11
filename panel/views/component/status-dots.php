<?php

use function Cosray\escape;

// A node's stages as dots, from Panel\NodeStatus: in the editor head a
// button with a tooltip that opens the inspector's status tab, in lists an
// image with a short text. The editor's status.js moves the mine dot through
// the client states (saving, error) between saves.
// Receives: status (the editor's status view or a list row's), size (md, sm),
// id and oob for the editor head.

$status = (array) u($status);
$stages = (array) ($status['stages'] ?? []);
$size = (string) u($size ?? 'md');
$id = u($id ?? null);
$oob = (bool) (u($oob ?? null) ?? false);
$sentence = (string) ($status['sentence'] ?? '');
$dots = static function (array $stages): string {
	$html = '';

	foreach ($stages as $stage) {
		$html .= '<span class="dot" data-stage="' . escape((string) $stage['key']) . '" data-state="'
		. escape((string) $stage['state']) . '"></span>';
	}

	return $html;
};
?>
<?php if (is_string($id)): ?>
	<button
		type="button"
		id="<?= escape($id) ?>"
		class="cms-status-dots is-<?= escape($size) ?>"
		data-inspector-open="cms-inspector-tab-status"
		data-draft="<?= ($status['draft'] ?? false) ? 'true' : 'false' ?>"
		data-mine="<?= escape((string) ($status['mine'] ?? 'none')) ?>"
		aria-label="<?= escape(__('status:label', ['status' => $sentence])) ?>"
		<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
		<?= $dots($stages) ?>
		<span class="tooltip" aria-hidden="true">
			<?php foreach ($stages as $stage): ?>
				<span class="row" data-stage="<?= escape((string) $stage['key']) ?>">
					<span class="dot" data-stage="<?= escape((string) $stage['key']) ?>" data-state="<?= escape((string) $stage['state']) ?>"></span>
					<span><strong><?= escape((string) $stage['name']) ?><?= ($stage['badge'] ?? '') !== '' ? ' (' . escape((string) $stage['badge']) . ')' : '' ?></strong> · <span class="text"><?= escape((string) $stage['text']) ?></span></span>
				</span>
			<?php endforeach ?>
			<span class="hint"><?= escape(__('status:details')) ?></span>
		</span>
	</button>
<?php else: ?>
	<span
		class="cms-status-dots is-<?= escape($size) ?>"
		role="img"
		aria-label="<?= escape($sentence) ?>"
		title="<?= escape($sentence) ?>"><?= $dots($stages) ?></span>
<?php endif ?>

<?php

use Cosray\Panel\Icon;
use Cosray\Panel\NodeStatus;

use function Cosray\escape;

// A node's stages, from Panel\NodeStatus: in the editor head a button of
// icons with a tooltip that opens the inspector's status tab, in lists an
// image with a short text, as icons, dots, or compact: the main version's
// icon with a dot for each further stage that holds something. The
// editor's status.js moves the mine stage through the client states
// (saving, error) between saves.
// Receives: status (the editor's status view or a list row's), size (md, sm),
// look (icons, dots, compact), id and oob for the editor head.

$status = (array) u($status);
$stages = (array) ($status['stages'] ?? []);
$size = (string) u($size ?? 'md');
$id = u($id ?? null);
$oob = (bool) (u($oob ?? null) ?? false);
$sentence = (string) ($status['sentence'] ?? '');
$look = (string) u($look ?? 'icons');
$icons = NodeStatus::ICONS;
$marks = static function (array $stages) use ($icons): string {
	$html = '';

	foreach ($stages as $stage) {
		$key = (string) $stage['key'];
		$html .= '<span class="cms-stage-mark" data-stage="' . escape($key) . '" data-state="'
		. escape((string) $stage['state']) . '">' . Icon::render($icons[$key] ?? 'pencil') . '</span>';
	}

	return $html;
};
$dots = static function (array $stages): string {
	$html = '';

	foreach ($stages as $stage) {
		$html .= '<span class="cms-status-dots-dot" data-stage="' . escape((string) $stage['key']) . '" data-state="'
		. escape((string) $stage['state']) . '"></span>';
	}

	return $html;
};
$isMain = static fn(array $stage): bool => in_array($stage['key'], ['main', 'saved'], true);
$main = array_filter($stages, $isMain);
$look = $look === 'compact' && $main === [] ? 'dots' : $look;
$content = match ($look) {
	'compact' => $marks($main) . '<span class="cms-status-dots-pips">' . $dots(array_filter(
		$stages,
		static fn(array $stage): bool => !$isMain($stage) && $stage['state'] !== 'none',
	)) . '</span>',
	'dots' => $dots($stages),
	default => $marks($stages),
};
?>
<?php if (is_string($id)): ?>
	<button
		type="button"
		id="<?= escape($id) ?>"
		class="cms-status-dots"
		data-inspector-open="cms-inspector-tab-status"
		data-draft="<?= ($status['draft'] ?? false) ? 'true' : 'false' ?>"
		data-mine="<?= escape((string) ($status['mine'] ?? 'none')) ?>"
		aria-label="<?= escape(__('status:label', ['status' => $sentence])) ?>"
		<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
		<?= $marks($stages) ?>
		<span class="cms-status-dots-tooltip" aria-hidden="true">
			<?php foreach ($stages as $stage): ?>
				<span class="cms-status-dots-row" data-stage="<?= escape((string) $stage['key']) ?>">
					<?= $marks([$stage]) ?>
					<span><strong><?= escape((string) $stage['name']) ?><?= ($stage['badge'] ?? '') !== '' ? ' (' . escape((string) $stage['badge']) . ')' : '' ?></strong> · <span class="cms-status-dots-text"><?= escape((string) $stage['text']) ?></span></span>
				</span>
			<?php endforeach ?>
			<span class="cms-status-dots-hint"><?= escape(__('status:details')) ?></span>
		</span>
	</button>
<?php else: ?>
	<span
		class="cms-status-dots is-<?= escape($size) ?> is-<?= escape($look) ?>"
		role="img"
		aria-label="<?= escape($sentence) ?>"
		title="<?= escape($sentence) ?>"><?= $content ?></span>
<?php endif ?>

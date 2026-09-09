<?php

use function Cosray\escape;

// The blocks field's own settings: the gap between blocks, as one value
// or — behind a toggle, on a grid — split into row and column gap. Each
// entry is a neutral-locale map submitting as
// content[{field}][meta][{key}][zxx], as `field/meta` would render it;
// the toggle is unnamed and only decides which selects show. Splitting
// copies the gap into both axes and clears it, joining copies back.

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$meta = $this->unwrap($meta ?? null);
$meta = is_array($meta) ? $meta : [];
$id = (string) $id;
$columns = max(1, (int) ($columns ?? 1));

$fieldName = (string) ($field['name'] ?? '');
$nameRoot = (string) ($nameRoot ?? "content[{$fieldName}]");
$subs = [];

foreach ((array) ((($control['props'] ?? []))['fields'] ?? []) as $sub) {
	if (is_array($sub) && is_string($sub['key'] ?? null)) {
		$subs[$sub['key']] = $sub;
	}
}

$stored = static fn(string $key): string => (
	is_array($meta[$key] ?? null) && is_scalar($meta[$key]['zxx'] ?? null) ? (string) $meta[$key]['zxx'] : ''
);
$split = $columns > 1 && ($stored('rowGap') !== '' || $stored('columnGap') !== '');
$subField = ['required' => false, 'immutable' => false];
$render = function (string $key, bool $hidden) use ($subs, $subField, $id, $nameRoot, $stored): void {
	$sub = $subs[$key] ?? null;

	if ($sub === null) {
		return;
	}

	$subId = "{$id}-{$key}";
	?>
	<div class="field" data-gap-<?= $key === 'gap' ? 'single' : 'separate' ?><?= $hidden ? ' hidden' : '' ?>>
		<label class="cms-sub-label" for="<?= escape($subId) ?>">
			<?= escape((string) ($sub['label'] ?? $key)) ?>
		</label>
		<?php $this->insert('field/control', [
			'field' => $subField,
			'control' => (array) ($sub['control'] ?? []),
			'id' => $subId,
			'name' => "{$nameRoot}[meta][{$key}][zxx]",
			'value' => $stored($key),
			'data' => null,
		]) ?>
	</div>
	<?php
};
?>
<div class="fields" data-gap-scope>
	<?php $render('gap', $split) ?>
	<?php if ($columns > 1): ?>
		<label class="field split">
			<input type="checkbox" class="cms-checkbox" data-gap-split <?= $split ? 'checked' : '' ?> />
			<span><?= escape(__('field:gap-split')) ?></span>
		</label>
		<?php $render('rowGap', !$split) ?>
		<?php $render('columnGap', !$split) ?>
	<?php endif ?>
	<?php foreach ($subs as $key => $sub) {
		if (!in_array($key, ['gap', 'rowGap', 'columnGap'], true)) {
			$render($key, false);
		}
	} ?>
</div>

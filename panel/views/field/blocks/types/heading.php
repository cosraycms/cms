<?php

// The heading block as content: the level as a quiet chip reading "H2"
// before the text, the text at that level's size — the stylesheet reads
// the selected option. Both sub-fields go through the regular wrapper
// for their names, locale variants and errors; the labels are for
// screen readers only. Receives what row-fields receives.

$type = (array) $this->unwrap($type);
$subs = [];

foreach ((array) ($type['fields'] ?? []) as $sub) {
	if (is_array($sub) && is_string($sub['name'] ?? null) && !($sub['hidden'] ?? false)) {
		$subs[$sub['name']] = $sub;
	}
}
?>
<div class="heading">
	<?php foreach (['level', 'text'] as $part): ?>
		<?php if (isset($subs[$part])): ?>
			<div class="<?= $part ?>">
				<?php $this->insert('field/row-fields/field', ['sub' => $subs[$part], 'labels' => false]) ?>
			</div>
		<?php endif ?>
	<?php endforeach ?>
</div>

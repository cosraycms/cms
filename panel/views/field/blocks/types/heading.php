<?php

use Cosray\Panel\Icon;

$type = (array) $this->unwrap($type);
$subs = [];

foreach ((array) ($type['fields'] ?? []) as $sub) {
	if (is_array($sub) && is_string($sub['name'] ?? null) && !($sub['hidden'] ?? false)) {
		$subs[$sub['name']] = $sub;
	}
}

$fieldsData = (array) ($this->unwrap($fieldsData ?? null) ?? []);
$level = (string) ($fieldsData['level']['value']['zxx'] ?? '1');
$levelId = (string) $this->unwrap($rowId) . '-level-zxx';
$disabled = (bool) ($this->unwrap($readonly ?? null) ?? false) || ($subs['level']['immutable'] ?? false);
?>
<div class="heading" data-heading>
	<?php if (isset($subs['text'])): ?>
		<div class="text">
			<?php $this->insert('field/row-fields/field', ['sub' => $subs['text'], 'labels' => false]) ?>
		</div>
	<?php endif ?>
	<?php if (isset($subs['level'])): ?>
		<div class="level">
			<div hidden>
				<?php $this->insert('field/row-fields/field', ['sub' => $subs['level'], 'labels' => false]) ?>
			</div>
			<button
				type="button"
				class="level-button"
				popovertarget="<?= $this->escape($levelId . '-menu') ?>"
				aria-haspopup="menu"
				aria-expanded="false"
				aria-labelledby="<?= $this->escape($levelId . '-label ' . $levelId . '-caption') ?>"
				<?= $disabled ? 'disabled' : '' ?>>
				<span id="<?= $this->escape($levelId . '-caption') ?>" data-heading-caption>H<?= $this->escape($level) ?></span>
			</button>
			<div
				id="<?= $this->escape($levelId . '-menu') ?>"
				class="cms-action-menu"
				popover="auto"
				data-action-menu
				data-align="end">
				<?php foreach (range(1, 6) as $choice): ?>
					<button
						type="button"
						role="menuitemradio"
						aria-checked="<?= (string) $choice === $level ? 'true' : 'false' ?>"
						class="<?= (string) $choice === $level ? 'is-active' : '' ?>"
						data-heading-level="<?= $choice ?>"
						<?= $disabled ? 'disabled' : '' ?>>
						<?= Icon::render('type-h' . $choice) ?>
						<?= $this->escape(__('block:heading-choice', ['level' => $choice])) ?>
					</button>
				<?php endforeach ?>
			</div>
		</div>
	<?php endif ?>
</div>

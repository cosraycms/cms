<?php

$commonChoices = (array) $this->unwrap($commonChoices);
$more = (bool) $this->unwrap($more);
$id = (string) $this->unwrap($id);
?>
<button type="button" class="inserter" popovertarget="<?= $this->escape($id) ?>"
	aria-haspopup="menu" aria-label="<?= $this->escape(__('field:add-block')) ?>" title="<?= $this->escape(__(
		'field:add-block',
	)) ?>">
	<?= \Cosray\Panel\Icon::render('plus') ?>
</button>
<div id="<?= $this->escape($id) ?>" class="cms-action-menu cms-block-picker" popover="auto" data-action-menu>
	<div class="choices">
		<?php foreach ($commonChoices as $index => $choice): ?>
			<?php $choiceId = "{$id}-choice-{$index}"; ?>
			<div class="choice" role="group" aria-labelledby="<?= $this->escape($choiceId) ?>">
				<span class="caption" id="<?= $this->escape($choiceId) ?>">
					<span class="icon" aria-hidden="true"><?= $choice['icon'] ?></span>
					<span><?= $this->escape($choice['label']) ?></span>
				</span>
				<span class="actions">
					<?php foreach (['before' => __('field:before'), 'after' => __('field:after')] as $position => $label): ?>
						<?php $buttonId = "{$choiceId}-{$position}"; ?>
						<button type="button" class="cms-button secondary small"
							id="<?= $this->escape($buttonId) ?>"
							aria-labelledby="<?= $this->escape("{$buttonId} {$choiceId}") ?>"
							data-repeater-add="<?= $this->escape($choice['type']) ?>"
							data-repeater-insert="<?= $this->escape($position) ?>">
							<?= $this->escape($label) ?>
						</button>
					<?php endforeach ?>
				</span>
			</div>
		<?php endforeach ?>
	</div>
	<?php if ($more): ?>
		<hr />
		<button type="button" data-block-catalog-open data-repeater-insert="before">
			<?= \Cosray\Panel\Icon::render('collection') ?>
			<?= $this->escape(__('field:more-blocks')) ?>
		</button>
	<?php endif ?>
</div>

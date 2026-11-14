<?php

$choices = (array) u($choices);
$id = (string) u($id);
$field = (array) u($field);
$label = (string) ($field['label'] ?? $field['name'] ?? '');
$title = __('field:add-block') . ($label !== '' ? ' — ' . $label : '');
?>
<template data-block-catalog>
	<?php $this->include('component/modal-header', ['title' => $title]) ?>
	<div class="cms-modal-body cms-block-catalog">
		<label class="cms-block-catalog-search">
			<?= $this->escape(__('field:search-blocks')) ?>
			<input type="search" class="cms-input" data-block-search data-dialog-focus autocomplete="off" />
		</label>
		<p class="cms-block-catalog-status" role="status" aria-live="polite" aria-atomic="true" data-block-results></p>
		<div class="cms-block-catalog-results">
			<?php foreach (array_values($choices) as $index => $choice): ?>
				<?php $choiceId = "{$id}-catalog-choice-{$index}"; ?>
				<div class="cms-block-catalog-choice"
					data-block-choice="<?= $this->escape($choice['type']) ?>"
					data-handle="<?= $this->escape($choice['handle']) ?>"
					aria-labelledby="<?= $this->escape($choiceId) ?>"
					tabindex="-1">
					<span class="cms-block-catalog-select">
						<span class="cms-block-catalog-icon" aria-hidden="true"><?= $choice['icon'] ?></span>
						<span id="<?= $this->escape($choiceId) ?>"><?= $this->escape($choice['label']) ?></span>
					</span>
					<span class="cms-block-actions" hidden>
						<?php foreach (['before' => __('field:before'), 'after' => __('field:after')] as $position => $label): ?>
							<?php $buttonId = "{$choiceId}-{$position}"; ?>
							<button type="button" class="cms-button cms-button-secondary cms-button-small"
								id="<?= $this->escape($buttonId) ?>"
								aria-labelledby="<?= $this->escape("{$buttonId} {$choiceId}") ?>"
								data-block-insert="<?= $this->escape($position) ?>"
								tabindex="-1">
								<?= $this->escape($label) ?>
							</button>
						<?php endforeach ?>
					</span>
				</div>
			<?php endforeach ?>
		</div>
	</div>
</template>

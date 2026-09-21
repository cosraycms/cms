<?php

$choices = (array) $this->unwrap($choices);
$id = (string) $this->unwrap($id);
$field = (array) $this->unwrap($field);
$label = (string) ($field['label'] ?? $field['name'] ?? '');
$title = __('field:add-block') . ($label !== '' ? ' — ' . $label : '');
?>
<template data-block-catalog>
	<?php $this->insert('component/modal-header', ['title' => $title]) ?>
	<div class="modal-body cms-block-catalog">
		<label class="search">
			<?= $this->escape(__('field:search-blocks')) ?>
			<input type="search" class="cms-input" data-block-search data-dialog-focus autocomplete="off" />
		</label>
		<?php // The client fills :count in the plural form as the search narrows. ?>
		<p class="status" role="status" aria-live="polite" aria-atomic="true"
			data-block-results
			data-one="<?= $this->escape(__n('field:block-results', 'field:block-results-plural', 1)) ?>"
			data-count="<?= $this->escape(__n('field:block-results', 'field:block-results-plural', 2, ['count' => ':count'])) ?>"
			data-empty="<?= $this->escape(__('field:no-block-results')) ?>"></p>
		<div class="results">
			<?php foreach (array_values($choices) as $index => $choice): ?>
				<?php $choiceId = "{$id}-catalog-choice-{$index}"; ?>
				<div class="choice"
					data-block-choice="<?= $this->escape($choice['type']) ?>"
					data-handle="<?= $this->escape($choice['handle']) ?>"
					aria-labelledby="<?= $this->escape($choiceId) ?>"
					tabindex="-1">
					<span class="select">
						<span class="icon" aria-hidden="true"><?= $choice['icon'] ?></span>
						<span id="<?= $this->escape($choiceId) ?>"><?= $this->escape($choice['label']) ?></span>
					</span>
					<span class="cms-block-actions" hidden>
						<?php foreach (['before' => __('field:before'), 'after' => __('field:after')] as $position => $label): ?>
							<?php $buttonId = "{$choiceId}-{$position}"; ?>
							<button type="button" class="cms-button secondary small"
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

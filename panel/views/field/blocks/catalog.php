<?php

$choices = (array) $this->unwrap($choices);
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
			<?php foreach ($choices as $choice) {
				$this->insert('field/blocks/choice', ['choice' => $choice, 'insert' => null]);
			} ?>
		</div>
	</div>
</template>

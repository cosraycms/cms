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
		<p class="status" role="status" aria-live="polite" aria-atomic="true"
			data-block-results data-count="<?= $this->escape(__('field:block-results')) ?>"
			data-empty="<?= $this->escape(__('field:no-block-results')) ?>"></p>
		<div class="results">
			<?php foreach ($choices as $choice) {
				$this->insert('field/blocks/choice', ['choice' => $choice, 'insert' => null]);
			} ?>
		</div>
	</div>
</template>

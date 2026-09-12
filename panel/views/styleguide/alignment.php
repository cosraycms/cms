<?php

$alignmentFields = (array) $this->unwrap($alignmentFields);
?>
<div class="cms-node">
	<div class="pane">
		<div class="inner">
			<form id="node-editor-form" class="sheet" onsubmit="event.preventDefault()">
				<div class="sample">
					<button
						type="button"
						class="cms-button secondary"
						onclick="document.getElementById('editor-errors').replaceWith(document.getElementById('alignment-errors').content.cloneNode(true)); document.dispatchEvent(new CustomEvent('htmx:after:swap'))">
						Show validation errors
					</button>
				</div>
				<div id="editor-errors" class="errors" tabindex="-1" hidden></div>
				<template id="alignment-errors">
					<div id="editor-errors" class="errors" tabindex="-1">
						<ul>
							<li><button type="button" data-error-path='["content","alignmentShort","value","zxx"]'>Enter a value.</button></li>
							<li><button type="button" data-error-path='["content","alignmentShort","value","zxx"]'>This second message stays with the same input.</button></li>
							<li><button type="button" data-error-path='["content","alignmentDate","value","zxx"]'>Enter a valid date.</button></li>
						</ul>
					</div>
				</template>
				<div class="cms-fields" data-sample="fields:alignment">
					<?php foreach ($alignmentFields as $field): ?>
						<?php $this->insert('field/item', [
							'field' => $field,
							'content' => [],
							'locales' => $locales,
							'defaultLocale' => $defaultLocale,
							'uid' => 'styleguide',
							'assets' => [],
							'pathSourceFields' => [],
							'globalLocales' => true,
						]) ?>
					<?php endforeach ?>
				</div>
			</form>
		</div>
	</div>
</div>

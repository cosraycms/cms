<?php

$alignmentFields = (array) u($alignmentFields);
?>
<div class="cms-node">
	<div class="cms-node-pane">
		<div class="cms-node-pane-scroll">
			<div class="cms-node-column">
				<div id="editor-errors" class="cms-errors" tabindex="-1" hidden></div>
				<template id="alignment-errors">
					<div id="editor-errors" class="cms-errors" tabindex="-1" role="alert">
						<span class="cms-errors-mark" aria-hidden="true">!</span>
						<div class="cms-errors-summary">
							<p class="cms-errors-title">Entry was not saved</p>
							<ul class="cms-errors-issues">
								<li><button type="button" data-error-path='["content","alignmentShort","value","zxx"]' data-error-code="rule.required">Short is required</button></li>
								<li><button type="button" data-error-path='["content","alignmentShort","value","zxx"]' data-error-code="rule.regex">This second message stays with the same input.</button></li>
								<li><button type="button" data-error-path='["content","alignmentDate","value","zxx"]' data-error-code="rfc3339">Enter a valid date.</button></li>
								<li>A message without a field lands in the summary only.</li>
							</ul>
						</div>
					</div>
				</template>
				<form id="node-editor-form" class="cms-node-sheet" onsubmit="event.preventDefault()">
					<div class="cms-styleguide-sample">
						<button
							type="button"
							class="cms-button cms-button-secondary"
							onclick="document.getElementById('editor-errors').replaceWith(document.getElementById('alignment-errors').content.cloneNode(true)); document.dispatchEvent(new CustomEvent('htmx:after:swap'))">
							Show validation errors
						</button>
					</div>
					<div class="cms-fields" data-sample="fields:alignment">
						<?php foreach ($alignmentFields as $field): ?>
							<?php $this->include('field/item', [
								'field' => $field,
								'content' => [],
								'locales' => $locales,
								'defaultLocale' => $defaultLocale,
								'uid' => 'styleguide',
								'assets' => [],
								'pathSourceFields' => [],
							]) ?>
						<?php endforeach ?>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

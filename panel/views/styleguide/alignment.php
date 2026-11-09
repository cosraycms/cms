<?php

$alignmentFields = (array) u($alignmentFields);
?>
<div class="cms-node">
	<div class="pane">
		<div class="pane-scroll">
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
						<div id="editor-errors" class="errors" tabindex="-1" role="alert">
							<span class="mark" aria-hidden="true">!</span>
							<div class="summary">
								<p class="title">Entry was not saved</p>
								<ul class="issues">
									<li><button type="button" data-error-path='["content","alignmentShort","value","zxx"]' data-error-code="rule.required">Short is required</button></li>
									<li><button type="button" data-error-path='["content","alignmentShort","value","zxx"]' data-error-code="rule.regex">This second message stays with the same input.</button></li>
									<li><button type="button" data-error-path='["content","alignmentDate","value","zxx"]' data-error-code="rfc3339">Enter a valid date.</button></li>
									<li>A message without a field lands in the summary only.</li>
								</ul>
							</div>
						</div>
					</template>
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

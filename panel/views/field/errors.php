<?php $errorList = $this->unwrap($errors ?? []) ?>
<?php if (is_array($errorList) && $errorList !== []): ?>
	<ul class="field-errors" id="<?= $inputId ?>-errors">
		<?php foreach ($errorList as $error): ?>
			<li><?= $this->escape((string) $error) ?></li>
		<?php endforeach ?>
	</ul>
<?php endif ?>

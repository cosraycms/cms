<?php $subs = (array) u($subs); ?>
<div class="test-callout">
	<?php $this->include('field/row-fields/field', ['sub' => $subs['text'], 'labels' => false]) ?>
</div>

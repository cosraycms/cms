<?php

use function Cosray\escape;

// Meta editor rendered inside the field's meta dialog: the metaControl
// group's sub-control keys name the meta entries; every entry is a
// neutral-locale map and submits as content[{field}][meta][{key}][zxx]
// through the same merge patch as everything else.

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$meta = $this->unwrap($meta ?? null);
$meta = is_array($meta) ? $meta : [];
$id = (string) $id;

$fieldName = (string) ($field['name'] ?? '');
$subs = (array) ((($control['props'] ?? []))['fields'] ?? []);
$subField = ['required' => false, 'immutable' => false];
?>
<div class="cms-meta-fields">
	<?php foreach ($subs as $sub): ?>
		<?php

		$key = (string) ($sub['key'] ?? '');
		$subId = "{$id}-{$key}";
		?>
		<div class="cms-meta-field">
			<label class="cms-sub-label" for="<?= escape($subId) ?>">
				<?= escape((string) ($sub['label'] ?? $key)) ?>
			</label>
			<?php $this->insert('field/control', [
				'field' => $subField,
				'control' => (array) ($sub['control'] ?? []),
				'id' => $subId,
				'name' => "content[{$fieldName}][meta][{$key}][zxx]",
				'value' => is_array($meta[$key] ?? null) ? $meta[$key]['zxx'] ?? null : null,
				'data' => null,
			]) ?>
		</div>
	<?php endforeach ?>
</div>

<?php

use Cosray\Panel\EntrySummary;
use Cosray\Panel\RowLocales;

$index = $this->unwrap($index);
$rowData = $this->unwrap($rowData ?? null);
$rowData = is_array($rowData) ? $rowData : null;
$entryType = (array) $this->unwrap($entryType);
$name = (string) $this->unwrap($name);
$id = (string) $this->unwrap($id);
$assets = (array) ($this->unwrap($assets ?? null) ?? []);
$defaultLocale = (string) $this->unwrap($defaultLocale);

$rowName = "{$name}[{$index}]";
$rowId = "{$id}-{$index}";
$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
$fieldsData = is_array($rowData['fields'] ?? null) ? $rowData['fields'] : [];
$label = (string) ($entryType['label'] ?? __('field:entry'));
$summary = EntrySummary::of($entryType, $fieldsData, $assets, $defaultLocale);

// Stored rows start collapsed; a stamped row is empty and wants input.
$open = $rowData === null;
$ownsLocales = RowLocales::owned($entryType, count((array) $this->unwrap($locales)));
?>
<div class="entry" data-repeater-row <?= $ownsLocales ? 'data-locale-scope' : '' ?>>
	<div class="summary">
		<span class="grip" data-repeater-grip title="<?= $this->escape(__('field:drag-entry')) ?>">
			<?php $this->insert('icon/grip.svg') ?>
		</span>
		<button
			type="button"
			class="opener"
			data-repeater-collapse
			aria-expanded="<?= $open ? 'true' : 'false' ?>"
			aria-controls="<?= $this->escape("{$rowId}-form") ?>">
			<?php if ($summary->hasImage): ?>
				<span class="thumb">
					<?php if ($summary->thumb !== null): ?>
						<img src="<?= $this->escape($summary->thumb) ?>" alt="" />
					<?php endif ?>
				</span>
			<?php endif ?>
			<span class="texts">
				<?php // Each line names the sub-field it came from, so the

				// behavior refreshes only that line while its input changes. ?>
				<span
					class="primary"
					data-repeater-title="<?= $this->escape((string) $summary->primaryField) ?>"
					data-fallback="<?= $this->escape($label) ?>"><?= $this->escape(
						$summary->primary ?? $label,
					) ?></span>
				<span
					class="secondary"
					data-repeater-subtitle="<?= $this->escape((string) $summary->secondaryField) ?>"><?= $this->escape(
						(string) $summary->secondary,
					) ?></span>
			</span>
		</button>
		<?php if ($ownsLocales) {
			$this->insert('field/row-locales');
		} ?>
		<details class="kebab" data-repeater-menu>
			<summary aria-label="<?= $this->escape(__('field:entry-actions')) ?>"></summary>
			<div class="kebab-menu">
				<button type="button" data-repeater-move="up">
					<?= $this->escape(__('common:move-up')) ?>
				</button>
				<button type="button" data-repeater-move="down">
					<?= $this->escape(__('common:move-down')) ?>
				</button>
				<button type="button" data-repeater-remove>
					<?= $this->escape(__('field:remove-entry')) ?>
				</button>
			</div>
		</details>
	</div>
	<input
		type="hidden"
		data-repeater-uid
		name="<?= $this->escape("{$rowName}[uid]") ?>"
		value="<?= $this->escape($uid) ?>" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[type]") ?>"
		value="<?= $this->escape((string) $entryType['type']) ?>" />
	<div
		class="form cms-fields"
		id="<?= $this->escape("{$rowId}-form") ?>"
		data-repeater-body
		<?= $open ? '' : 'hidden' ?>>
		<?php $this->insert('field/row-fields', [
			'type' => $entryType,
			'ownsLocales' => $ownsLocales,
			'fieldsData' => $fieldsData,
			'rowName' => $rowName,
			'rowId' => $rowId,
		]) ?>
	</div>
</div>

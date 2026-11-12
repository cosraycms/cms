<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The dialog that creates a node from its required fields, loaded into a
// modal by create.js. Its form takes the editor form's id, so the save
// transport, the submit guard and the validation display work as in the
// editor; store() answers a valid submission by opening the editor.
// Receives: the create editor's context.

$links = u($links);
$node = (array) u($node);
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$system = (array) u($system);
$panelBase = (string) u($panelBase);
$pathSourceFields = (array) u($pathSourceFields ?? []);
$type = (array) ($node['type'] ?? []);
$uid = (string) ($node['uid'] ?? '');
$record = (bool) ($type['record'] ?? false);
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<?php $this->include('component/modal-header', ['title' => (string) ($node['title'] ?? '')]) ?>
<form
	id="node-editor-form"
	class="cms-create"
	method="post"
	action="<?= escape($links->create((string) ($type['handle'] ?? ''), $node['parent'] ?? null)) ?>"
	hx-swap="none"
	data-json-form
	data-create
	novalidate>
	<?php $this->include('component/csrf') ?>
	<input type="hidden" name="uid" value="<?= escape($uid) ?>" />
	<input type="hidden" name="dialog" value="1" />
	<div class="modal-body">
		<div id="editor-errors" class="errors" hidden></div>
		<output id="editor-status" class="status" role="status" hidden></output>
		<?php $this->include('field/sheet', [
			'fields' => $node['fields'] ?? [],
			'fieldsets' => $node['fieldsets'] ?? [],
			'content' => $node['content'] ?? [],
			'locales' => $locales,
			'defaultLocale' => $defaultLocale,
			'uid' => $uid,
			'assets' => [],
			'pathSourceFields' => $pathSourceFields,
		]) ?>
		<?php // What creating means: an unpublished main version, or a record saved directly. ?>
		<div class="hint">
			<?php if (!$record): ?>
				<span class="cms-status-dots is-sm" aria-hidden="true">
					<span class="mark" data-stage="main" data-state="unpublished"><?= Icon::render('globe') ?></span>
					<span class="mark" data-stage="draft" data-state="none"><?= Icon::render('people') ?></span>
					<span class="mark" data-stage="mine" data-state="none"><?= Icon::render('person') ?></span>
				</span>
			<?php endif ?>
			<p><?= escape(__($record ? 'editor:create-record' : 'editor:create-entry')) ?></p>
		</div>
	</div>
	<footer class="modal-footer">
		<button type="button" class="cms-button secondary" data-dialog-close><?= escape(__('editor:cancel')) ?></button>
		<button type="submit" class="cms-button primary" data-editor-submit><?= escape(__('editor:create')) ?></button>
	</footer>
	<input type="hidden" name="_complete" value="1" />
</form>
<script id="cosray-system-data" type="application/json"><?= json_encode(
	['panel' => $panelBase, 'system' => $system],
	$jsonFlags,
) ?></script>

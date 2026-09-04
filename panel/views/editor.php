<?php

use function Cosray\escape;

$this->layout('layer/main');

$mode = (string) $mode;
$name = (string) $name;
$slug = (string) $slug;
$node = (array) $this->unwrap($node);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$generatedPaths = (array) $this->unwrap($generatedPaths ?? []);
$pathSourceFields = (array) $this->unwrap($pathSourceFields ?? []);
$meta = (array) ($this->unwrap($meta ?? null) ?? []);
$system = (array) $this->unwrap($system);
$panelBase = (string) $panelBase;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

$fields = $node['fields'] ?? [];
$fieldsets = $node['fieldsets'] ?? [];
$content = $node['content'] ?? [];
$assets = $node['assets'] ?? [];
$type = $node['type'] ?? [];
$uid = (string) ($node['uid'] ?? '');
$routable = (bool) ($type['routable'] ?? false);
$renderable = (bool) ($type['renderable'] ?? false);
$published = (bool) ($node['published'] ?? false);
$deletable = (bool) ($node['deletable'] ?? false);
// The inspector carries the settings a node only has when it is addressable or
// rendered; without either there is nothing to put in it and the editor runs
// single-column.
$showSettings = $routable || $renderable;
$edit = $mode === 'edit';
$action = $edit
	? $links->edit($uid)
	: $links->create((string) ($type['handle'] ?? ''));

$fieldsByName = [];

foreach ($fields as $field) {
	if (!is_array($field) || !is_string($field['name'] ?? null)) {
		continue;
	}

	$fieldsByName[$field['name']] = $field;
}

$fieldsetsByFirstField = [];
$fieldsetMembers = [];

foreach ($fieldsets as $fieldset) {
	if (!is_array($fieldset)) {
		continue;
	}

	$members = array_values(array_filter(
		(array) ($fieldset['fields'] ?? []),
		static fn(mixed $name): bool => is_string($name),
	));

	if ($members === []) {
		continue;
	}

	$fieldsetsByFirstField[$members[0]] = $fieldset;

	foreach ($members as $member) {
		$fieldsetMembers[$member] = true;
	}
}

// The pane renders a stack of sections: each fieldset is one, and every run
// of fields between fieldsets forms an anonymous one, so dividers can sit
// between sections without wrapping single fields.
$sections = [];
$run = null;

foreach ($fields as $field) {
	if (!is_array($field) || ($field['hidden'] ?? false)) {
		continue;
	}

	$fieldName = (string) ($field['name'] ?? '');

	if (isset($fieldsetsByFirstField[$fieldName])) {
		$sections[] = ['fieldset' => $fieldsetsByFirstField[$fieldName]];
		$run = null;
	} elseif (!isset($fieldsetMembers[$fieldName])) {
		if ($run === null) {
			$sections[] = ['fields' => []];
			$run = array_key_last($sections);
		}

		$sections[$run]['fields'][] = $field;
	}
}
?>

<div class="page cms-node">
	<header class="head">
		<div class="titles">
			<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
				<a href="<?= escape($links->back()) ?>"><?= escape($name) ?></a>
				<span class="sep" aria-hidden="true">/</span>
				<span><?= escape($edit ? __('editor:mode-edit') : __('editor:mode-create')) ?></span>
			</nav>
			<div class="line">
				<h1><?= $node['title'] ?? '' ?></h1>
				<?php if ($renderable): ?>
					<span
						id="editor-published"
						class="cms-status <?= $published ? 'is-published' : 'is-unpublished' ?>">
						<?= escape($published ? __('editor:published') : __('editor:unpublished')) ?>
					</span>
				<?php endif ?>
				<span
					id="editor-dirty"
					class="dirty"
					title="<?= escape(__('editor:unsaved-changes')) ?>"
					hidden>●</span>
			</div>
		</div>

		<div class="actions">
			<output id="editor-status" class="status" role="status"></output>
			<?php if ($edit && $deletable): ?>
				<?php // Its own form: the editor form wraps the panes, and forms

				// cannot nest. ?>
				<?php // hx-swap="none" like the editor form: success is a 303 htmx

				// follows into the collection, a refusal returns only the
				// out-of-band status chip. ?>
				<form
					method="post"
					action="<?= escape($links->delete($uid)) ?>"
					hx-swap="none"
					hx-confirm="<?= escape(__('editor:delete-confirm')) ?>">
					<button class="cms-button danger" type="submit">
						<?= escape(__('editor:delete')) ?>
					</button>
				</form>
			<?php endif ?>
			<?php if ($edit && $routable && $renderable): ?>
				<button
					class="cms-button secondary"
					type="submit"
					form="node-editor-form"
					name="preview"
					value="1"
					data-editor-submit>
					<?= escape(__('editor:preview')) ?>
				</button>
			<?php endif ?>
			<button
				class="cms-button secondary"
				type="submit"
				form="node-editor-form"
				name="publish"
				value="1"
				data-editor-submit>
				<?= escape(__('editor:save-publish')) ?>
			</button>
			<button class="cms-button primary" type="submit" form="node-editor-form" data-editor-submit>
				<?= escape(__('editor:save')) ?>
			</button>
		</div>
	</header>

	<?php // The form wraps both panes: the inspector's controls submit with the

	// content fields through the same merge patch, so they have to be inside it.
	// novalidate because the form legitimately hides controls (locale variants,
	// meta dialogs) that native validation cannot handle; the server validates
	// and reports out of band. ?>
	<?php // data-json-form: the transport behavior re-encodes the submit as one

	// nested JSON body, so PHP's max_input_vars cannot truncate large forms. ?>
	<form
		id="node-editor-form"
		class="panes"
		method="post"
		action="<?= escape($action) ?>"
		hx-swap="none"
		data-json-form
		novalidate>
		<div class="pane">
			<div class="inner">
				<div id="editor-errors" class="errors" hidden></div>
				<?php if (!$edit): ?>
					<?php // A new node carries the blueprint uid so media uploaded

					// before the first save lands under node/<uid>/ and the stored
					// node adopts the same uid. ?>
					<input type="hidden" name="uid" value="<?= escape($uid) ?>" />
				<?php endif ?>
				<div class="sheet">
					<?php foreach ($sections as $section): ?>
						<?php if (isset($section['fieldset'])): ?>
							<?php $this->insert('field/fieldset', [
								'fieldset' => $section['fieldset'],
								'fieldsByName' => $fieldsByName,
								'content' => $content,
								'locales' => $locales,
								'defaultLocale' => $defaultLocale,
								'uid' => $uid,
								'assets' => $assets,
								'pathSourceFields' => $pathSourceFields,
							]) ?>
						<?php else: ?>
							<div class="cms-fields">
								<?php foreach ($section['fields'] as $field): ?>
									<?php $this->insert('field/item', [
										'field' => $field,
										'content' => $content,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => $uid,
										'assets' => $assets,
										'pathSourceFields' => $pathSourceFields,
									]) ?>
								<?php endforeach ?>
							</div>
						<?php endif ?>
					<?php endforeach ?>
				</div>
			</div>
		</div>

		<?php if ($showSettings): ?>
			<?php $this->insert('node/inspector', [
				'node' => $node,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'routable' => $routable,
				'renderable' => $renderable,
				'pathsUrl' => $edit
					? $links->paths($uid)
					: $links->createPaths((string) ($type['handle'] ?? '')),
				'generatedPaths' => $generatedPaths,
				'meta' => $meta,
			]) ?>
		<?php endif ?>

		<?php // Truncation sentinel — the last control in the form. A submit cut

		// short (a form-encoded POST past max_input_vars loses its tail) is
		// missing it, and the server refuses to save such submissions. ?>
		<input type="hidden" name="_complete" value="1" />
	</form>

	<div id="editor-preview" hidden></div>
	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => $panelBase, 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>

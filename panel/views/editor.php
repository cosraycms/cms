<?php

use Cosray\Panel\ContentLocales;

use function Cosray\escape;

$embed = u($embed ?? null);
$embed = is_array($embed) ? $embed : null;
$this->layout($embed === null ? 'layer/main' : 'layer/embed');

$mode = (string) $mode;
$backUrl = (string) u($backUrl);
$backLabel = (string) u($backLabel);
$links = u($links);
$node = (array) u($node);
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$generatedPaths = (array) u($generatedPaths ?? []);
$pathSourceFields = (array) u($pathSourceFields ?? []);
$meta = (array) (u($meta ?? null) ?? []);
$system = (array) u($system);
$panelBase = (string) u($panelBase);
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

$fields = $node['fields'] ?? [];
$fieldsets = $node['fieldsets'] ?? [];
$content = $node['content'] ?? [];
$assets = $node['assets'] ?? [];
$type = $node['type'] ?? [];
$uid = (string) ($node['uid'] ?? '');
$parent = $node['parent'] ?? null;
$routable = (bool) ($type['routable'] ?? false);
$renderable = (bool) ($type['renderable'] ?? false);
$published = (bool) ($node['published'] ?? false);
$deletable = (bool) ($node['deletable'] ?? false);
$draft = is_array($meta['draft'] ?? null) ? $meta['draft'] : null;
$contentLocales = ContentLocales::used($fields, count($locales));
$showSettings = $routable || $renderable || $contentLocales;
$edit = $mode === 'edit';
$panelCookie = u($panelCookie);
$wideBlocks = $panelCookie->is('blocks', 'wide');
$showSettings = $showSettings && $embed === null;
$action = $edit
	? $links->edit($uid)
	: $links->create((string) ($type['handle'] ?? ''), $parent);
$autosave = u($autosave ?? null);
$restored = is_array($autosave) && is_array($autosave['restored'] ?? null) ? $autosave['restored'] : null;
?>

<div
	class="page cms-node"
	<?= $wideBlocks ? 'data-wide-blocks' : '' ?>
	<?php if ($embed !== null): ?>
		data-embed
		data-embed-uid="<?= escape($uid) ?>"
		data-embed-origins='<?= json_encode($embed['origins'] ?? [], $jsonFlags) ?>'
		<?= ($embed['created'] ?? false) ? 'data-embed-created' : '' ?>
	<?php endif ?>>
	<header class="head">
		<div class="titles">
			<div class="line">
				<?php if ($embed === null): ?>
					<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
						<a href="<?= escape($backUrl) ?>" hx-target="#frame"><?= escape($backLabel) ?></a>
						<span class="sep" aria-hidden="true">/</span>
					</nav>
				<?php endif ?>
				<h1><?= $node['title'] ?? '' ?></h1>
				<?php if ($renderable): ?>
					<span
						id="editor-published"
						class="cms-status <?= $published ? 'is-published' : 'is-unpublished' ?>">
						<?= escape($published ? __('editor:published') : __('editor:unpublished')) ?>
					</span>
					<span id="editor-changes" class="cms-status is-changes" <?= $draft ? '' : 'hidden' ?>>
						<?= escape(__('editor:changes')) ?>
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
			<?php // Filled and shown by errors.js while a failed save has open issues. ?>
			<div id="editor-problems" class="problems" hidden>
				<button class="cms-problems" type="button" popovertarget="editor-problems-menu">
					<span class="mark" aria-hidden="true">!</span>
					<span class="count"></span>
				</button>
				<div
					id="editor-problems-menu"
					class="cms-action-menu cms-problems-menu"
					popover="auto"
					data-action-menu
					data-align="end">
					<p class="heading"><?= escape(__('editor:problems-heading')) ?></p>
					<div class="entries"></div>
				</div>
			</div>
			<?php if ($embed !== null): ?>
				<button class="cms-button secondary" type="button" data-embed-cancel>
					<?= escape(__('editor:cancel')) ?>
				</button>
				<button class="cms-button primary" type="submit" form="node-editor-form" data-editor-submit>
					<?= \Cosray\Panel\Icon::render('floppy') ?>
					<?= escape(__('editor:save')) ?>
				</button>
			<?php else: ?>
			<?php if ($edit && $deletable): ?>
				<?php // Its own form: the editor form wraps the panes, and forms

				// cannot nest. ?>
				<form
					id="node-editor-delete"
					data-dirty-bypass
					method="post"
					action="<?= escape($links->delete($uid)) ?>"
					hx-swap="none"
					hx-confirm="<?= escape(__('editor:delete-confirm')) ?>"
					data-confirm-action="<?= escape(__('editor:delete')) ?>"
					data-confirm-tone="danger">
					<?php $this->include('component/csrf') ?>
					<button class="cms-button danger" type="submit">
						<?= \Cosray\Panel\Icon::render('trash3') ?>
						<?= escape(__('editor:delete')) ?>
					</button>
				</form>
			<?php endif ?>
			<?php if ($edit && $renderable): ?>
				<?php // Discarding the working copy: its own form like delete, submitted

				// from the save menu. Hidden so the empty element does not take a gap
				// in the action row. The unsaved-changes guard stands aside; dropping
				// the unsaved edits is the point, and hx-confirm asks once. ?>
				<form
					id="node-editor-discard"
					method="post"
					action="<?= escape($links->discard($uid)) ?>"
					hx-swap="none"
					hx-confirm="<?= escape(__('editor:discard-confirm')) ?>"
					data-confirm-action="<?= escape(__('editor:discard-changes')) ?>"
					data-confirm-tone="danger"
					data-dirty-bypass
					data-autosave-drop
					hidden><?php $this->include('component/csrf') ?></form>
			<?php endif ?>
			<?php if ($edit && $routable && $renderable): ?>
				<button
					class="cms-button secondary"
					type="submit"
					form="node-editor-form"
					name="preview"
					value="1"
					data-editor-submit>
					<?= \Cosray\Panel\Icon::render('eye') ?>
					<?= escape(__('editor:preview')) ?>
				</button>
			<?php endif ?>
			<div class="cms-split-button">
				<button class="cms-button primary" type="submit" form="node-editor-form" data-editor-submit>
					<?= \Cosray\Panel\Icon::render('floppy') ?>
					<?= escape(__('editor:save')) ?>
				</button>
				<button
					class="cms-button primary toggle"
					type="button"
					popovertarget="editor-save-options"
					aria-haspopup="menu"
					aria-label="<?= escape(__('editor:save-options')) ?>">
					<?= \Cosray\Panel\Icon::render('chevron-down') ?>
				</button>
				<?php $this->include('node/save-options', ['draft' => $draft]) ?>
			</div>
			<?php endif ?>
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
		<?= $contentLocales ? 'data-content-locale-scope data-content-locale="' . escape($defaultLocale) . '"' : '' ?>
		<?= $contentLocales ? "data-content-locales='" . escape(json_encode($locales, $jsonFlags)) . "'" : '' ?>
		novalidate>
		<?php $this->include('component/csrf') ?>
		<div class="pane">
			<div class="pane-scroll">
				<div class="inner">
					<?php if ($restored !== null): ?>
						<?php $this->include('node/restored', ['restored' => $restored]) ?>
					<?php endif ?>
					<div id="editor-errors" class="errors" hidden></div>
					<?php if ($embed !== null): ?>
						<?php // The embedding application reads live content: its saves publish. ?>
						<input type="hidden" name="publish" value="1" />
						<?php if ($contentLocales): ?>
							<div class="cms-embed-locales">
								<?php $this->include('component/content-locales', [
									'locales' => $locales,
									'selected' => $defaultLocale,
								]) ?>
							</div>
						<?php endif ?>
					<?php endif ?>
					<?php if (!$edit): ?>
						<?php // A new node carries the blueprint uid so media uploaded

						// before the first save lands under node/<uid>/ and the stored
						// node adopts the same uid. ?>
						<input type="hidden" name="uid" value="<?= escape($uid) ?>" />
					<?php endif ?>
					<?php $this->include('field/sheet', [
						'fields' => $fields,
						'fieldsets' => $fieldsets,
						'content' => $content,
						'locales' => $locales,
						'defaultLocale' => $defaultLocale,
						'uid' => $uid,
						'assets' => $assets,
						'pathSourceFields' => $pathSourceFields,
						'changed' => $restored['fields'] ?? [],
					]) ?>
				</div>
			</div>
		</div>

		<?php if ($showSettings): ?>
			<?php $this->include('node/inspector', [
				'node' => $node,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'routable' => $routable,
				'renderable' => $renderable,
				'pathsUrl' => $edit
					? $links->paths($uid)
					: $links->createPaths((string) ($type['handle'] ?? ''), $parent),
				'generatedPaths' => $generatedPaths,
				'meta' => $meta,
				'contentLocales' => $contentLocales,
				'collapsed' => $panelCookie->is('inspector', 'collapsed'),
				'changed' => $restored['settings'] ?? [],
			]) ?>
		<?php endif ?>

		<?php // Truncation sentinel — the last control in the form. A submit cut

		// short (a form-encoded POST past max_input_vars loses its tail) is
		// missing it, and the server refuses to save such submissions. ?>
		<input type="hidden" name="_complete" value="1" />
	</form>

	<div id="editor-preview" hidden></div>
	<?php if (is_array($autosave)): ?>
		<?php $this->include('node/autosave', ['autosave' => $autosave]) ?>
	<?php endif ?>
	<?php if ($restored !== null): ?>
		<?php // Submitted from the restored note; outside the editor form, as

		// forms cannot nest. data-autosave-drop keeps the pending edits from
		// being autosaved on the way out. ?>
		<form
			id="editor-autosave-discard"
			method="post"
			action="<?= escape((string) $restored['discard']) ?>"
			hx-swap="none"
			hx-confirm="<?= escape(__('editor:restored-discard-confirm')) ?>"
			data-confirm-action="<?= escape(__('editor:restored-discard')) ?>"
			data-confirm-tone="danger"
			data-dirty-bypass
			data-autosave-drop
			hidden><?php $this->include('component/csrf') ?></form>
	<?php endif ?>
	<?php if (array_any(
		$fields,
		static fn(mixed $f): bool => is_array($f) && (($f['control'] ?? [])['name'] ?? null) === 'blocks',
	)) {
		$this->include('node/layout-preview', [
			'url' => $edit ? $links->blocks($uid) : $links->createBlocks((string) ($type['handle'] ?? ''), $parent),
		]);
	} ?>
	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => $panelBase, 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>

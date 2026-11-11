<?php

use Cosray\Panel\ContentLocales;
use Cosray\Panel\Icon;

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
$record = (bool) ($type['record'] ?? false);
$contentLocales = ContentLocales::used($fields, count($locales));
$edit = $mode === 'edit';
$showSettings = $routable || $renderable || $contentLocales || $edit;
$panelCookie = u($panelCookie);
$wideBlocks = $panelCookie->is('blocks', 'wide');
$showSettings = $showSettings && $embed === null;
$action = $edit
	? $links->edit($uid)
	: $links->create((string) ($type['handle'] ?? ''), $parent);
$autosave = u($autosave ?? null);
$status = u($status ?? null);
$status = is_array($status) ? $status : null;
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
				<?php if ($status !== null): ?>
					<?php $this->include('component/status-dots', [
						'status' => $status,
						'id' => 'editor-status-dots',
					]) ?>
				<?php endif ?>
				<?php if ($embed === null): ?>
					<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
						<a href="<?= escape($backUrl) ?>" hx-target="#frame"><?= escape($backLabel) ?></a>
						<span class="sep" aria-hidden="true">/</span>
					</nav>
				<?php endif ?>
				<h1><?= $node['title'] ?? '' ?></h1>
				<?php if ($embed !== null): ?>
					<?php // The embedding frame learns about unsaved edits from this pill. ?>
					<span id="editor-dirty" class="cms-status is-unsaved" hidden>
						<?= escape(__('editor:unsaved-changes')) ?>
					</span>
				<?php endif ?>
			</div>
		</div>

		<div class="actions">
			<?php // A save's outcome for the scripts; the node editor shows it as a toast. ?>
			<output id="editor-status" class="status" role="status" <?= $embed === null ? 'hidden' : '' ?>></output>
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
					<?= Icon::render('floppy') ?>
					<?= escape(__('editor:save')) ?>
				</button>
			<?php else: ?>
				<?php if ($edit): ?>
					<button
						class="cms-button secondary square"
						type="button"
						popovertarget="editor-more"
						aria-haspopup="menu"
						aria-label="<?= escape(__('editor:more')) ?>"
						title="<?= escape(__('editor:more')) ?>">
						<?= Icon::render('three-dots-vertical') ?>
					</button>
					<div id="editor-more" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
						<button type="submit" form="node-editor-duplicate">
							<?= Icon::render('copy') ?>
							<?= escape(__('editor:duplicate')) ?>
						</button>
						<?php if ($deletable): ?>
							<button type="submit" form="node-editor-delete" class="danger">
								<?= Icon::render('trash3') ?>
								<?= escape(__('editor:delete')) ?>
							</button>
						<?php endif ?>
					</div>
				<?php endif ?>
				<?php if ($edit && $routable && $renderable): ?>
					<div class="cms-split-button">
						<?php // Keeps the edits as the user's autosave, without saving, and

						// shows them on top of the working state. ?>
						<button
							class="cms-button secondary square"
							type="submit"
							form="node-editor-form"
							formaction="<?= escape($links->preview($uid)) ?>"
							data-editor-submit
							aria-label="<?= escape(__('editor:preview-current')) ?>"
							title="<?= escape(__('editor:preview-current')) ?>">
							<?= Icon::render('eye') ?>
						</button>
						<button
							class="cms-button secondary toggle"
							type="button"
							popovertarget="editor-preview-options"
							aria-haspopup="menu"
							aria-label="<?= escape(__('editor:preview-options')) ?>">
							<?= Icon::render('chevron-down') ?>
						</button>
						<div
							id="editor-preview-options"
							class="cms-action-menu"
							popover="auto"
							data-action-menu
							data-align="end">
							<button
								type="submit"
								form="node-editor-form"
								formaction="<?= escape($links->preview($uid)) ?>"
								data-editor-submit>
								<?= Icon::render('eye') ?>
								<?= escape(__('editor:preview-current')) ?>
							</button>
							<?php $liveUrl = $status['liveUrl'] ?? null; ?>
							<?php if (is_string($liveUrl)): ?>
								<a href="<?= escape($liveUrl) ?>" target="_blank" rel="noopener">
									<?= Icon::render('box-arrow-up-right') ?>
									<?= escape(__('status:view')) ?>
								</a>
							<?php else: ?>
								<button type="button" disabled>
									<?= Icon::render('box-arrow-up-right') ?>
									<?= escape(__('status:view')) ?>
								</button>
							<?php endif ?>
						</div>
					</div>
				<?php endif ?>
				<?php
				$mine = (string) ($status['mine'] ?? 'none');
				$publishable = $edit && !$record;
				$saveLabel = $publishable ? 'editor:save-draft' : 'editor:save';
				?>
				<?php // A node's save keeps the edits as its draft; a record's saves them. ?>
				<button
					class="cms-button <?= $publishable ? 'secondary' : 'primary' ?>"
					type="submit"
					form="node-editor-form"
					data-editor-submit
					<?= $edit ? 'data-needs="mine"' : '' ?>
					<?= $edit && $mine === 'none' ? 'disabled' : '' ?>>
					<?= Icon::render('floppy') ?>
					<span
						data-save-label
						data-normal="<?= escape(__($saveLabel)) ?>"
						data-conflict="<?= escape(__('editor:save-anyway')) ?>"><?= escape(__(
						$mine === 'conflict' ? 'editor:save-anyway' : $saveLabel,
					)) ?></span>
				</button>
				<?php if ($publishable && $status !== null): ?>
					<?php $this->include('node/apply', ['status' => $status]) ?>
				<?php endif ?>
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
					<?php if ($status !== null): ?>
						<?php $this->include('node/conflict', ['status' => $status]) ?>
					<?php endif ?>
					<div id="editor-errors" class="errors" hidden></div>
					<?php if ($embed !== null): ?>
						<?php // The embedding application reads the main version: its saves apply and publish. ?>
						<input type="hidden" name="apply" value="publish" />
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
				'status' => $status,
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
	<?php // The source of the requests that leave the editor after a form
	// dropped it: no form, whose fields would end up in the URL. ?>
	<span id="editor-leave" hidden data-dirty-bypass></span>
	<?php if ($edit && $embed === null): ?>
		<?php // The head's menu and the status tab submit these; the editor form

		// wraps the panes, and forms cannot nest. ?>
		<form
			id="node-editor-duplicate"
			method="post"
			action="<?= escape($links->duplicate($uid)) ?>"
			hx-swap="none"
			hidden><?php $this->include('component/csrf') ?></form>
		<?php if ($deletable): ?>
			<form
				id="node-editor-delete"
				data-dirty-bypass
				method="post"
				action="<?= escape($links->delete($uid)) ?>"
				hx-swap="none"
				hx-confirm="<?= escape(__('editor:delete-confirm')) ?>"
				data-confirm-action="<?= escape(__('editor:delete')) ?>"
				data-confirm-tone="danger"
				hidden><?php $this->include('component/csrf') ?></form>
		<?php endif ?>
		<?php if (!$record): ?>
			<?php // Discarding the working copy drops the unsaved edits too, unless the

			// question says to keep them: the unsaved-changes guard stands aside. ?>
			<form
				id="node-editor-discard"
				method="post"
				action="<?= escape($links->discard($uid)) ?>"
				hx-swap="none"
				data-dirty-bypass
				data-autosave-drop
				data-done-toast="<?= escape(__('editor:draft-discarded')) ?>"
				hidden><?php $this->include('component/csrf') ?></form>
		<?php endif ?>
	<?php endif ?>
	<?php if (is_array($status['visibility'] ?? null)): ?>
		<?php // The publication and listing switches act at once, apart from the save:

		// their own form, outside the editor form, which forms cannot nest in. ?>
		<form
			id="node-editor-visibility"
			method="post"
			action="<?= escape($links->visibility($uid)) ?>"
			hx-swap="none"
			data-dirty-bypass
			hidden><?php $this->include('component/csrf') ?></form>
	<?php endif ?>
	<?php if (is_array($autosave)): ?>
		<?php $this->include('node/autosave', ['autosave' => $autosave]) ?>
	<?php endif ?>
	<?php if (is_array($autosave)): ?>
		<?php // Drops the user's edits; outside the editor form, as forms cannot

		// nest. data-autosave-drop keeps the pending edits from being autosaved
		// on the way out. ?>
		<form
			id="editor-autosave-discard"
			method="post"
			action="<?= escape($links->discardAutosave($uid)) ?>"
			hx-swap="none"
			data-dirty-bypass
			data-autosave-drop
			data-done-toast="<?= escape(__('editor:mine-discarded')) ?>"
			hidden><?php $this->include('component/csrf') ?></form>
	<?php endif ?>
	<?php if ($status !== null): ?>
		<?php $this->include('node/discard', ['status' => $status]) ?>
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

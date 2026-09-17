<?php

use function Cosray\escape;

// A user's account and the fields of its type. The form is the editor form:
// the same id, JSON transport, error marks and out-of-band save response.

$this->layout('layer/main');

$user = $this->unwrap($user);
$exists = (bool) $exists;
$locked = (bool) $locked;
$roles = (array) $this->unwrap($roles);
$fields = (array) $this->unwrap($fields);
$locales = (array) $this->unwrap($locales);
$panelLocaleChoices = (array) $this->unwrap($panelLocaleChoices);
$deleteUrl = $this->unwrap($deleteUrl ?? null);
$notice = $this->unwrap($notice ?? null);
$system = (array) $this->unwrap($system);
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div class="page cms-node cms-user">
	<header class="head">
		<div class="titles">
			<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
				<a href="<?= escape((string) $listUrl) ?>"><?= escape(__('user:users')) ?></a>
				<span class="sep" aria-hidden="true">/</span>
				<span><?= escape((string) $typeLabel) ?></span>
			</nav>
			<div class="line">
				<h1><?= escape((string) $title) ?></h1>
				<?php if ($exists && !$user->active): ?>
					<span class="cms-status is-unpublished"><?= escape(__('user:inactive')) ?></span>
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
			<?php if (is_string($deleteUrl)): ?>
				<?php // Its own form: forms cannot nest. A refusal answers with the

				// out-of-band status and error box, success with a redirect. ?>
				<form
					method="post"
					action="<?= escape($deleteUrl) ?>"
					hx-swap="none"
					hx-confirm="<?= escape(__('user:delete-confirm')) ?>"
					data-dirty-bypass>
					<button class="cms-button danger" type="submit">
						<?= \Cosray\Panel\Icon::render('trash3') ?>
						<?= escape(__('editor:delete')) ?>
					</button>
				</form>
			<?php endif ?>
			<button class="cms-button primary" type="submit" form="node-editor-form" data-editor-submit>
				<?= \Cosray\Panel\Icon::render('floppy') ?>
				<?= escape(__('editor:save')) ?>
			</button>
		</div>
	</header>

	<form
		id="node-editor-form"
		class="panes"
		method="post"
		action="<?= escape((string) $action) ?>"
		hx-swap="none"
		data-json-form
		novalidate>
		<div class="pane">
			<div class="inner">
				<?php if (is_string($notice)): ?>
					<div class="cms-notice" role="status">
						<p><?= escape($notice) ?></p>
					</div>
				<?php endif ?>
				<div id="editor-errors" class="errors" hidden></div>

				<div class="sheet">
					<fieldset class="cms-fieldset" data-fieldset="account">
						<legend class="legend"><?= escape(__('user:account')) ?></legend>
						<div class="cms-fields fields">
							<?php $this->insert('user/input', [
								'name' => 'email',
								'label' => __('user:email'),
								'type' => 'email',
								'value' => $user->email,
								'required' => true,
							]) ?>
							<?php $this->insert('user/input', [
								'name' => 'username',
								'label' => __('user:username'),
								'value' => $user->username,
								'help' => __('user:username-help'),
							]) ?>
							<?php $this->insert('user/input', [
								'name' => 'name',
								'label' => __('user:name'),
								'value' => $user->name ?? '',
							]) ?>
							<?php if (count($panelLocaleChoices) > 1): ?>
								<div class="cms-field" style="grid-column: span 50 / span 50" data-field="panel_locale">
									<label class="label" for="user-panel-locale"><div><?= escape(
										__('user:panel-language'),
									) ?></div></label>
									<div class="field-body">
										<div class="control">
											<select class="cms-input" id="user-panel-locale" name="panel_locale">
												<option value=""><?= escape(__('user:panel-language-auto')) ?></option>
												<?php foreach ($panelLocaleChoices as $id => $label): ?>
													<option
														value="<?= escape((string) $id) ?>"
														<?= $user->panelLocale === $id ? 'selected' : '' ?>><?= escape((string) $label) ?></option>
												<?php endforeach ?>
											</select>
										</div>
									</div>
								</div>
							<?php endif ?>
							<?php $this->insert('user/input', [
								'name' => 'password',
								'label' => $exists ? __('user:new-password') : __('user:password'),
								'type' => 'password',
								'required' => !$exists,
								'autocomplete' => 'new-password',
								'help' => $exists ? __('user:password-keep-help') : __('user:password-help'),
							]) ?>
							<?php $this->insert('user/input', [
								'name' => 'password_repeat',
								'label' => __('user:password-repeat'),
								'type' => 'password',
								'required' => !$exists,
								'autocomplete' => 'new-password',
							]) ?>
						</div>
					</fieldset>

					<fieldset class="cms-fieldset" data-fieldset="access" <?= $locked ? 'disabled' : '' ?>>
						<legend class="legend"><?= escape(__('user:access')) ?></legend>
						<?php if ($locked): ?>
							<div class="description"><?= escape(__('user:access-own-help')) ?></div>
						<?php endif ?>
						<div class="cms-fields fields">
							<div class="cms-field" style="grid-column: span 100 / span 100" data-field="active">
								<div class="field-body">
									<div class="control cms-toggle">
										<label class="toggle">
											<input
												type="checkbox"
												role="switch"
												class="cms-switch"
												name="active"
												value="1"
												<?= $user->active ? 'checked' : '' ?> />
											<span><?= escape(__('user:active')) ?></span>
										</label>
									</div>
									<div class="description"><?= escape(__('user:active-help')) ?></div>
								</div>
							</div>
							<?php if ($roles !== []): ?>
								<div class="cms-field" style="grid-column: span 100 / span 100" data-field="roles">
									<div class="label" id="user-roles-label"><div><?= escape(__('user:roles')) ?></div></div>
									<div class="field-body">
										<div class="control cms-radio-group" role="group" aria-labelledby="user-roles-label">
											<?php foreach ($roles as $role): ?>
												<label class="cms-checkbox-label">
													<input
														type="checkbox"
														class="cms-checkbox"
														name="roles[]"
														value="<?= escape((string) $role['name']) ?>"
														<?= $role['held'] ? 'checked' : '' ?>
														<?= $role['assignable'] ? '' : 'disabled' ?> />
													<span><?= escape((string) $role['label']) ?></span>
												</label>
											<?php endforeach ?>
										</div>
										<div class="description"><?= escape(__('user:roles-help')) ?></div>
									</div>
								</div>
							<?php endif ?>
						</div>
					</fieldset>
				</div>

				<?php if ($fields['fields'] !== []): ?>
					<?php $this->insert('field/sheet', [
						'fields' => $fields['fields'],
						'fieldsets' => $fields['fieldsets'],
						'content' => $fields['content'],
						'locales' => $locales,
						'defaultLocale' => $defaultLocale,
						'uid' => $user->uid,
					]) ?>
				<?php endif ?>
			</div>
		</div>

		<?php // Truncation sentinel, the last control in the form; see the node editor. ?>
		<input type="hidden" name="_complete" value="1" />
	</form>

	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => $panelBase, 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>

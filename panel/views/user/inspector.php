<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The access rail beside the account fields: who the user may act as, and
// whether they may sign in at all. Its controls sit inside the editor form
// and submit with the account fields. Collapsed, only the strip's expand
// button remains — below 75rem the drawer is hidden until it is pressed.

$user = $this->unwrap($user);
$roles = (array) $this->unwrap($roles);
$locked = (bool) $locked;
$collapsed = (bool) $collapsed;
$show = __('user:access-show');
$hide = __('user:access-hide');
?>
<aside
	class="cms-inspector"
	aria-label="<?= escape(__('user:access')) ?>"
	data-inspector
	<?= $collapsed ? 'data-collapsed' : '' ?>>
	<div class="strip">
		<button
			type="button"
			class="tool"
			title="<?= escape($show) ?>"
			aria-label="<?= escape($show) ?>"
			data-inspector-expand>
			<?= Icon::render('layout-sidebar-inset-reverse') ?>
		</button>
	</div>
	<div class="drawer">
		<div class="top">
			<h2 class="heading"><?= escape(__('user:access')) ?></h2>
			<button
				type="button"
				class="tool"
				title="<?= escape($hide) ?>"
				aria-label="<?= escape($hide) ?>"
				data-inspector-collapse>
				<?= Icon::render('layout-sidebar-inset-reverse') ?>
			</button>
		</div>
		<div class="scroll">
			<?php // Disabled covers the whole section: there is no quick control

			// outside it that would stay live. ?>
			<fieldset class="cms-fieldset section" data-fieldset="access" <?= $locked ? 'disabled' : '' ?>>
				<?php if ($locked): ?>
					<p class="help"><?= escape(__('user:access-own-help')) ?></p>
				<?php endif ?>

				<div class="field" data-field="active">
					<label class="toggle">
						<span class="copy">
							<span class="title"><?= escape(__('user:active')) ?></span>
							<span class="help"><?= escape(__('user:active-help')) ?></span>
						</span>
						<input
							type="checkbox"
							role="switch"
							class="cms-switch"
							name="active"
							value="1"
							<?= $user->active ? 'checked' : '' ?> />
					</label>
				</div>

				<?php if ($roles !== []): ?>
					<div class="field" data-field="roles">
						<div class="label" id="user-roles-label"><?= escape(__('user:roles')) ?></div>
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
						<span class="help"><?= escape(__('user:roles-help')) ?></span>
					</div>
				<?php endif ?>
			</fieldset>
		</div>
	</div>
</aside>

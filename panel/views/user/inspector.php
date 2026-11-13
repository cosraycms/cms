<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The access rail beside the account fields: who the user may act as, and
// whether they may sign in at all. Its controls sit inside the editor form
// and submit with the account fields. Collapsed, only the strip's expand
// button remains — below 75rem the drawer is hidden until it is pressed.

$user = u($user);
$roles = (array) u($roles);
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
	<div class="cms-inspector-strip">
		<button
			type="button"
			class="cms-inspector-tool"
			title="<?= escape($show) ?>"
			aria-label="<?= escape($show) ?>"
			data-inspector-expand>
			<?= Icon::render('layout-sidebar-inset-reverse') ?>
		</button>
	</div>
	<div class="cms-inspector-drawer">
		<div class="cms-inspector-top">
			<h2 class="cms-inspector-heading"><?= escape(__('user:access')) ?></h2>
			<button
				type="button"
				class="cms-inspector-tool"
				title="<?= escape($hide) ?>"
				aria-label="<?= escape($hide) ?>"
				data-inspector-collapse>
				<?= Icon::render('layout-sidebar-inset-reverse') ?>
			</button>
		</div>
		<div class="cms-inspector-scroll">
			<?php // Disabled covers the whole section: there is no quick control

			// outside it that would stay live. ?>
			<fieldset class="cms-fieldset cms-inspector-section" data-fieldset="access" <?= $locked ? 'disabled' : '' ?>>
				<?php if ($locked): ?>
					<p class="cms-inspector-help"><?= escape(__('user:access-own-help')) ?></p>
				<?php endif ?>

				<div class="cms-inspector-field" data-field="active">
					<label class="cms-inspector-toggle">
						<span class="cms-inspector-copy">
							<span class="cms-inspector-title"><?= escape(__('user:active')) ?></span>
							<span class="cms-inspector-help"><?= escape(__('user:active-help')) ?></span>
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
					<div class="cms-inspector-field" data-field="roles">
						<div class="cms-inspector-label" id="user-roles-label"><?= escape(__('user:roles')) ?></div>
						<div class="cms-radio-group" role="group" aria-labelledby="user-roles-label">
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
						<span class="cms-inspector-help"><?= escape(__('user:roles-help')) ?></span>
					</div>
				<?php endif ?>
			</fieldset>
		</div>
	</div>
</aside>

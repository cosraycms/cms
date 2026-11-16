<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// A button of the panel kit. Receives: label, and either url (a link) or
// action (a form that posts there, with the CSRF field), or neither (a
// plain button of the surrounding form). Optional: icon, variant (primary,
// secondary, danger; secondary by default), download (a link the browser
// saves), confirm (a question asked first, with confirmAction as the
// confirming button's label and tone danger), name and value, and
// iconOnly, which keeps the label for screen readers only.

$label = (string) u($label);
$variant = (string) u($variant ?? 'secondary');
$icon = u($icon ?? null);
$url = u($url ?? null);
$action = u($action ?? null);
$confirm = u($confirm ?? null);
$iconOnly = (bool) u($iconOnly ?? false);
$class = 'cms-button cms-button-' . escape($variant);
$inner = (is_string($icon) ? Icon::render($icon) : '') . ($iconOnly ? '' : escape($label));
$aria = $iconOnly ? ' aria-label="' . escape($label) . '"' : '';
?>
<?php if (is_string($url)): ?>
	<a class="<?= $class ?>" href="<?= escape($url) ?>"<?= $aria ?><?= u($download ?? false) ? ' hx-boost="false" download' : '' ?>><?= $inner ?></a>
<?php elseif (is_string($action)): ?>
	<form
		class="cms-kit-inline-form"
		method="post"
		action="<?= escape($action) ?>"
		<?php if (is_string($confirm)): ?>
			hx-confirm="<?= escape($confirm) ?>"
			data-confirm-action="<?= escape((string) u($confirmAction ?? $label)) ?>"
			data-confirm-tone="danger"
		<?php endif ?>>
		<?php $this->include('component/csrf') ?>
		<button class="<?= $class ?>" type="submit"<?= $aria ?>><?= $inner ?></button>
	</form>
<?php else: ?>
	<button
		class="<?= $class ?>"
		type="submit"
		<?= isset($name) ? 'name="' . escape((string) u($name)) . '"' : '' ?>
		<?= isset($value) ? 'value="' . escape((string) u($value)) . '"' : '' ?><?= $aria ?>><?= $inner ?></button>
<?php endif ?>

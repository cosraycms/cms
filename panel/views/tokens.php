<?php

use function Cosray\escape;

// The signed-in user's API tokens. A new token is shown once, in the response
// that created it: only its hash is stored.

$this->layout('layer/main');

$tokens = (array) u($tokens);
$created = u($created ?? null);
$error = u($error ?? null);
$name = (string) u($name ?? '');
$tokensUrl = (string) $tokensUrl;
$profileUrl = (string) $profileUrl;
?>

<div class="page cms-collection cms-tokens">
	<header class="head">
		<div class="titles">
			<nav class="breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
				<a href="<?= escape($profileUrl) ?>"><?= escape(__('user:profile')) ?></a>
				<span class="sep" aria-hidden="true">/</span>
				<span><?= escape(__('tokens:title')) ?></span>
			</nav>
			<div class="line">
				<h1><?= escape(__('tokens:title')) ?></h1>
			</div>
		</div>
	</header>

	<div class="body">
		<div class="lead">
			<p class="intro"><?= escape(__('tokens:intro')) ?></p>

			<?php if (is_string($created)): ?>
				<div class="cms-notice" role="status">
					<p><?= escape(__('tokens:created')) ?></p>
					<input class="cms-input" type="text" readonly value="<?= escape($created) ?>" aria-label="<?= escape(__('tokens:title')) ?>" />
				</div>
			<?php endif ?>
			<?php if (is_string($error)): ?>
				<div class="cms-notice is-error" role="alert"><p><?= escape($error) ?></p></div>
			<?php endif ?>
		</div>

		<form class="toolbar" method="post" action="<?= escape($tokensUrl) ?>">
			<?php $this->include('component/csrf') ?>
			<label class="sr-only" for="token-name"><?= escape(__('tokens:name')) ?></label>
			<input
				id="token-name"
				class="cms-input name"
				name="name"
				required
				maxlength="255"
				value="<?= escape($name) ?>"
				placeholder="<?= escape(__('tokens:name')) ?>" />
			<span class="expires">
				<label for="token-expires"><?= escape(__('tokens:expires')) ?></label>
				<input id="token-expires" class="cms-input" name="expires" type="date" />
			</span>
			<button class="cms-button primary" type="submit"><?= escape(__('tokens:create')) ?></button>
		</form>

		<div class="listing">
			<?php if ($tokens === []): ?>
				<div class="empty"><strong><?= escape(__('tokens:none')) ?></strong></div>
			<?php else: ?>
				<div class="scroll">
					<table
						class="cms-list"
						role="table"
						style="--columns: minmax(12rem, 2fr) minmax(8rem, 1fr) minmax(8rem, 1fr) minmax(8rem, 1fr) max-content">
						<thead role="rowgroup">
							<tr role="row">
								<th role="columnheader"><span class="inner"><?= escape(__('tokens:name')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('tokens:created-at')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('tokens:expires')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('tokens:used')) ?></span></th>
								<th class="col-actions" role="columnheader"><span class="sr-only"><?= escape(__('tokens:revoke')) ?></span></th>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($tokens as $token): ?>
								<tr class="row" role="row">
									<td class="cell" role="cell" data-label="<?= escape(__('tokens:name')) ?>"><span class="value"><?= escape((string) $token['name']) ?></span></td>
									<td class="cell" role="cell" data-label="<?= escape(__('tokens:created-at')) ?>"><span class="value"><?= escape((string) $token['created']) ?></span></td>
									<td class="cell" role="cell" data-label="<?= escape(__('tokens:expires')) ?>"><span class="value"><?= escape((string) ($token['expires'] ?? __('tokens:never'))) ?></span></td>
									<td class="cell" role="cell" data-label="<?= escape(__('tokens:used')) ?>"><span class="value"><?= escape((string) ($token['used'] ?? __('tokens:never'))) ?></span></td>
									<td class="cell col-actions" role="cell">
										<form
											method="post"
											action="<?= escape((string) $token['revokeUrl']) ?>"
											hx-confirm="<?= escape(__('tokens:revoke-confirm', ['name' => (string) $token['name']])) ?>">
											<?php $this->include('component/csrf') ?>
											<button class="cms-button danger" type="submit"><?= escape(__('tokens:revoke')) ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>
		</div>
	</div>
</div>

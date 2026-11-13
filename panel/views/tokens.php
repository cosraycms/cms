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

<div class="cms-page cms-collection cms-tokens">
	<header class="cms-page-head">
		<div class="cms-page-titles">
			<div class="cms-page-line">
				<nav class="cms-page-breadcrumb" aria-label="<?= escape(__('collection:breadcrumb')) ?>">
					<a href="<?= escape($profileUrl) ?>"><?= escape(__('user:profile')) ?></a>
					<span class="cms-page-sep" aria-hidden="true">/</span>
				</nav>
				<h1><?= escape(__('tokens:title')) ?></h1>
			</div>
		</div>
	</header>

	<div class="cms-collection-body">
		<div class="cms-tokens-lead">
			<p class="cms-tokens-intro"><?= escape(__('tokens:intro')) ?></p>

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

		<form class="cms-page-toolbar" method="post" action="<?= escape($tokensUrl) ?>">
			<?php $this->include('component/csrf') ?>
			<label class="cms-sr-only" for="token-name"><?= escape(__('tokens:name')) ?></label>
			<input
				id="token-name"
				class="cms-input cms-tokens-name"
				name="name"
				required
				maxlength="255"
				value="<?= escape($name) ?>"
				placeholder="<?= escape(__('tokens:name')) ?>" />
			<span class="cms-tokens-expires">
				<label for="token-expires"><?= escape(__('tokens:expires')) ?></label>
				<input id="token-expires" class="cms-input" name="expires" type="date" />
			</span>
			<button class="cms-button cms-button-primary" type="submit"><?= escape(__('tokens:create')) ?></button>
		</form>

		<div class="cms-collection-listing">
			<?php if ($tokens === []): ?>
				<div class="cms-collection-empty"><strong><?= escape(__('tokens:none')) ?></strong></div>
			<?php else: ?>
				<div class="cms-collection-scroll">
					<table
						class="cms-list"
						role="table"
						style="--columns: minmax(12rem, 2fr) minmax(8rem, 1fr) minmax(8rem, 1fr) minmax(8rem, 1fr) max-content">
						<thead role="rowgroup">
							<tr role="row">
								<th role="columnheader"><span class="cms-list-heading"><?= escape(__('tokens:name')) ?></span></th>
								<th role="columnheader"><span class="cms-list-heading"><?= escape(__('tokens:created-at')) ?></span></th>
								<th role="columnheader"><span class="cms-list-heading"><?= escape(__('tokens:expires')) ?></span></th>
								<th role="columnheader"><span class="cms-list-heading"><?= escape(__('tokens:used')) ?></span></th>
								<th class="cms-list-col-actions" role="columnheader"><span class="cms-sr-only"><?= escape(__('tokens:revoke')) ?></span></th>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($tokens as $token): ?>
								<tr class="cms-list-row" role="row">
									<td class="cms-list-cell" role="cell" data-label="<?= escape(__('tokens:name')) ?>"><span class="cms-list-value"><?= escape((string) $token['name']) ?></span></td>
									<td class="cms-list-cell" role="cell" data-label="<?= escape(__('tokens:created-at')) ?>"><span class="cms-list-value"><?= escape((string) $token['created']) ?></span></td>
									<td class="cms-list-cell" role="cell" data-label="<?= escape(__('tokens:expires')) ?>"><span class="cms-list-value"><?= escape((string) ($token['expires'] ?? __('tokens:never'))) ?></span></td>
									<td class="cms-list-cell" role="cell" data-label="<?= escape(__('tokens:used')) ?>"><span class="cms-list-value"><?= escape((string) ($token['used'] ?? __('tokens:never'))) ?></span></td>
									<td class="cms-list-cell cms-list-col-actions" role="cell">
										<form
											method="post"
											action="<?= escape((string) $token['revokeUrl']) ?>"
											hx-confirm="<?= escape(__('tokens:revoke-confirm', ['name' => (string) $token['name']])) ?>"
											data-confirm-action="<?= escape(__('tokens:revoke')) ?>"
											data-confirm-tone="danger">
											<?php $this->include('component/csrf') ?>
											<button class="cms-button cms-button-danger" type="submit"><?= escape(__('tokens:revoke')) ?></button>
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

<script lang="ts">
	import type { Node } from '$types/data';
	import type { Locale } from '$lib/sys';

	import { setDirty } from '$lib/state';
	import { _ } from '$lib/locale';
	import { system } from '$lib/sys';
	import ToggleLine from '$shell/ToggleLine.svelte';

	type Props = {
		node: Node;
	};

	let { node = $bindable() }: Props = $props();

	function getPathPlaceholder(locale: Locale) {
		let localLocale: Locale | undefined = locale;

		if (!node.generatedPaths) {
			return '';
		}

		const localeId = locale.id;

		while (localLocale !== undefined) {
			const value = node.paths[localLocale.id];

			if (value) {
				return value;
			}

			localLocale = $system.locales.find((l) => l.id === localLocale?.fallback);
		}

		const value = node.generatedPaths[localeId];

		if (value) {
			return value;
		}

		return '';
	}

	function oninput() {
		setDirty();
	}

	function onHandleInput(event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		node.handle = input.value;
		setDirty();
	}
</script>

<div class="cms-settings">
	<div class="cms-settings-row">
		<label class="cms-field-label" for="cms-node-handle">
			{_('Handle')}:
		</label>
		<div class="cms-settings-handle-value">
			<input
				id="cms-node-handle"
				type="text"
				maxlength="64"
				pattern={'(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?'}
				value={node.handle ?? ''}
				oninput={onHandleInput}
			/>
		</div>
	</div>
	{#if node.type.routable}
		<div class="cms-settings-paths">
			{#each $system.locales as locale (locale)}
				<div class="cms-settings-path">
					<div class="cms-field-label">{locale.title}:</div>
					<div class="cms-settings-path-value">
						<input
							type="text"
							bind:value={node.paths[locale.id]}
							placeholder={getPathPlaceholder(locale)}
							required={locale.id === $system.defaultLocale}
							{oninput}
						/>
					</div>
				</div>
			{/each}
		</div>
	{/if}
	{#if node.type.renderable}
		<div class="cms-settings-renderable">
			<div class="cms-settings-section">
				<ToggleLine
					title={_('Veröffentlicht')}
					subtitle={_('Legt fest, ob die Seite für alle Besucher erreichbar ist.')}
					bind:value={node.published}
				/>
			</div>
			<!--<div class="my-4">
                <ToggleLine
                    title={_('Gesperrt')}
                    subtitle={_(
                        'Seiten die gesperrt sind, können nicht verändert werden.',
                    )}
                    bind:value={node.locked} />
            </div>-->
			<div class="cms-settings-section">
				<ToggleLine
					title={_('Versteckt')}
					subtitle={_('Versteckte Seiten werden in Auflistungen ignoriert.')}
					bind:value={node.hidden}
				/>
			</div>
			<div class="cms-settings-row">
				<div class="cms-field-label">{_('Internal Document-ID')}:</div>
				<div class="cms-settings-value">{node.uid}</div>
			</div>
		</div>
	{/if}
</div>

<style lang="postcss">
	.cms-settings {
		padding: var(--cms-space-4);
	}

	@media (min-width: 640px) {
		.cms-settings {
			padding: var(--cms-space-6);
		}
	}

	@media (min-width: 768px) {
		.cms-settings {
			padding: var(--cms-space-8);
		}
	}

	.cms-settings-handle-value {
		width: 100%;
	}

	.cms-settings-paths {
		margin-top: var(--cms-space-4);
		margin-bottom: var(--cms-space-8);
		display: table;
		width: 100%;
	}

	.cms-settings-path {
		display: table-row;

		& > div {
			padding: var(--cms-space-2);
			display: table-cell;
		}
	}

	.cms-settings-path-value {
		width: 100%;
	}

	.cms-settings-renderable {
		max-width: var(--cms-size-3xl);
	}

	.cms-settings-section + .cms-settings-section {
		margin-top: var(--cms-space-4);
	}

	.cms-settings-row {
		display: flex;
		flex-direction: row;
		align-items: flex-start;
		gap: var(--cms-space-4);
		margin-top: var(--cms-space-4);
	}

	.cms-settings-value {
		padding-top: var(--cms-space-px);
	}
</style>

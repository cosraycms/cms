<script lang="ts">
	import type { Snippet } from 'svelte';
	import { ZXX, type BlockYoutube } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { setDirty } from '$lib/state';
	import { _ } from '$lib/locale';
	import Setting from '$shell/Setting.svelte';

	type Props = {
		field: BlocksField;
		item: BlockYoutube;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), index, children }: Props = $props();

	let showSettings = $state(false);
	$effect(() => {
		item.meta ??= { aspectRatioX: { [ZXX]: 16 }, aspectRatioY: { [ZXX]: 9 } };
		item.meta.aspectRatioX ??= { [ZXX]: 16 };
		item.meta.aspectRatioY ??= { [ZXX]: 9 };
		item.value ??= { [ZXX]: '' };
	});
	let x = $derived(item.meta.aspectRatioX[ZXX] ? item.meta.aspectRatioX[ZXX] : 16);
	let y = $derived(item.meta.aspectRatioY[ZXX] ? item.meta.aspectRatioY[ZXX] : 9);
	let percent = $derived(parseFloat(((y / x) * 100).toFixed(2)));

	if (!item.value?.[ZXX]) {
		showSettings = true;
	}

	function oninput() {
		setDirty();
	}
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body">
	{#if showSettings}
		<Setting>
			<label for={field.name + '_' + index + '_ytid'}>
				{_('Youtube-ID')}
			</label>
			<div class="cms-blocks-youtube-field-row">
				<input
					id={field.name + '_' + index + '_ytid'}
					name={field.name + '_' + index + '_ytid'}
					type="text"
					maxlength="20"
					placeholder={_('Fügen Sie hier die Youtube-ID ein')}
					bind:value={item.value[ZXX]}
					{oninput} />
			</div>
		</Setting>
		<Setting>
			<label for={field.name + '_' + index + '_x'}>
				{_('Seitenverhältnis')}
			</label>
			<div class="cms-blocks-youtube-ratio-row">
				<input
					id={field.name + '_' + index + '_x'}
					name={field.name + '_' + index + '_x'}
					type="number"
					max="100"
					min="1"
					placeholder={_('Breite')}
					bind:value={item.meta.aspectRatioX[ZXX]}
					{oninput} />
				<input
					id={field.name + '_' + index + '_y'}
					name={field.name + '_' + index + '_y'}
					type="number"
					max="100"
					min="1"
					placeholder={_('Höhe')}
					bind:value={item.meta.aspectRatioY[ZXX]}
					{oninput} />
			</div>
		</Setting>
	{:else}
		<div class="youtube-container">
			<div
				class="cms-blocks-youtube-frame"
				style="padding-top: {percent}%">
				<iframe
					class="youtube cms-blocks-youtube-iframe"
					title="Youtube Video"
					src="https://www.youtube.com/embed/{item.value[ZXX]}"
					allowfullscreen>
				</iframe>
			</div>
		</div>
	{/if}
</div>

<style lang="postcss">
	.cms-blocks-youtube-field-row {
		margin-top: var(--cms-space-2);
	}

	.cms-blocks-youtube-ratio-row {
		display: flex;
		flex-direction: row;
		gap: var(--cms-space-4);
		margin-top: var(--cms-space-2);
	}

	.cms-blocks-youtube-frame {
		position: relative;
	}

	.cms-blocks-youtube-iframe {
		position: absolute;
		top: 0;
		left: 0;
		height: 100%;
		width: 100%;
	}
</style>

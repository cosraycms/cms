<script lang="ts">
	import { __ } from '$lib/locale';
	import Icon from '$components/Icon.svelte';

	type Props = {
		upload: () => void;
		library: () => void;
		// A figure's hover bar draws its own quiet trigger style.
		quiet?: boolean;
	};

	let { upload, library, quiet = false }: Props = $props();

	const id = $props.id();
</script>

<button
	type="button"
	class={['replace', quiet ? 'quiet' : 'cms-button secondary small']}
	popovertarget="{id}-replace"
	aria-haspopup="menu"
>
	{__('media:replace')}
	<Icon name="chevron-down" />
</button>
<div
	id="{id}-replace"
	class="cms-action-menu cms-replace-menu"
	popover="auto"
	data-action-menu
	data-align="end"
>
	<button type="button" onclick={library}>
		<Icon name="folder2-open" />
		<span class="choice">
			{__('media:browse')}
			<small>{__('media:browse-hint')}</small>
		</span>
	</button>
	<button type="button" onclick={upload}>
		<Icon name="cloud-upload" />
		<span class="choice">
			{__('upload:from-device')}
			<small>{__('upload:from-device-hint')}</small>
		</span>
	</button>
</div>

<style>
	@layer panel {
		.replace {
			flex-shrink: 0;

			& :global(svg:last-child) {
				width: 0.625rem;
				height: 0.625rem;
			}
		}

		.cms-replace-menu {
			--width: max-content;

			& button {
				align-items: flex-start;
				padding-block: var(--cms-space-2);
			}

			& :global(svg) {
				flex-shrink: 0;
				width: 1rem;
				height: 1rem;
				margin-top: 0.125rem;
				color: var(--cms-color-text-muted);
			}

			& .choice {
				display: flex;
				flex-direction: column;
				gap: var(--cms-space-0-5);
			}

			& small {
				color: var(--cms-color-text-subtle);
				font-size: var(--cms-font-size-xs);
			}
		}
	}
</style>

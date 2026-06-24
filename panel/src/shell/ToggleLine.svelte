<script lang="ts">
	import Toggle from '$shell/Toggle.svelte';

	type Props = {
		title: string;
		subtitle: string;
		value: boolean;
	};

	let { title, subtitle, value = $bindable() }: Props = $props();
</script>

<div class="cms-toggle-line">
	<span class="cms-toggle-line-copy">
		<span class="cms-toggle-line-title" id="availability-label">
			{title}
		</span>
		<span class="cms-toggle-line-subtitle" id="availability-description">
			{subtitle}
		</span>
	</span>
	<Toggle bind:value />
</div>

<style>
	@layer panel {
		.cms-toggle-line {
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		.cms-toggle-line-copy {
			display: flex;
			flex: 1 1 auto;
			flex-direction: column;
		}

		.cms-toggle-line-title {
			font-size: var(--cms-font-size-sm);
			line-height: 1.5rem;
			font-weight: 600;
			color: var(--cms-color-neutral-900);
		}

		.cms-toggle-line-subtitle {
			font-size: var(--cms-font-size-sm);
			color: var(--cms-color-neutral-500);
		}
	}
</style>

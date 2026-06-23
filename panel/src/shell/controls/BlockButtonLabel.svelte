<script lang="ts">
	type Props = {
		value: number | null;
	};

	let { value }: Props = $props();
</script>

{#if value !== null}
	<span class="block-button-label">
		{value}
	</span>
{/if}

<style lang="postcss">
	span.block-button-label {
		position: absolute;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: var(--cms-radius-full);
		background-color: var(--cms-color-danger-700);
		font-weight: 600;
		color: var(--cms-color-white);
		font-size: 9px;
		height: 12px;
		width: 12px;
		line-height: 9px;
		top: -13px;
		left: 3px;
	}
</style>

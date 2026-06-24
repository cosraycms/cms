<script lang="ts">
	import { toggleNav } from '../lib/ui';
	import IcoBarsCenterLeft from '$shell/icons/IcoBarsCenterLeft.svelte';
</script>

<button class="cms-nav-toggle" onclick={toggleNav}>
	<IcoBarsCenterLeft />
</button>

<style>
	@layer panel {
		button.cms-nav-toggle {
			position: absolute;
			left: var(--space-2);
			width: 2.25rem;
			height: 2.25rem;
			padding: var(--space-2);
			background-color: var(--color-white);
			border: 0;
			box-shadow: none;
			cursor: pointer;
		}
	}
</style>

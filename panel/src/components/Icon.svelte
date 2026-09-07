<script lang="ts">
	import { icon } from '$lib/icons';

	let { name }: { name: string } = $props();
</script>

{@html icon(name)}

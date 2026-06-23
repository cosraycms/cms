<script lang="ts">
	import type { FileData } from '$types/data';
	import type { ImageField } from '$types/fields';
	import FileLike from './FileLike.svelte';

	type Props = {
		field: ImageField;
		data: FileData;
		node: string;
	};

	let { field, data = $bindable(), node }: Props = $props();
</script>

<FileLike type="video" {field} {data} {node} />

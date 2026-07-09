<script lang="ts">
	import { __ } from '$lib/locale';
	import Button from '$shell/Button.svelte';

	type Props = {
		title?: any;
		body: any;
		type?: string;
		close: any;
	};

	let { title = null, body, type = 'info', close }: Props = $props();
</script>

<div class="modal {type}">
	{#if title}
		<h2>{title}</h2>
	{/if}
	<div class="body">
		{body}
	</div>
	<div class="controls">
		<Button variant="secondary" onclick={close}>
			{__('common:ok')}
		</Button>
	</div>
</div>

<script lang="ts">
	import type { PageData } from './$types';
	import EmbeddedNode from '$shell/EmbeddedNode.svelte';
	import { create, createAndClose } from '$lib/node';
	import Modal from '$shell/modal/Modal.svelte';
	import Toasts from '$shell/Toasts.svelte';

	type Props = {
		data: PageData;
	};

	let { data }: Props = $props();
	let node = $state(data.node);

	async function save(publish: boolean): Promise<boolean> {
		if (publish) {
			node.published = true;
		}

		const result = await create(node, node.type.handle, `embed/node/${data.type}`);

		return result?.success === true;
	}

	async function saveAndClose() {
		node.published = true;
		await createAndClose(node, node.type.handle, `embed/node/${data.type}`);
	}
</script>

<Modal>
	<EmbeddedNode
		bind:node
		fields={data.fields}
		{save}
		{saveAndClose} />
	<Toasts />
</Modal>

<script lang="ts">
	import type { PageData } from './$types';
	import type { Node } from '$types/data';
	import req from '$lib/req';
	import { save as saveNode, saveAndClose as saveAndCloseNode } from '$lib/node';
	import EmbeddedNode from '$shell/EmbeddedNode.svelte';
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

		const result = await saveNode(node.uid, node);

		if (!result?.success) {
			return false;
		}

		const response = await req.get(`node/${result.uid}`, {});

		if (response?.ok) {
			node = response.data as Node;
		}

		return true;
	}

	async function saveAndClose() {
		node.published = true;
		await saveAndCloseNode(node.uid, node);
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

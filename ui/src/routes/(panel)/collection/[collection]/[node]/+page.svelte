<script lang="ts">
	import type { PageData } from './$types';
	import type { Node as NodeType } from '$types/data';
	import req from '$lib/req';
	import { save as saveNode } from '$lib/node';
	import Node from '$shell/Node.svelte';

	type Props = {
		data: PageData;
	};

	type CollectionState = {
		name: string;
		slug: string;
		q?: string;
		offset?: number;
		limit?: number;
		sort?: string;
		dir?: string;
	};

	let { data }: Props = $props();
	let collection = (data.collection ?? {
		name: '',
		slug: '',
	}) as CollectionState;
	let node = $state(data.node as NodeType);

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
			node = response.data as NodeType;
		}

		return true;
	}
</script>

<Node
	bind:node
	{collection}
	{save} />

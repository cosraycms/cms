<script lang="ts">
	import type { BeforeNavigate } from '@sveltejs/kit';
	import type { Node as NodeType } from '$types/data';
	import type { ModalFunctions } from '$shell/modal';

	import { getContext } from 'svelte';
	import { beforeNavigate, goto } from '$app/navigation';
	import { dirty, setPristine } from '$lib/state';
	import NodeEditor from '$shell/NodeEditor.svelte';
	import ModalDirty from '$shell/modals/ModalDirty.svelte';

	type Props = {
		node: NodeType;
		collection: {
			name: string;
			slug: string;
			q?: string;
			offset?: number;
			limit?: number;
			sort?: string;
			dir?: string;
		};
		save: (published: boolean) => Promise<boolean>;
	};

	let { node = $bindable(), collection, save }: Props = $props();
	let { open, close } = getContext<ModalFunctions>('modal');

	beforeNavigate(({ cancel, to }: BeforeNavigate) => {
		if ($dirty) {
			if (to === null) {
				cancel();
			} else {
				cancel();
				open(
					ModalDirty,
					{
						proceed: () => {
							setPristine();
							close();
							goto(to.url);
						},
						close,
					},
					{
						hideClose: true,
					},
				);
			}
		}
	});
</script>

<NodeEditor
	bind:node
	{collection}
	{save} />

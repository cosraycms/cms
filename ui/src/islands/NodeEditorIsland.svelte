<script lang="ts">
	type CollectionState = {
		name: string;
		slug: string;
		q?: string;
		offset?: number;
		limit?: number;
		sort?: string;
		dir?: string;
	};

	export type EditorBootstrap = {
		mode: 'edit' | 'create';
		collection: CollectionState;
		node: string | null;
		type: string | null;
		parent: string | null;
		apiBase: string;
		bootUrl: string;
		panelPath: string;
		backUrl: string;
	};

	type Props = {
		bootstrap: EditorBootstrap;
	};

	let { bootstrap }: Props = $props();
</script>

<div class="cosray-node-editor-placeholder">
	<p class="eyebrow">Svelte editor island</p>
	<h2>{bootstrap.collection.name}</h2>
	<p>The editor bundle is loaded. Runtime wiring follows in the next implementation slice.</p>
</div>

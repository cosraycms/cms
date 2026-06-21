<svelte:options customElement="cosray-richtext-editor" />

<script lang="ts">
	import { baseKeymap, toggleMark } from 'prosemirror-commands';
	import { history, redo, undo } from 'prosemirror-history';
	import { keymap } from 'prosemirror-keymap';
	import { DOMParser, DOMSerializer } from 'prosemirror-model';
	import { schema } from 'prosemirror-schema-basic';
	import { EditorState, type Command } from 'prosemirror-state';
	import { EditorView } from 'prosemirror-view';
	import { onMount } from 'svelte';

	import { findValueInput, syncValueInput } from './shared/input';

	let root: HTMLDivElement;
	let view: EditorView | null = null;

	function host(): HTMLElement {
		const rootNode = root.getRootNode();

		return rootNode instanceof ShadowRoot && rootNode.host instanceof HTMLElement
			? rootNode.host
			: root;
	}

	function parse(html: string) {
		const element = document.createElement('div');
		element.innerHTML = html;

		return DOMParser.fromSchema(schema).parse(element);
	}

	function serialize(state: EditorState): string {
		const element = document.createElement('div');
		const fragment = DOMSerializer.fromSchema(schema).serializeFragment(state.doc.content);
		element.appendChild(fragment);

		return element.innerHTML;
	}

	function run(command: Command): void {
		if (view === null) {
			return;
		}

		command(view.state, view.dispatch, view);
		view.focus();
	}

	onMount(() => {
		const element = host();
		const input = findValueInput(element);

		view = new EditorView(root, {
			state: EditorState.create({
				doc: parse(input?.value ?? ''),
				plugins: [
					history(),
					keymap({
						'Mod-z': undo,
						'Mod-y': redo,
						'Mod-Shift-z': redo
					}),
					keymap(baseKeymap)
				]
			}),
			dispatchTransaction(transaction) {
				if (view === null) {
					return;
				}

				const state = view.state.apply(transaction);
				view.updateState(state);

				if (transaction.docChanged) {
					syncValueInput(element, input, serialize(state));
				}
			}
		});

		return () => {
			view?.destroy();
			view = null;
		};
	});
</script>

<div class="toolbar" part="toolbar">
	<button type="button" onclick={() => run(toggleMark(schema.marks.strong))}>Bold</button>
	<button type="button" onclick={() => run(toggleMark(schema.marks.em))}>Italic</button>
</div>
<div class="editor" bind:this={root}></div>

<style>
	:host {
		display: block;
	}

	.toolbar {
		display: flex;
		gap: 0.5rem;
		margin-bottom: 0.5rem;
	}

	.toolbar button {
		border: 1px solid #cbd5e1;
		border-radius: 0.375rem;
		background: #ffffff;
		padding: 0.25rem 0.5rem;
	}

	.editor {
		border: 1px solid #cbd5e1;
		border-radius: 0.5rem;
		min-height: 12rem;
		padding: 0.75rem;
	}

	.editor :global(.ProseMirror) {
		min-height: 10.5rem;
		outline: none;
	}

	.editor :global(.ProseMirror p:first-child) {
		margin-top: 0;
	}

	.editor :global(.ProseMirror p:last-child) {
		margin-bottom: 0;
	}
</style>

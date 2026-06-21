<svelte:options customElement="cosray-code-editor" />

<script lang="ts">
	import { css } from '@codemirror/lang-css';
	import { html } from '@codemirror/lang-html';
	import { javascript } from '@codemirror/lang-javascript';
	import { json } from '@codemirror/lang-json';
	import { markdown } from '@codemirror/lang-markdown';
	import { php } from '@codemirror/lang-php';
	import { sql } from '@codemirror/lang-sql';
	import { xml } from '@codemirror/lang-xml';
	import { yaml } from '@codemirror/lang-yaml';
	import { EditorView } from '@codemirror/view';
	import { basicSetup } from 'codemirror';
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

	function language(name: string) {
		switch (name.toLowerCase()) {
			case 'css':
				return css();
			case 'html':
				return html();
			case 'javascript':
			case 'js':
				return javascript();
			case 'json':
				return json();
			case 'markdown':
			case 'md':
				return markdown();
			case 'php':
				return php();
			case 'sql':
				return sql();
			case 'xml':
				return xml();
			case 'yaml':
			case 'yml':
				return yaml();
			default:
				return [];
		}
	}

	onMount(() => {
		const element = host();
		const input = findValueInput(element);
		const syntax = element.getAttribute('syntax') ?? element.getAttribute('language') ?? 'plaintext';

		view = new EditorView({
			doc: input?.value ?? '',
			extensions: [
				basicSetup,
				language(syntax),
				EditorView.lineWrapping,
				EditorView.updateListener.of((update) => {
					if (update.docChanged) {
						syncValueInput(element, input, update.state.doc.toString());
					}
				})
			],
			parent: root
		});

		return () => {
			view?.destroy();
			view = null;
		};
	});
</script>

<div class="editor" bind:this={root}></div>

<style>
	:host {
		display: block;
	}

	.editor {
		border: 1px solid #cbd5e1;
		border-radius: 0.5rem;
		min-height: 12rem;
		overflow: hidden;
	}

	.editor :global(.cm-editor) {
		min-height: 12rem;
	}
</style>

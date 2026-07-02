<script lang="ts">
	import type { Extension } from '@codemirror/state';

	import { onDestroy, onMount } from 'svelte';
	import { Compartment, EditorState, Annotation } from '@codemirror/state';
	import { EditorView, keymap, lineNumbers, highlightActiveLineGutter } from '@codemirror/view';
	import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
	import {
		foldGutter,
		foldKeymap,
		syntaxHighlighting,
		defaultHighlightStyle,
	} from '@codemirror/language';
	import { indentOnInput, bracketMatching } from '@codemirror/language';
	import { drawSelection, highlightActiveLine, rectangularSelection } from '@codemirror/view';
	import {
		DEFAULT_CODE_SYNTAX,
		loadCodeLanguageExtension,
		normalizeCodeSyntax,
	} from '$shell/code/languages';

	type Props = {
		name: string;
		value: string;
		syntax?: string;
		required?: boolean;
		readonly?: boolean;
		notify?: () => void;
	};

	let {
		name,
		value = $bindable(),
		syntax = $bindable(DEFAULT_CODE_SYNTAX),
		required = false,
		readonly = false,
		notify = () => {},
	}: Props = $props();

	let editorElement = $state<HTMLElement>();
	let editor: EditorView | null = null;
	let languageLoadId = 0;
	let applyingExternalValue = false;

	const externalUpdate = Annotation.define<boolean>();
	const languageCompartment = new Compartment();
	const readOnlyCompartment = new Compartment();

	function editorExtensions(languageExtension: Extension): Extension[] {
		return [
			lineNumbers(),
			highlightActiveLineGutter(),
			history(),
			drawSelection(),
			EditorState.allowMultipleSelections.of(true),
			indentOnInput(),
			bracketMatching(),
			rectangularSelection(),
			highlightActiveLine(),
			foldGutter(),
			syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
			keymap.of([...defaultKeymap, ...historyKeymap, ...foldKeymap, indentWithTab]),
			languageCompartment.of(languageExtension),
			readOnlyCompartment.of(EditorState.readOnly.of(readonly)),
			EditorView.updateListener.of((update) => {
				if (!update.docChanged) {
					return;
				}

				value = update.state.doc.toString();

				if (applyingExternalValue) {
					return;
				}

				const hasExternalUpdate = update.transactions.some((transaction) =>
					transaction.annotation(externalUpdate),
				);

				if (!hasExternalUpdate) {
					notify();
				}
			}),
		];
	}

	async function reconfigureLanguage(nextSyntax: string) {
		if (!editor) {
			return;
		}

		const currentLoadId = ++languageLoadId;
		const extension = await loadCodeLanguageExtension(nextSyntax);

		if (!editor || currentLoadId !== languageLoadId) {
			return;
		}

		editor.dispatch({
			effects: languageCompartment.reconfigure(extension),
		});
	}

	function replaceDoc(nextValue: string) {
		if (!editor) {
			return;
		}

		const current = editor.state.doc.toString();

		if (current === nextValue) {
			return;
		}

		applyingExternalValue = true;
		editor.dispatch({
			changes: { from: 0, to: editor.state.doc.length, insert: nextValue },
			annotations: externalUpdate.of(true),
		});
		applyingExternalValue = false;
	}

	onMount(async () => {
		if (!editorElement) {
			return;
		}

		syntax = normalizeCodeSyntax(syntax);
		const initialLanguage = await loadCodeLanguageExtension(syntax);

		editor = new EditorView({
			state: EditorState.create({
				doc: value ?? '',
				extensions: editorExtensions(initialLanguage),
			}),
			parent: editorElement,
		});
	});

	onDestroy(() => {
		editor?.destroy();
		editor = null;
	});

	$effect(() => {
		if (!editor) {
			return;
		}

		replaceDoc(value ?? '');
	});

	$effect(() => {
		if (!editor) {
			return;
		}

		const normalized = normalizeCodeSyntax(syntax);

		if (normalized !== syntax) {
			syntax = normalized;
		}

		void reconfigureLanguage(normalized);
	});

	$effect(() => {
		if (!editor) {
			return;
		}

		editor.dispatch({
			effects: readOnlyCompartment.reconfigure(EditorState.readOnly.of(readonly)),
		});
	});
</script>

<div class="cms-code-editor" bind:this={editorElement}></div>

<textarea
	class="cms-code-editor-input"
	{name}
	bind:value
	{required}
	readonly
	tabindex="-1"
	aria-hidden="true"
>
</textarea>

<script lang="ts">
	import type { Extension } from '@codemirror/state';

	import { onDestroy, onMount } from 'svelte';
	import { Compartment, EditorState, Annotation } from '@codemirror/state';
	import { EditorView, keymap, lineNumbers, highlightActiveLineGutter } from '@codemirror/view';
	import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
	import { foldGutter, foldKeymap } from '@codemirror/language';
	import { indentOnInput, bracketMatching } from '@codemirror/language';
	import { drawSelection, highlightActiveLine, rectangularSelection } from '@codemirror/view';
	import {
		DEFAULT_CODE_SYNTAX,
		loadCodeLanguageExtension,
		normalizeCodeSyntax,
	} from '$components/code/languages';
	import { cosrayCodeTheme } from '$components/code/theme';

	type Props = {
		name: string;
		value: string;
		syntax?: string;
		required?: boolean;
		readonly?: boolean;
		fallback?: string;
		fallbackLabel?: string;
		notify?: () => void;
	};

	let {
		name,
		value = $bindable(),
		syntax = $bindable(DEFAULT_CODE_SYNTAX),
		required = false,
		readonly = false,
		fallback = '',
		fallbackLabel = '',
		notify = () => {},
	}: Props = $props();

	let editorElement = $state<HTMLElement>();
	let editor: EditorView | null = null;
	let languageLoadId = 0;
	let applyingExternalValue = false;
	let focused = $state(false);

	function preview(
		element: HTMLElement,
		initial: { value: string; syntax: string },
	): {
		update: (next: { value: string; syntax: string }) => void;
		destroy: () => void;
	} {
		let view: EditorView | null = null;
		let loadId = 0;
		let destroyed = false;

		async function render(next: { value: string; syntax: string }) {
			const currentLoadId = ++loadId;
			const language = await loadCodeLanguageExtension(next.syntax);

			if (destroyed || currentLoadId !== loadId) {
				return;
			}

			view?.destroy();
			view = new EditorView({
				state: EditorState.create({
					doc: next.value,
					extensions: [
						lineNumbers(),
						cosrayCodeTheme,
						language,
						EditorState.readOnly.of(true),
						EditorView.editable.of(false),
					],
				}),
				parent: element,
			});
		}

		void render(initial);

		return {
			update: (next) => void render(next),
			destroy() {
				destroyed = true;
				view?.destroy();
			},
		};
	}

	function focusOut(event: FocusEvent) {
		if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) {
			focused = false;
		}
	}

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
			cosrayCodeTheme,
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

<div class="cms-code-editor-wrap" onfocusin={() => (focused = true)} onfocusout={focusOut}>
	{#if value === '' && fallback !== '' && !focused}
		<div class="cms-code-editor-fallback" aria-hidden="true">
			<div
				class="cms-code-editor cms-code-editor-preview"
				use:preview={{ value: fallback, syntax }}
			></div>
			<span>{fallbackLabel}</span>
		</div>
	{/if}
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
</div>

<script lang="ts">
	import type { PrismEditor } from 'prism-code-editor';

	import { onDestroy, onMount } from 'svelte';
	import { createEditor } from 'prism-code-editor';
	import { defaultKeymap, editHistory, editorCommands } from 'prism-code-editor/commands';
	import { highlightBracketPairs } from 'prism-code-editor/highlight-brackets';
	import { matchBrackets } from 'prism-code-editor/match-brackets';
	import {
		DEFAULT_CODE_SYNTAX,
		loadCodeLanguage,
		normalizeCodeSyntax,
	} from '$components/code/languages';

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
	let editor: PrismEditor | null = null;
	let languageLoadId = 0;
	let focused = $state(false);

	function preview(
		element: HTMLElement,
		initial: { value: string; syntax: string },
	): {
		update: (next: { value: string; syntax: string }) => void;
		destroy: () => void;
	} {
		let view: PrismEditor | null = null;
		let loadId = 0;
		let destroyed = false;

		async function render(next: { value: string; syntax: string }) {
			const currentLoadId = ++loadId;
			const language = await loadCodeLanguage(next.syntax);

			if (destroyed || currentLoadId !== loadId) {
				return;
			}

			view?.remove();
			view = createEditor(element, { language, value: next.value, readOnly: true });
		}

		void render(initial);

		return {
			update: (next) => void render(next),
			destroy() {
				destroyed = true;
				view?.remove();
			},
		};
	}

	function focusOut(event: FocusEvent) {
		if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) {
			focused = false;
		}
	}

	async function reconfigureLanguage(nextSyntax: string) {
		if (!editor) {
			return;
		}

		const currentLoadId = ++languageLoadId;
		const language = await loadCodeLanguage(nextSyntax);

		if (!editor || currentLoadId !== languageLoadId) {
			return;
		}

		editor.setOptions({ language });
	}

	// Replacing the text reports no change: the update it causes leaves
	// the editor's text equal to value.
	function replaceDoc(nextValue: string) {
		if (!editor || editor.value === nextValue) {
			return;
		}

		editor.setOptions({ value: nextValue });
	}

	onMount(async () => {
		if (!editorElement) {
			return;
		}

		syntax = normalizeCodeSyntax(syntax);
		const language = await loadCodeLanguage(syntax);

		editor = createEditor(
			editorElement,
			{
				language,
				value: value ?? '',
				readOnly: readonly,
				// Also called for the initial value, a language switch and a
				// replaced text, which leave the text equal to value.
				onUpdate: (next) => {
					if (next === value) {
						return;
					}

					value = next;
					notify();
				},
			},
			editHistory(),
			editorCommands(defaultKeymap),
			matchBrackets(false),
			highlightBracketPairs(),
		);
	});

	onDestroy(() => {
		editor?.remove();
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

		editor.setOptions({ readOnly: readonly });
	});
</script>

<div class="cms-code-editor-wrap" onfocusin={() => (focused = true)} onfocusout={focusOut}>
	{#if value === '' && fallback !== '' && !focused}
		<!-- Inert: hidden from assistive technology, and the read-only
		     editor's textarea cannot take focus. -->
		<div class="cms-code-editor-fallback" inert>
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

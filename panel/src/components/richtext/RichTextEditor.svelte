<script lang="ts">
	import { DOMSerializer } from 'prosemirror-model';
	import type { EditorState } from 'prosemirror-state';

	import { mount, onDestroy, onMount, unmount } from 'svelte';

	import type { AssetInfo } from '$types/data';

	import { cosray } from '$lib/bridge';
	import { __ } from '$lib/locale';
	import ModalImage from '$components/modals/ModalImage.svelte';
	import ModalLink from '$components/modals/ModalLink.svelte';
	import createEditor, { type CmsEditor } from './editor';
	import { docToPm, isFilledDoc, type RichtextDoc } from './format';
	import { schema } from './schema';
	import {
		isMarkActive,
		isNodeActive,
		getActiveTextAlign,
		getMarkAttributes,
		getBlockAttributes,
	} from './state-helpers';
	import {
		toggleBold,
		toggleItalic,
		toggleStrike,
		toggleSubscript,
		toggleSuperscript,
		toggleBulletList,
		toggleOrderedList,
		toggleBlockquote,
		setTextAlign,
		unsetTextAlign,
		setParagraphClass,
		setHeading,
		setParagraph,
		insertHorizontalRule,
		insertHardBreak,
		setLink,
		unsetLink,
		clearMarks,
		clearNodes,
		setStyle,
		unsetStyle,
		insertImage,
	} from './commands';
	import { undo, redo } from 'prosemirror-history';

	import Icon from '$components/Icon.svelte';

	// Mirrors Cosray\Schema\Tool::defaults() — the set an editor gets when
	// neither the field nor the project configures one.
	const defaultTools = [
		'undo',
		'redo',
		'bold',
		'italic',
		'strike',
		'h2',
		'h3',
		'bullet-list',
		'ordered-list',
		'link',
	];

	type Props = {
		value: RichtextDoc | null;
		name: string;
		required?: boolean;
		fallback?: RichtextDoc | null;
		fallbackLabel?: string;
		toolbar?: 'default' | 'inline';
		embed?: boolean;
		notify?: () => void;
		/** Toolbar tool set (`#[Tools]` / `richtext.tools`). */
		tools?: string[];
		/** Declared paragraph classes (`richtext.classes`). */
		classes?: Record<string, string>;
		/** Declared text styles (`richtext.styles`). */
		styles?: Record<string, string>;
		/** Resolve an asset uid to a display URL for inline images. */
		assetUrl?: (uid: string) => string | null;
		/** Register an asset picked from the library or uploaded. */
		onAsset?: (uid: string, info: AssetInfo) => void;
	};

	let {
		value = $bindable(),
		name,
		required = false,
		fallback = null,
		fallbackLabel = '',
		toolbar = 'default',
		embed = false,
		notify = () => {},
		tools = defaultTools,
		classes = {},
		styles = {},
		assetUrl = () => null,
		onAsset = () => {},
	}: Props = $props();
	let ref = $state<HTMLElement>();
	let fallbackRef = $state<HTMLElement>();
	let bubble = $state<HTMLElement>();
	let editor = $state<CmsEditor>();
	let editorState = $state({
		bold: false,
		heading1: false,
		heading2: false,
		heading3: false,
		paragraphClass: null as string | null,
		center: false,
		right: false,
		justify: false,
		italic: false,
		strike: false,
		bulletList: false,
		orderedList: false,
		subscript: false,
		superscript: false,
		blockquote: false,
		link: false,
		styleClass: null as string | null,
	});
	let showSource = $state(false);
	let sourceHtml = $state('');
	let showDropdown = $state(false);
	let showStyleDropdown = $state(false);
	const menuId = $props.id();
	let focused = $state(false);
	let showFallback = $derived(
		isFilledDoc(fallback) && !isFilledDoc(value) && !focused && !showSource,
	);

	$effect(() => {
		if (!fallbackRef) return;

		fallbackRef.replaceChildren();

		if (isFilledDoc(fallback)) {
			fallbackRef.append(
				DOMSerializer.fromSchema(schema).serializeFragment(docToPm(fallback).content, { document }),
			);

			for (const image of fallbackRef.querySelectorAll<HTMLImageElement>('img[data-uid]')) {
				const url = assetUrl(image.dataset.uid ?? '');

				if (url) image.src = url;
			}
		}
	});

	function focusIn() {
		focused = true;
	}

	function focusOut(event: FocusEvent) {
		if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) {
			focused = false;
		}
	}

	// Config-declared options: both lists are empty unless the app
	// declares entries — no built-in styling escape hatches.
	let classOptions = $derived(Object.entries(classes));
	let styleOptions = $derived(Object.entries(styles));

	function updateEditorState(state: EditorState) {
		editorState.bold = isMarkActive(state, schema.marks.bold);
		editorState.heading1 = isNodeActive(state, schema.nodes.heading, { level: 1 });
		editorState.heading2 = isNodeActive(state, schema.nodes.heading, { level: 2 });
		editorState.heading3 = isNodeActive(state, schema.nodes.heading, { level: 3 });
		const isParagraph = isNodeActive(state, schema.nodes.paragraph);
		const paragraphAttrs = getBlockAttributes(state, schema.nodes.paragraph);
		editorState.paragraphClass = isParagraph ? (paragraphAttrs?.class ?? 'default') : null;
		editorState.center = getActiveTextAlign(state) === 'center';
		editorState.right = getActiveTextAlign(state) === 'right';
		editorState.justify = getActiveTextAlign(state) === 'justify';
		editorState.italic = isMarkActive(state, schema.marks.italic);
		editorState.strike = isMarkActive(state, schema.marks.strike);
		editorState.bulletList = isNodeActive(state, schema.nodes.bulletList);
		editorState.orderedList = isNodeActive(state, schema.nodes.orderedList);
		editorState.subscript = isMarkActive(state, schema.marks.subscript);
		editorState.superscript = isMarkActive(state, schema.marks.superscript);
		editorState.blockquote = isNodeActive(state, schema.nodes.blockquote);
		editorState.link = isMarkActive(state, schema.marks.link);
		const styleAttrs = getMarkAttributes(state, schema.marks.style);
		editorState.styleClass = styleAttrs?.class ?? null;
	}

	onMount(() => {
		if (!ref) return;

		editor = createEditor({
			element: ref,
			content: value,
			mode: toolbar,
			bubbleElement: bubble,
			assetUrl,
			// The bind write must land before notify: the element serializes
			// the bound map into the cosray-change detail when notified.
			onUpdate: (doc) => {
				value = doc;
				notify();
			},
			onStateChange: updateEditorState,
		});
	});

	onDestroy(() => {
		editor?.destroy();
	});

	function changeSource(event: KeyboardEvent) {
		const target = event.target as HTMLTextAreaElement;

		// setContent dispatches a changed transaction, which routes the
		// parsed document back through onUpdate.
		editor?.setContent(target.value);
	}

	function run(command: (state: any, dispatch?: any, view?: any) => boolean) {
		return () => {
			showDropdown = false;
			showStyleDropdown = false;
			editor?.run(command);
		};
	}

	function runDropdown(command: (state: any, dispatch?: any, view?: any) => boolean) {
		return () => {
			editor?.run(command);
			showDropdown = !showDropdown;
			showStyleDropdown = false;
		};
	}

	function runStyleDropdown(command: (state: any, dispatch?: any, view?: any) => boolean) {
		return () => {
			editor?.run(command);
			showStyleDropdown = false;
			showDropdown = false;
		};
	}

	function toggleSource() {
		if (!showSource) {
			sourceHtml = editor?.getHTML() ?? '';
		}

		showSource = !showSource;
		showDropdown = false;
		showStyleDropdown = false;
	}

	function addLink(target: { href?: string; node?: string; asset?: string }, blank: boolean) {
		if (!editor) return;
		const href = target.href ?? '';
		const node = target.node ?? '';
		const asset = target.asset ?? '';
		if (href === '' && node === '' && asset === '') return;

		editor.run(
			setLink({
				href: href || null,
				node: node || null,
				asset: asset || null,
				target: blank ? '_blank' : '',
				class: undefined,
			}),
		);
	}

	function openAddLinkModal() {
		if (!editor) return;
		const state = editor.view.state;
		const linkAttrs = getMarkAttributes(state, schema.marks.link);
		const href = typeof linkAttrs?.href === 'string' ? linkAttrs.href : '';
		const node = typeof linkAttrs?.node === 'string' ? linkAttrs.node : '';
		const asset = typeof linkAttrs?.asset === 'string' ? linkAttrs.asset : '';
		const target = linkAttrs?.target ?? '';

		const handle = cosray().modal.open(
			(host) => {
				const app = mount(ModalLink, {
					target: host,
					props: {
						add: addLink,
						close: () => handle.close(),
						href,
						node,
						asset,
						blank: target === '_blank',
					},
				});

				return () => void unmount(app);
			},
			{ owner: ref },
		);
	}

	function addImage(uid: string, info: AssetInfo) {
		if (!editor) return;
		onAsset(uid, info);
		editor.run(insertImage(uid));
	}

	function openAddImageModal() {
		if (!editor) return;

		const handle = cosray().modal.open(
			(host) => {
				const app = mount(ModalImage, {
					target: host,
					props: {
						add: addImage,
						close: () => handle.close(),
					},
				});

				return () => void unmount(app);
			},
			{ owner: ref },
		);
	}

	function toggleHeading(level: 1 | 2 | 3) {
		return () => {
			const active =
				level === 1
					? editorState.heading1
					: level === 2
						? editorState.heading2
						: editorState.heading3;

			run(active ? setParagraph() : setHeading(level))();
		};
	}

	type ToolSpec = {
		key: string;
		tool: string;
		icon: string;
		label: string;
		onclick: () => void;
		isActive?: () => boolean;
		isVisible?: () => boolean;
	};

	// The full vocabulary in canonical order; `tools` picks the subset, so a
	// configured list is a set, not a layout.
	const toolbarSpecs: ToolSpec[] = [
		{
			key: 'undo',
			tool: 'undo',
			icon: 'arrow-counterclockwise',
			label: __('richtext:undo'),
			onclick: run(undo),
		},
		{
			key: 'redo',
			tool: 'redo',
			icon: 'arrow-clockwise',
			label: __('richtext:redo'),
			onclick: run(redo),
		},
		{
			key: 'bold',
			tool: 'bold',
			icon: 'type-bold',
			label: __('richtext:bold'),
			onclick: run(toggleBold()),
			isActive: () => editorState.bold,
		},
		{
			key: 'italic',
			tool: 'italic',
			icon: 'type-italic',
			label: __('richtext:italic'),
			onclick: run(toggleItalic()),
			isActive: () => editorState.italic,
		},
		{
			key: 'strike',
			tool: 'strike',
			icon: 'type-strikethrough',
			label: __('richtext:strikethrough'),
			onclick: run(toggleStrike()),
			isActive: () => editorState.strike,
		},
		{
			key: 'h1',
			tool: 'h1',
			icon: 'type-h1',
			label: __('richtext:heading-1'),
			onclick: toggleHeading(1),
			isActive: () => editorState.heading1,
		},
		{
			key: 'h2',
			tool: 'h2',
			icon: 'type-h2',
			label: __('richtext:heading-2'),
			onclick: toggleHeading(2),
			isActive: () => editorState.heading2,
		},
		{
			key: 'h3',
			tool: 'h3',
			icon: 'type-h3',
			label: __('richtext:heading-3'),
			onclick: toggleHeading(3),
			isActive: () => editorState.heading3,
		},
		{
			key: 'sub',
			tool: 'sub',
			icon: 'subscript',
			label: __('richtext:subscript'),
			onclick: run(toggleSubscript()),
			isActive: () => editorState.subscript,
		},
		{
			key: 'sup',
			tool: 'sup',
			icon: 'superscript',
			label: __('richtext:superscript'),
			onclick: run(toggleSuperscript()),
			isActive: () => editorState.superscript,
		},
		{
			key: 'align-left',
			tool: 'align',
			icon: 'text-left',
			label: __('richtext:align-left'),
			onclick: run(unsetTextAlign()),
		},
		{
			key: 'align-center',
			tool: 'align',
			icon: 'text-center',
			label: __('richtext:align-center'),
			onclick: run(setTextAlign('center')),
			isActive: () => editorState.center,
		},
		{
			key: 'align-right',
			tool: 'align',
			icon: 'text-right',
			label: __('richtext:align-right'),
			onclick: run(setTextAlign('right')),
			isActive: () => editorState.right,
		},
		{
			key: 'align-justify',
			tool: 'align',
			icon: 'justify',
			label: __('richtext:justify'),
			onclick: run(setTextAlign('justify')),
			isActive: () => editorState.justify,
		},
		{
			key: 'bullet-list',
			tool: 'bullet-list',
			icon: 'list-ul',
			label: __('richtext:bullet-list'),
			onclick: run(toggleBulletList()),
			isActive: () => editorState.bulletList,
		},
		{
			key: 'ordered-list',
			tool: 'ordered-list',
			icon: 'list-ol',
			label: __('richtext:numbered-list'),
			onclick: run(toggleOrderedList()),
			isActive: () => editorState.orderedList,
		},
		{
			key: 'blockquote',
			tool: 'blockquote',
			icon: 'blockquote-right',
			label: __('richtext:blockquote'),
			onclick: run(toggleBlockquote()),
			isActive: () => editorState.blockquote,
		},
		{
			key: 'hr',
			tool: 'hr',
			icon: 'hr',
			label: __('richtext:horizontal-line'),
			onclick: run(insertHorizontalRule()),
		},
		{
			key: 'link',
			tool: 'link',
			icon: 'link-45deg',
			label: __('richtext:add-page-link'),
			onclick: openAddLinkModal,
		},
		{
			key: 'unlink',
			tool: 'link',
			icon: 'slash-circle',
			label: __('richtext:remove-link'),
			onclick: run(unsetLink()),
			isVisible: () => editorState.link,
		},
		{
			key: 'image',
			tool: 'image',
			icon: 'image',
			label: __('image:insert'),
			onclick: openAddImageModal,
		},
		{
			key: 'br',
			tool: 'br',
			icon: 'arrow-return-left',
			label: __('richtext:hard-break'),
			onclick: run(insertHardBreak()),
		},
		{
			key: 'clear',
			tool: 'clear',
			icon: 'eraser',
			label: __('richtext:remove-formats'),
			onclick: run(clearMarks()),
		},
	];

	let enabled = $derived(new Set(tools));
	let activeSpecs = $derived(toolbarSpecs.filter((spec) => enabled.has(spec.tool)));
</script>

{#if toolbar === 'inline'}
	<div class="richtext-bubble cms-richtext-bubble" bind:this={bubble}>
		{#if editor}
			<button
				type="button"
				aria-label={__('richtext:bold')}
				class="richtext-toolbar-btn"
				onclick={run(toggleBold())}
				class:active={editorState.bold}
			>
				<Icon name="type-bold" />
			</button>
			<button
				type="button"
				aria-label={__('richtext:italic')}
				class="richtext-toolbar-btn"
				onclick={run(toggleItalic())}
				class:active={editorState.italic}
			>
				<Icon name="type-italic" />
			</button>
			<button
				type="button"
				aria-label={__('richtext:strikethrough')}
				class="richtext-toolbar-btn"
				onclick={run(toggleStrike())}
				class:active={editorState.strike}
			>
				<Icon name="type-strikethrough" />
			</button>
			<button
				type="button"
				aria-label={__('richtext:remove-formats')}
				class="richtext-toolbar-btn"
				onclick={run(clearMarks())}
			>
				<Icon name="eraser" />
			</button>
		{/if}
	</div>
{/if}

<div
	class="richtext richtext-{toolbar}"
	class:required
	class:embed
	onfocusin={focusIn}
	onfocusout={focusOut}
>
	{#if editor}
		{#if toolbar !== 'inline'}
			<div
				class="richtext-toolbar cms-richtext-toolbar"
				class:cms-richtext-toolbar-open={!showSource}
				class:tooltip-b={embed}
			>
				{#if showSource}
					<div class="richtext-extras cms-richtext-extras-source">
						<button
							onclick={toggleSource}
							class="richtext-source-btn cms-richtext-source-btn-compact"
						>
							<Icon name="file-earmark-richtext" />
							<span class="cms-richtext-source-label">
								{__('richtext:show-content')}
							</span>
						</button>
					</div>
				{:else}
					{#if classOptions.length > 0}
						<div class="cms-richtext-dropdown-wrap">
							<div class="richtext-dropdown">
								<button
									type="button"
									class="richtext-dropdown-button"
									aria-expanded={showDropdown}
									aria-haspopup="true"
									onclick={() => {
										showDropdown = !showDropdown;
										showStyleDropdown = false;
									}}
								>
									{__('richtext:paragraph')}
									<Icon name="chevron-down" />
								</button>
							</div>
							{#if showDropdown}
								<div
									class="richtext-dropdown-menu"
									role="menu"
									aria-orientation="vertical"
									aria-labelledby="menu-button"
									tabindex="-1"
								>
									<div class="cms-richtext-dropdown-items" role="none">
										<button
											type="button"
											onclick={runDropdown(setParagraph())}
											role="menuitem"
											tabindex="-1"
											class="richtext-dropdown-item"
											class:active={editorState.paragraphClass === 'default'}
										>
											<Icon name="paragraph" />
											<span class="cms-richtext-dropdown-item-label">
												{__('richtext:paragraph')}
											</span>
										</button>
										{#each classOptions as [cls, label] (cls)}
											<button
												type="button"
												onclick={runDropdown(setParagraphClass(cls))}
												role="menuitem"
												tabindex="-1"
												class="richtext-dropdown-item"
												class:active={editorState.paragraphClass === cls}
											>
												<Icon name="type" />
												<span class="cms-richtext-dropdown-item-label">
													{label}
												</span>
											</button>
										{/each}
										<button
											type="button"
											onclick={runDropdown(clearNodes())}
											role="menuitem"
											tabindex="-1"
											class="richtext-dropdown-item"
										>
											<Icon name="eraser" />
											<span class="cms-richtext-dropdown-item-label">
												{__('richtext:remove-format')}
											</span>
										</button>
									</div>
								</div>
							{/if}
						</div>
					{/if}
					{#if styleOptions.length > 0}
						<div class="cms-richtext-dropdown-wrap">
							<div class="richtext-dropdown">
								<button
									type="button"
									class="richtext-dropdown-button"
									aria-label={__('richtext:text-style')}
									aria-expanded={showStyleDropdown}
									aria-haspopup="true"
									onclick={() => {
										showStyleDropdown = !showStyleDropdown;
										showDropdown = false;
									}}
								>
									<Icon name="fonts" />
									<Icon name="chevron-down" />
								</button>
							</div>
							{#if showStyleDropdown}
								<div
									class="richtext-dropdown-menu"
									role="menu"
									aria-orientation="vertical"
									aria-labelledby="style-menu-button"
									tabindex="-1"
								>
									<div class="cms-richtext-dropdown-items" role="none">
										{#each styleOptions as [cls, label] (cls)}
											<button
												onclick={runStyleDropdown(setStyle(cls))}
												role="menuitem"
												tabindex="-1"
												class="richtext-dropdown-item"
												class:active={editorState.styleClass === cls}
											>
												<span class="cms-richtext-dropdown-item-label">
													{label}
												</span>
											</button>
										{/each}
										<button
											onclick={runStyleDropdown(unsetStyle())}
											role="menuitem"
											tabindex="-1"
											class="richtext-dropdown-item"
										>
											<Icon name="eraser" />
											<span class="cms-richtext-dropdown-item-label">
												{__('richtext:remove-style')}
											</span>
										</button>
									</div>
								</div>
							{/if}
						</div>
					{/if}
					<div class="cms-richtext-dropdown-wrap cms-richtext-toolbar-compact-actions">
						<button
							type="button"
							id={`${menuId}-trigger`}
							popovertarget={menuId}
							aria-haspopup="menu"
							class="richtext-dropdown-button cms-richtext-compact-tools-button"
							title={__('richtext:formatting-tools')}
							aria-label={__('richtext:formatting-tools')}
						>
							<Icon name="three-dots-vertical" />
						</button>
						<div
							id={menuId}
							class="cms-action-menu"
							popover="auto"
							data-action-menu
							style="--width: 18rem"
							onbeforetoggle={() => {
								showDropdown = false;
								showStyleDropdown = false;
							}}
						>
							{#each activeSpecs as spec (spec.key)}
								{#if spec.isVisible?.() ?? true}
									<button
										type="button"
										onclick={spec.onclick}
										role={spec.isActive ? 'menuitemcheckbox' : 'menuitem'}
										aria-checked={spec.isActive?.()}
										class:is-active={spec.isActive?.() ?? false}
									>
										<Icon name={spec.icon} />
										<span>{spec.label}</span>
									</button>
								{/if}
							{/each}
						</div>
					</div>
					<div
						class="richtext-toolbar-btns cms-richtext-toolbar-btns-grow cms-richtext-toolbar-main-actions"
					>
						{#each activeSpecs as spec (spec.key)}
							{#if spec.isVisible?.() ?? true}
								<button
									type="button"
									class="richtext-toolbar-btn"
									title={spec.label}
									aria-label={spec.label}
									onclick={spec.onclick}
									class:active={spec.isActive?.() ?? false}
								>
									<Icon name={spec.icon} />
								</button>
							{/if}
						{/each}
					</div>
					{#if enabled.has('source')}
						<div class="richtext-extras">
							<button
								type="button"
								onclick={toggleSource}
								class="richtext-source-btn cms-richtext-source-btn-offset"
							>
								<Icon name="code-slash" />
								<span class="cms-richtext-toolbar-source-label">
									{__('richtext:show-source')}
								</span>
							</button>
						</div>
					{/if}
				{/if}
			</div>
		{/if}
	{/if}

	<div class="cms-richtext-stack" class:has-fallback={showFallback}>
		{#if showFallback}
			<div class="cms-richtext-fallback" aria-hidden="true">
				<div class="ProseMirror" bind:this={fallbackRef}></div>
				<span>{fallbackLabel}</span>
			</div>
		{/if}
		<div
			class="richtext-editor cms-richtext-content cms-richtext-layer-base"
			bind:this={ref}
			data-name={name}
			class:hide={showSource}
		></div>
		<div
			class="richtext-source cms-richtext-source cms-richtext-layer-base"
			class:hide={!showSource}
		>
			<!-- No name: the host carries the value into the form. A named
			     textarea would submit a bare key that, for a sub-field called
			     "content" inside entries, wipes the whole content tree. -->
			<textarea onkeyup={changeSource} bind:value={sourceHtml} class="cms-richtext-source-input">
			</textarea>
		</div>
	</div>
</div>

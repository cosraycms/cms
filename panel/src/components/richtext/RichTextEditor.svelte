<script lang="ts">
	import type { EditorState } from 'prosemirror-state';

	import { type Component, mount, onDestroy, onMount, unmount } from 'svelte';

	import type { AssetInfo } from '$types/data';

	import { cosray } from '$lib/bridge';
	import { __ } from '$lib/locale';
	import ModalImage from '$components/modals/ModalImage.svelte';
	import ModalLink from '$components/modals/ModalLink.svelte';
	import createEditor, { type CmsEditor } from './editor';
	import { type RichtextDoc } from './format';
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

	import IcoH1 from '$components/icons/IcoH1.svelte';
	import IcoH2 from '$components/icons/IcoH2.svelte';
	import IcoH3 from '$components/icons/IcoH3.svelte';
	import IcoBold from '$components/icons/IcoBold.svelte';
	import IcoBlockQuoteRight from '$components/icons/IcoBlockQuoteRight.svelte';
	import IcoParagraph from '$components/icons/IcoParagraph.svelte';
	import IcoHorizontalRule from '$components/icons/IcoHorizontalRule.svelte';
	import IcoTextHeight from '$components/icons/IcoTextHeight.svelte';
	import IcoItalic from '$components/icons/IcoItalic.svelte';
	import IcoAlignLeft from '$components/icons/IcoAlignLeft.svelte';
	import IcoAlignRight from '$components/icons/IcoAlignRight.svelte';
	import IcoAlignCenter from '$components/icons/IcoAlignCenter.svelte';
	import IcoAlignJustify from '$components/icons/IcoAlignJustify.svelte';
	import IcoRemoveFormat from '$components/icons/IcoRemoveFormat.svelte';
	import IcoSubscript from '$components/icons/IcoSubscript.svelte';
	import IcoSuperscript from '$components/icons/IcoSuperscript.svelte';
	import IcoStrikethrough from '$components/icons/IcoStrikethrough.svelte';
	import IcoListUl from '$components/icons/IcoListUl.svelte';
	import IcoListOl from '$components/icons/IcoListOl.svelte';
	import IcoUndo from '$components/icons/IcoUndo.svelte';
	import IcoRedo from '$components/icons/IcoRedo.svelte';
	import IcoCode from '$components/icons/IcoCode.svelte';
	import IcoImage from '$components/icons/IcoImage.svelte';
	import IcoLink from '$components/icons/IcoLink.svelte';
	import IcoUnlink from '$components/icons/IcoUnlink.svelte';
	import IcoDocument from '$components/icons/IcoDocument.svelte';
	import IcoLineBreak from '$components/icons/IcoLineBreak.svelte';
	import IcoFontSize from '$components/icons/IcoFontSize.svelte';
	import IcoThreeDots from '$components/icons/IcoThreeDots.svelte';

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
	let showCompactToolsDropdown = $state(false);

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
			showCompactToolsDropdown = false;
			editor?.run(command);
		};
	}

	function runDropdown(command: (state: any, dispatch?: any, view?: any) => boolean) {
		return () => {
			editor?.run(command);
			showDropdown = !showDropdown;
			showStyleDropdown = false;
			showCompactToolsDropdown = false;
		};
	}

	function runStyleDropdown(command: (state: any, dispatch?: any, view?: any) => boolean) {
		return () => {
			editor?.run(command);
			showStyleDropdown = false;
			showDropdown = false;
			showCompactToolsDropdown = false;
		};
	}

	function toggleSource() {
		if (!showSource) {
			sourceHtml = editor?.getHTML() ?? '';
		}

		showSource = !showSource;
		showDropdown = false;
		showStyleDropdown = false;
		showCompactToolsDropdown = false;
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

		const handle = cosray().modal.open((host) => {
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
		});
	}

	function addImage(uid: string, info: AssetInfo) {
		if (!editor) return;
		onAsset(uid, info);
		editor.run(insertImage(uid));
	}

	function openAddImageModal() {
		showCompactToolsDropdown = false;
		if (!editor) return;

		const handle = cosray().modal.open((host) => {
			const app = mount(ModalImage, {
				target: host,
				props: {
					add: addImage,
					close: () => handle.close(),
				},
			});

			return () => void unmount(app);
		});
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

	function openLinkModalClosed() {
		showCompactToolsDropdown = false;
		openAddLinkModal();
	}

	type ToolSpec = {
		key: string;
		tool: string;
		icon: Component;
		label: string;
		onclick: () => void;
		isActive?: () => boolean;
		isVisible?: () => boolean;
	};

	// The full vocabulary in canonical order; `tools` picks the subset, so a
	// configured list is a set, not a layout.
	const toolbarSpecs: ToolSpec[] = [
		{ key: 'undo', tool: 'undo', icon: IcoUndo, label: __('richtext:undo'), onclick: run(undo) },
		{ key: 'redo', tool: 'redo', icon: IcoRedo, label: __('richtext:redo'), onclick: run(redo) },
		{
			key: 'bold',
			tool: 'bold',
			icon: IcoBold,
			label: __('richtext:bold'),
			onclick: run(toggleBold()),
			isActive: () => editorState.bold,
		},
		{
			key: 'italic',
			tool: 'italic',
			icon: IcoItalic,
			label: __('richtext:italic'),
			onclick: run(toggleItalic()),
			isActive: () => editorState.italic,
		},
		{
			key: 'strike',
			tool: 'strike',
			icon: IcoStrikethrough,
			label: __('richtext:strikethrough'),
			onclick: run(toggleStrike()),
			isActive: () => editorState.strike,
		},
		{
			key: 'h1',
			tool: 'h1',
			icon: IcoH1,
			label: __('richtext:heading-1'),
			onclick: toggleHeading(1),
			isActive: () => editorState.heading1,
		},
		{
			key: 'h2',
			tool: 'h2',
			icon: IcoH2,
			label: __('richtext:heading-2'),
			onclick: toggleHeading(2),
			isActive: () => editorState.heading2,
		},
		{
			key: 'h3',
			tool: 'h3',
			icon: IcoH3,
			label: __('richtext:heading-3'),
			onclick: toggleHeading(3),
			isActive: () => editorState.heading3,
		},
		{
			key: 'sub',
			tool: 'sub',
			icon: IcoSubscript,
			label: __('richtext:subscript'),
			onclick: run(toggleSubscript()),
			isActive: () => editorState.subscript,
		},
		{
			key: 'sup',
			tool: 'sup',
			icon: IcoSuperscript,
			label: __('richtext:superscript'),
			onclick: run(toggleSuperscript()),
			isActive: () => editorState.superscript,
		},
		{
			key: 'align-left',
			tool: 'align',
			icon: IcoAlignLeft,
			label: __('richtext:align-left'),
			onclick: run(unsetTextAlign()),
		},
		{
			key: 'align-center',
			tool: 'align',
			icon: IcoAlignCenter,
			label: __('richtext:align-center'),
			onclick: run(setTextAlign('center')),
			isActive: () => editorState.center,
		},
		{
			key: 'align-right',
			tool: 'align',
			icon: IcoAlignRight,
			label: __('richtext:align-right'),
			onclick: run(setTextAlign('right')),
			isActive: () => editorState.right,
		},
		{
			key: 'align-justify',
			tool: 'align',
			icon: IcoAlignJustify,
			label: __('richtext:justify'),
			onclick: run(setTextAlign('justify')),
			isActive: () => editorState.justify,
		},
		{
			key: 'bullet-list',
			tool: 'bullet-list',
			icon: IcoListUl,
			label: __('richtext:bullet-list'),
			onclick: run(toggleBulletList()),
			isActive: () => editorState.bulletList,
		},
		{
			key: 'ordered-list',
			tool: 'ordered-list',
			icon: IcoListOl,
			label: __('richtext:numbered-list'),
			onclick: run(toggleOrderedList()),
			isActive: () => editorState.orderedList,
		},
		{
			key: 'blockquote',
			tool: 'blockquote',
			icon: IcoBlockQuoteRight,
			label: __('richtext:blockquote'),
			onclick: run(toggleBlockquote()),
			isActive: () => editorState.blockquote,
		},
		{
			key: 'hr',
			tool: 'hr',
			icon: IcoHorizontalRule,
			label: __('richtext:horizontal-line'),
			onclick: run(insertHorizontalRule()),
		},
		{
			key: 'link',
			tool: 'link',
			icon: IcoLink,
			label: __('richtext:add-page-link'),
			onclick: openLinkModalClosed,
		},
		{
			key: 'unlink',
			tool: 'link',
			icon: IcoUnlink,
			label: __('richtext:remove-link'),
			onclick: run(unsetLink()),
			isVisible: () => editorState.link,
		},
		{
			key: 'image',
			tool: 'image',
			icon: IcoImage,
			label: __('image:insert'),
			onclick: openAddImageModal,
		},
		{
			key: 'br',
			tool: 'br',
			icon: IcoLineBreak,
			label: __('richtext:hard-break'),
			onclick: run(insertHardBreak()),
		},
		{
			key: 'clear',
			tool: 'clear',
			icon: IcoRemoveFormat,
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
				class="richtext-toolbar-btn"
				onclick={run(toggleBold())}
				class:active={editorState.bold}
			>
				<IcoBold />
			</button>
			<button
				class="richtext-toolbar-btn"
				onclick={run(toggleItalic())}
				class:active={editorState.italic}
			>
				<IcoItalic />
			</button>
			<button
				class="richtext-toolbar-btn"
				onclick={run(toggleStrike())}
				class:active={editorState.strike}
			>
				<IcoStrikethrough />
			</button>
			<button class="richtext-toolbar-btn" onclick={run(clearMarks())}>
				<IcoRemoveFormat />
			</button>
		{/if}
	</div>
{/if}

<div class="richtext richtext-{toolbar}" class:required class:embed>
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
							<IcoDocument />
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
										showCompactToolsDropdown = false;
									}}
								>
									{__('richtext:paragraph')}
									<svg
										class="cms-richtext-dropdown-icon"
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20"
										fill="currentColor"
										aria-hidden="true"
									>
										<path
											fill-rule="evenodd"
											d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
											clip-rule="evenodd"
										/>
									</svg>
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
											<IcoParagraph />
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
												<IcoTextHeight />
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
											<IcoRemoveFormat />
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
									aria-expanded={showStyleDropdown}
									aria-haspopup="true"
									onclick={() => {
										showStyleDropdown = !showStyleDropdown;
										showDropdown = false;
										showCompactToolsDropdown = false;
									}}
								>
									<IcoFontSize />
									<svg
										class="cms-richtext-dropdown-icon"
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20"
										fill="currentColor"
										aria-hidden="true"
									>
										<path
											fill-rule="evenodd"
											d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
											clip-rule="evenodd"
										/>
									</svg>
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
											<IcoRemoveFormat />
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
						<div class="richtext-dropdown">
							<button
								type="button"
								id="compact-tools-menu-button"
								class="richtext-dropdown-button cms-richtext-compact-tools-button"
								title={__('richtext:formatting-tools')}
								aria-label={__('richtext:formatting-tools')}
								aria-expanded={showCompactToolsDropdown}
								aria-haspopup="true"
								onclick={() => {
									showCompactToolsDropdown = !showCompactToolsDropdown;
									showDropdown = false;
									showStyleDropdown = false;
								}}
							>
								<IcoThreeDots />
							</button>
						</div>
						{#if showCompactToolsDropdown}
							<div
								class="richtext-dropdown-menu cms-richtext-compact-tools-menu"
								role="menu"
								aria-orientation="vertical"
								aria-labelledby="compact-tools-menu-button"
								tabindex="-1"
							>
								<div class="cms-richtext-dropdown-items" role="none">
									{#each activeSpecs as spec (spec.key)}
										{#if spec.isVisible?.() ?? true}
											{@const Icon = spec.icon}
											<button
												type="button"
												onclick={spec.onclick}
												role="menuitem"
												tabindex="-1"
												class="richtext-dropdown-item"
												class:active={spec.isActive?.() ?? false}
											>
												<Icon />
												<span class="cms-richtext-dropdown-item-label">
													{spec.label}
												</span>
											</button>
										{/if}
									{/each}
								</div>
							</div>
						{/if}
					</div>
					<div
						class="richtext-toolbar-btns cms-richtext-toolbar-btns-grow cms-richtext-toolbar-main-actions"
					>
						{#each activeSpecs as spec (spec.key)}
							{#if spec.isVisible?.() ?? true}
								{@const Icon = spec.icon}
								<button
									type="button"
									class="richtext-toolbar-btn"
									title={spec.label}
									onclick={spec.onclick}
									class:active={spec.isActive?.() ?? false}
								>
									<Icon />
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
								<IcoCode />
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

	<div
		class="richtext-editor cms-richtext-content cms-richtext-layer-base"
		bind:this={ref}
		data-name={name}
		class:hide={showSource}
	></div>
	<div class="richtext-source cms-richtext-source cms-richtext-layer-base" class:hide={!showSource}>
		<textarea
			onkeyup={changeSource}
			{name}
			bind:value={sourceHtml}
			class="cms-richtext-source-input"
		>
		</textarea>
	</div>
</div>

<script lang="ts">
	import type { EditorState } from 'prosemirror-state';

	import { mount, onDestroy, onMount, unmount } from 'svelte';

	import type { AssetInfo } from '$types/data';

	import { cosray } from '$lib/bridge';
	import { _ } from '$lib/locale';
	import ModalImage from '$shell/modals/ModalImage.svelte';
	import ModalLink from '$shell/modals/ModalLink.svelte';
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

	import IcoH1 from '$shell/icons/IcoH1.svelte';
	import IcoH2 from '$shell/icons/IcoH2.svelte';
	import IcoH3 from '$shell/icons/IcoH3.svelte';
	import IcoBold from '$shell/icons/IcoBold.svelte';
	import IcoBlockQuoteRight from '$shell/icons/IcoBlockQuoteRight.svelte';
	import IcoParagraph from '$shell/icons/IcoParagraph.svelte';
	import IcoHorizontalRule from '$shell/icons/IcoHorizontalRule.svelte';
	import IcoTextHeight from '$shell/icons/IcoTextHeight.svelte';
	import IcoItalic from '$shell/icons/IcoItalic.svelte';
	import IcoAlignLeft from '$shell/icons/IcoAlignLeft.svelte';
	import IcoAlignRight from '$shell/icons/IcoAlignRight.svelte';
	import IcoAlignCenter from '$shell/icons/IcoAlignCenter.svelte';
	import IcoAlignJustify from '$shell/icons/IcoAlignJustify.svelte';
	import IcoRemoveFormat from '$shell/icons/IcoRemoveFormat.svelte';
	import IcoSubscript from '$shell/icons/IcoSubscript.svelte';
	import IcoSuperscript from '$shell/icons/IcoSuperscript.svelte';
	import IcoStrikethrough from '$shell/icons/IcoStrikethrough.svelte';
	import IcoListUl from '$shell/icons/IcoListUl.svelte';
	import IcoListOl from '$shell/icons/IcoListOl.svelte';
	import IcoUndo from '$shell/icons/IcoUndo.svelte';
	import IcoRedo from '$shell/icons/IcoRedo.svelte';
	import IcoCode from '$shell/icons/IcoCode.svelte';
	import IcoImage from '$shell/icons/IcoImage.svelte';
	import IcoLink from '$shell/icons/IcoLink.svelte';
	import IcoUnlink from '$shell/icons/IcoUnlink.svelte';
	import IcoDocument from '$shell/icons/IcoDocument.svelte';
	import IcoLineBreak from '$shell/icons/IcoLineBreak.svelte';
	import IcoFontSize from '$shell/icons/IcoFontSize.svelte';
	import IcoThreeDots from '$shell/icons/IcoThreeDots.svelte';

	type Props = {
		value: RichtextDoc | null;
		name: string;
		editSource?: boolean;
		required?: boolean;
		toolbar?: 'default' | 'inline';
		embed?: boolean;
		notify?: () => void;
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
		editSource = true,
		required = false,
		toolbar = 'default',
		embed = false,
		notify = () => {},
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

	function openAddLinkModalCompact() {
		showCompactToolsDropdown = false;
		openAddLinkModal();
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
								{_('Show content')}
							</span>
						</button>
					</div>
				{:else}
					<div class="cms-richtext-dropdown-wrap">
						<div class="richtext-dropdown">
							<button
								type="button"
								class="richtext-dropdown-button"
								aria-expanded="true"
								aria-haspopup="true"
								onclick={() => {
									showDropdown = !showDropdown;
									showStyleDropdown = false;
									showCompactToolsDropdown = false;
								}}
							>
								{_('Absatz')}
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
										onclick={runDropdown(setHeading(1))}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.heading1}
									>
										<IcoH1 />
										<span class="cms-richtext-dropdown-item-label">
											{_('Überschrift Level 1')}
										</span>
									</button>
									<button
										onclick={runDropdown(setHeading(2))}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.heading2}
									>
										<IcoH2 />
										<span class="cms-richtext-dropdown-item-label">
											{_('Überschrift Level 2')}
										</span>
									</button>
									<button
										onclick={runDropdown(setHeading(3))}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.heading3}
									>
										<IcoH3 />
										<span class="cms-richtext-dropdown-item-label">
											{_('Überschrift Level 3')}
										</span>
									</button>
									<button
										onclick={runDropdown(setParagraph())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.paragraphClass === 'default'}
									>
										<IcoParagraph />
										<span class="cms-richtext-dropdown-item-label">
											{_('Absatz')}
										</span>
									</button>
									{#each classOptions as [cls, label] (cls)}
										<button
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
										onclick={runDropdown(clearNodes())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoRemoveFormat />
										<span class="cms-richtext-dropdown-item-label">
											{_('Format entfernen')}
										</span>
									</button>
								</div>
							</div>
						{/if}
					</div>
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
												{_('Stil entfernen')}
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
								title={_('Formatting tools')}
								aria-label={_('Formatting tools')}
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
									<button
										onclick={run(unsetTextAlign())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoAlignLeft />
										<span class="cms-richtext-dropdown-item-label">
											{_('Text align left')}
										</span>
									</button>
									<button
										onclick={run(setTextAlign('center'))}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.center}
									>
										<IcoAlignCenter />
										<span class="cms-richtext-dropdown-item-label">
											{_('Text align center')}
										</span>
									</button>
									<button
										onclick={run(setTextAlign('right'))}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.right}
									>
										<IcoAlignRight />
										<span class="cms-richtext-dropdown-item-label">
											{_('Text align right')}
										</span>
									</button>
									<button
										onclick={run(setTextAlign('justify'))}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.justify}
									>
										<IcoAlignJustify />
										<span class="cms-richtext-dropdown-item-label">
											{_('Justify text')}
										</span>
									</button>
									<button
										onclick={run(toggleBold())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.bold}
									>
										<IcoBold />
										<span class="cms-richtext-dropdown-item-label">
											{_('Bold text')}
										</span>
									</button>
									<button
										onclick={run(toggleItalic())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.italic}
									>
										<IcoItalic />
										<span class="cms-richtext-dropdown-item-label">
											{_('Italic text')}
										</span>
									</button>
									<button
										onclick={run(toggleStrike())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.strike}
									>
										<IcoStrikethrough />
										<span class="cms-richtext-dropdown-item-label">
											{_('Strike through')}
										</span>
									</button>
									<button
										onclick={run(toggleBulletList())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.bulletList}
									>
										<IcoListUl />
										<span class="cms-richtext-dropdown-item-label">
											{_('Bulleted list')}
										</span>
									</button>
									<button
										onclick={run(toggleOrderedList())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.orderedList}
									>
										<IcoListOl />
										<span class="cms-richtext-dropdown-item-label">
											{_('Numbered list')}
										</span>
									</button>
									<button
										onclick={run(toggleSubscript())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.subscript}
									>
										<IcoSubscript />
										<span class="cms-richtext-dropdown-item-label">
											{_('Subscript')}
										</span>
									</button>
									<button
										onclick={run(toggleSuperscript())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.superscript}
									>
										<IcoSuperscript />
										<span class="cms-richtext-dropdown-item-label">
											{_('Superscript')}
										</span>
									</button>
									<button
										onclick={run(toggleBlockquote())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
										class:active={editorState.blockquote}
									>
										<IcoBlockQuoteRight />
										<span class="cms-richtext-dropdown-item-label">
											{_('Block quote')}
										</span>
									</button>
									<button
										onclick={run(insertHorizontalRule())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoHorizontalRule />
										<span class="cms-richtext-dropdown-item-label">
											{_('Horizontal line')}
										</span>
									</button>
									<button
										onclick={openAddLinkModalCompact}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoLink />
										<span class="cms-richtext-dropdown-item-label">
											{_('Add link to page')}
										</span>
									</button>
									<button
										onclick={openAddImageModal}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoImage />
										<span class="cms-richtext-dropdown-item-label">
											{_('Bild einfügen')}
										</span>
									</button>
									{#if editorState.link}
										<button
											onclick={run(unsetLink())}
											role="menuitem"
											tabindex="-1"
											class="richtext-dropdown-item"
										>
											<IcoUnlink />
											<span class="cms-richtext-dropdown-item-label">
												{_('Remove link')}
											</span>
										</button>
									{/if}
									<button
										onclick={run(insertHardBreak())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoLineBreak />
										<span class="cms-richtext-dropdown-item-label">
											{_('Add a hard line break')}
										</span>
									</button>
									<button
										onclick={run(clearMarks())}
										role="menuitem"
										tabindex="-1"
										class="richtext-dropdown-item"
									>
										<IcoRemoveFormat />
										<span class="cms-richtext-dropdown-item-label">
											{_('Remove formats')}
										</span>
									</button>
								</div>
							</div>
						{/if}
					</div>
					<div
						class="richtext-toolbar-btns cms-richtext-toolbar-btns-grow cms-richtext-toolbar-main-actions"
					>
						<button
							class="richtext-toolbar-btn"
							title={_('Text align left')}
							onclick={run(unsetTextAlign())}
						>
							<IcoAlignLeft />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Text align center')}
							onclick={run(setTextAlign('center'))}
							class:active={editorState.center}
						>
							<IcoAlignCenter />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Text align right')}
							onclick={run(setTextAlign('right'))}
							class:active={editorState.right}
						>
							<IcoAlignRight />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Justify text')}
							onclick={run(setTextAlign('justify'))}
							class:active={editorState.justify}
						>
							<IcoAlignJustify />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Bold text')}
							onclick={run(toggleBold())}
							class:active={editorState.bold}
						>
							<IcoBold />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Italic text')}
							onclick={run(toggleItalic())}
							class:active={editorState.italic}
						>
							<IcoItalic />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Strike through')}
							onclick={run(toggleStrike())}
							class:active={editorState.strike}
						>
							<IcoStrikethrough />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Bulleted list')}
							onclick={run(toggleBulletList())}
							class:active={editorState.bulletList}
						>
							<IcoListUl />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Numbered list')}
							onclick={run(toggleOrderedList())}
							class:active={editorState.orderedList}
						>
							<IcoListOl />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Subscript')}
							onclick={run(toggleSubscript())}
							class:active={editorState.subscript}
						>
							<IcoSubscript />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Superscript')}
							onclick={run(toggleSuperscript())}
							class:active={editorState.superscript}
						>
							<IcoSuperscript />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Block quote')}
							onclick={run(toggleBlockquote())}
							class:active={editorState.blockquote}
						>
							<IcoBlockQuoteRight />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Horizontal line')}
							onclick={run(insertHorizontalRule())}
						>
							<IcoHorizontalRule />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Add link to page')}
							onclick={openAddLinkModal}
						>
							<IcoLink />
						</button>
						{#if editorState.link}
							<button
								class="richtext-toolbar-btn"
								title={_('Remove link')}
								onclick={run(unsetLink())}
							>
								<IcoUnlink />
							</button>
						{/if}
						<button
							class="richtext-toolbar-btn"
							title={_('Bild einfügen')}
							onclick={openAddImageModal}
						>
							<IcoImage />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Add a hard line break')}
							onclick={run(insertHardBreak())}
						>
							<IcoLineBreak />
						</button>
						<button
							class="richtext-toolbar-btn"
							title={_('Remove formats')}
							onclick={run(clearMarks())}
						>
							<IcoRemoveFormat />
						</button>
					</div>
					<div class="richtext-extras">
						<button class="richtext-toolbar-btn" title={_('Undo last action')} onclick={run(undo)}>
							<IcoUndo />
						</button>
						<button class="richtext-toolbar-btn" title={_('Redo last undo')} onclick={run(redo)}>
							<IcoRedo />
						</button>
						{#if editSource}
							<button
								onclick={toggleSource}
								class="richtext-source-btn cms-richtext-source-btn-offset"
							>
								<IcoCode />
								<span class="cms-richtext-toolbar-source-label">
									{_('Show source')}
								</span>
							</button>
						{/if}
					</div>
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

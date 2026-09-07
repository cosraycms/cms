<script lang="ts">
	import type { FileItem, LocaleMap, Meta } from '$types/data';
	import { untrack } from 'svelte';
	import { ZXX } from '$types/data';
	import { localeTitle, resolveTextFallback } from '$lib/fallback';
	import { ModalHeader, ModalBody, ModalFooter } from '$components/modal';
	import { __ } from '$lib/locale';
	import Button from '$components/Button.svelte';
	import Input from '$components/Input.svelte';

	type Props = {
		close: () => void;
		// The applying caller prunes empty meta before persisting, so the
		// editing scaffold below never shadows catalog defaults.
		apply: (asset: FileItem) => void;
		asset: FileItem;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		catalog?: Meta;
		hasAlt: boolean;
	};

	let {
		close,
		apply,
		asset = $bindable(),
		translate,
		contentLocale,
		identity,
		locales,
		catalog,
		hasAlt,
	}: Props = $props();
	let draft = $state(untrack(() => $state.snapshot(asset)));
	draft.meta ??= {};
	draft.meta.title ??= { zxx: '' };
	draft.meta.alt ??= { zxx: '' };
	let meta = $derived(draft.meta);
	let key = $derived(translate ? contentLocale : ZXX);
	let titleFallback = $derived(
		resolveTextFallback(
			meta.title as LocaleMap<string>,
			catalog?.title as LocaleMap<string> | undefined,
			key,
			locales?.all ?? [],
		),
	);
	let altFallback = $derived(
		resolveTextFallback(
			meta.alt as LocaleMap<string>,
			catalog?.alt as LocaleMap<string> | undefined,
			key,
			locales?.all ?? [],
		),
	);

	function sourceLabel(source: string): string {
		const language =
			source === ZXX ? __('field:shared-content') : localeTitle(locales?.all ?? [], source);

		return __('field:fallback-from', { language });
	}
</script>

<ModalHeader>{__('image:title-and-alt')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-edit-image-fields">
		<Input
			bind:value={meta.title}
			label={__('common:title')}
			id={`${identity}_edit_image_title`}
			{translate}
			locale={key}
			fallback={titleFallback?.value ?? ''}
			fallbackLabel={titleFallback ? sourceLabel(titleFallback.locale) : ''}
		/>
		{#if hasAlt}
			<Input
				bind:value={meta.alt}
				label={__('image:alt-text')}
				id={`${identity}_edit_image_alt`}
				{translate}
				locale={key}
				fallback={altFallback?.value ?? ''}
				fallbackLabel={altFallback ? sourceLabel(altFallback.locale) : ''}
				description={__('image:alt-text-help')}
			/>
		{/if}
	</div>
</ModalBody>
<ModalFooter>
	<Button variant="danger" onclick={close}>
		{__('common:cancel')}
	</Button>
	<Button variant="primary" onclick={() => apply(draft)}>
		{__('common:apply')}
	</Button>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-edit-image-fields {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-4);
			margin-bottom: var(--cms-space-8);
		}
	}
</style>

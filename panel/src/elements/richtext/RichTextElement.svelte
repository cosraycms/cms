<svelte:options customElement={{ tag: 'cosray-richtext', shadow: 'none' }} />

<script lang="ts">
	import type { AssetInfo, AssetMap, LocaleMap } from '$types/data';

	import { ZXX } from '$lib/content';
	import {
		FORMAT,
		VERSION,
		htmlToDoc,
		isFilledDoc,
		type RichtextDoc,
		type RichtextValue,
	} from './format.js';
	import RichTextEditor from '$components/richtext/RichTextEditor.svelte';
	import { localeTitle, resolveFallback } from '$lib/fallback';
	import { __ } from '$lib/locale';

	type FieldInfo = {
		name: string;
		required?: boolean;
		immutable?: boolean;
		translate?: boolean;
		presentation?: string;
		placeholder?: string;
		tools?: string[];
		richtextClasses?: Record<string, string>;
		richtextStyles?: Record<string, string>;
	};

	type Props = {
		value?: LocaleMap<RichtextDoc | string | null>;
		format?: string;
		field?: FieldInfo;
		locale?: string;
		locales?: {
			default: string;
			all: { id: string; title: string; fallback?: string | null }[];
		};
		assets?: AssetMap;
	};

	let {
		value = {},
		format = '',
		field = { name: 'richtext' },
		locale = ZXX,
		locales,
		assets = {},
	}: Props = $props();

	let active = $derived(field.translate ? locale : ZXX);
	let configuredLocales = $derived(locales?.all ?? []);

	// Library picks and uploads register here so previews resolve
	// before the payload knows the asset.
	let picked: AssetMap = $state({});

	function localeIds(): string[] {
		return field.translate ? (locales?.all ?? []).map((entry) => entry.id) : [ZXX];
	}

	/**
	 * All locales convert atomically: legacy HTML parses through the
	 * editor schema, structured documents pass through. The result is
	 * always a complete envelope value map.
	 */
	function convert(): RichtextValue {
		const map: RichtextValue = {};
		// A control the host renders without stored data — a freshly
		// stamped row — assigns a null value, not an absent one.
		const source = value ?? {};

		for (const id of new Set([...Object.keys(source), ...localeIds()])) {
			const raw = source[id] ?? null;

			if (typeof raw === 'string') {
				map[id] = htmlToDoc(raw);
			} else {
				map[id] = raw;
			}
		}

		return map;
	}

	// Synchronous init: the editor reads its content at mount, before
	// effects run; the effect handles later host re-assignments.
	let map: RichtextValue = $state(convert());

	$effect(() => {
		map = convert();
	});

	function notify() {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: map, format: FORMAT, version: VERSION },
				bubbles: true,
				composed: true,
			}),
		);
	}

	// Legacy content converts once at mount so an untouched field still
	// submits the structured envelope (saves are writer-strict).
	$effect(() => {
		if (format !== FORMAT) {
			notify();
		}
	});

	function assetUrl(uid: string): string | null {
		const info = picked[uid] ?? assets[uid];

		return info?.thumbUrl ?? info?.url ?? null;
	}

	function onAsset(uid: string, info: AssetInfo) {
		picked = { ...picked, [uid]: info };
	}

	let fallback = $derived(
		field.translate && !isFilledDoc(map[active])
			? resolveFallback(map, active, configuredLocales, (doc) => isFilledDoc(doc))
			: null,
	);
</script>

{#key active}
	<RichTextEditor
		name={field.name}
		required={field.required ?? false}
		readonly={field.immutable ?? false}
		fallback={fallback?.value ?? null}
		fallbackLabel={fallback
			? __('field:fallback-from', {
					language:
						fallback.locale === ZXX
							? __('field:shared-content')
							: localeTitle(configuredLocales, fallback.locale),
				})
			: ''}
		toolbar={field.presentation === 'block' ? 'inline' : 'default'}
		tools={field.tools}
		placeholder={field.placeholder ?? ''}
		classes={field.richtextClasses ?? {}}
		styles={field.richtextStyles ?? {}}
		{assetUrl}
		{onAsset}
		bind:value={map[active]}
		{notify}
	/>
{/key}

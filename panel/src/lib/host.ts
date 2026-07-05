import type { AssetMap } from '$types/data';

import { loadElement } from '$lib/elements';

type HostPayload = {
	value?: unknown;
	meta?: unknown;
	// Format envelope of structured richtext values; mirrored into the
	// form value so writer-strict saves see it even before any edit.
	format?: string | null;
	version?: number | null;
	field?: Record<string, unknown>;
	locales?: { default: string; all: { id: string; title: string }[] };
	assets?: AssetMap;
};

type ChangeDetail = {
	value?: unknown;
	meta?: unknown;
	format?: string;
	version?: number;
};

type ContractElement = HTMLElement & Record<string, unknown>;

/**
 * Form-associated host for custom-element controls. It reads its
 * payload from an embedded JSON script, loads the element module,
 * assigns the element contract (value, meta, field, node, locale,
 * locales) and mirrors every reported edit into the form value as one
 * JSON string ({ value, meta? }) — the [json] leaf the save patch
 * consumes. Elements keep the exact contract they had under the island.
 */
export class CosrayHost extends HTMLElement {
	static formAssociated = true;

	#internals = this.attachInternals();
	#payload: HostPayload = {};
	#element: ContractElement | null = null;
	#locale = '';
	#started = false;

	connectedCallback(): void {
		if (this.#started) {
			return;
		}

		this.#started = true;
		this.#locale = this.getAttribute('locale') ?? '';
		this.#payload = this.#readPayload();
		this.#setFormValue();
		this.addEventListener('cosray-change', (event) => {
			this.#apply((event as CustomEvent<ChangeDetail>).detail);
		});
		void this.#mount();
	}

	// The locale tabs behavior assigns the editing locale; forward it.
	set locale(locale: string) {
		this.#locale = locale;

		if (this.#element) {
			this.#element.locale = locale;
		}
	}

	get locale(): string {
		return this.#locale;
	}

	#readPayload(): HostPayload {
		const script = this.querySelector(':scope > script[type="application/json"]');

		try {
			return JSON.parse(script?.textContent ?? '') as HostPayload;
		} catch (error) {
			console.error('Could not parse the control payload.', this, error);

			return {};
		}
	}

	async #mount(): Promise<void> {
		const tag = this.getAttribute('tag') ?? '';
		const module = this.getAttribute('module') ?? '';

		if (tag === '' || module === '') {
			return;
		}

		try {
			await loadElement(module);
		} catch (error) {
			console.error(`Could not load the editor control module "${module}".`, error);

			return;
		}

		const element = document.createElement(tag) as ContractElement;
		element.value = this.#payload.value;

		if (this.#payload.meta != null) {
			element.meta = this.#payload.meta;
		}

		if (this.#payload.format != null) {
			element.format = this.#payload.format;
		}

		element.field = this.#payload.field;
		element.node = this.getAttribute('node') ?? '';
		element.locale = this.#locale;
		element.locales = this.#payload.locales;
		element.assets = this.#payload.assets ?? {};

		this.#element = element;
		this.append(element);
	}

	#apply(detail: ChangeDetail | null): void {
		if (!detail) {
			return;
		}

		this.#payload.value = detail.value;

		if (detail.meta !== undefined) {
			this.#payload.meta = detail.meta;
		}

		if (detail.format !== undefined) {
			this.#payload.format = detail.format;
		}

		if (detail.version !== undefined) {
			this.#payload.version = detail.version;
		}

		this.#setFormValue();
	}

	#setFormValue(): void {
		const value: { value: unknown; meta?: unknown; format?: string; version?: number } = {
			value: this.#payload.value,
		};

		if (this.#payload.meta != null) {
			value.meta = this.#payload.meta;
		}

		if (this.#payload.format != null) {
			value.format = this.#payload.format;
		}

		if (this.#payload.version != null) {
			value.version = this.#payload.version;
		}

		this.#internals.setFormValue(JSON.stringify(value));
	}
}

if (!customElements.get('cosray-host')) {
	customElements.define('cosray-host', CosrayHost);
}

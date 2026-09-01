import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
	loadElement: vi.fn<() => Promise<unknown>>(),
}));

vi.mock('$lib/elements', () => ({ loadElement: mocks.loadElement }));

import { CosrayHost, installHost } from '../../src/lib/host';

type Control = HTMLElement & Record<string, unknown>;

let values = new WeakMap<HTMLElement, string>();
const attachInternals = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'attachInternals');

beforeAll(() => {
	Object.defineProperty(HTMLElement.prototype, 'attachInternals', {
		configurable: true,
		value(this: HTMLElement) {
			const host = this;

			return {
				setFormValue(value: string) {
					values.set(host, value);
				},
			};
		},
	});
	installHost();
});

beforeEach(() => {
	values = new WeakMap();
	mocks.loadElement.mockResolvedValue(undefined);
});

afterEach(() => {
	document.body.replaceChildren();
	vi.restoreAllMocks();
	mocks.loadElement.mockReset();
});

afterAll(() => {
	if (attachInternals) {
		Object.defineProperty(HTMLElement.prototype, 'attachInternals', attachInternals);
	} else {
		delete (HTMLElement.prototype as Partial<HTMLElement>).attachInternals;
	}
});

function host(payload: unknown, attributes: Record<string, string> = {}): CosrayHost {
	const element = document.createElement('cosray-host') as CosrayHost;
	element.setAttribute('tag', 'test-control');
	element.setAttribute('module', 'acme/control.js');
	element.setAttribute('node', 'node-a');
	element.setAttribute('locale', 'en');

	for (const [name, value] of Object.entries(attributes)) {
		element.setAttribute(name, value);
	}

	const script = document.createElement('script');
	script.type = 'application/json';
	script.textContent = JSON.stringify(payload);
	element.append(script);
	document.body.append(element);

	return element;
}

function formValue(element: HTMLElement): unknown {
	return JSON.parse(values.get(element) ?? 'null') as unknown;
}

describe('cosray host', () => {
	it('loads and configures a control from the server payload', async () => {
		const payload = {
			value: { en: 'Hello' },
			meta: { note: { en: 'Editorial' } },
			format: 'cosray-richtext',
			version: 1,
			field: { name: 'body', required: true },
			locales: { default: 'en', all: [{ id: 'en', title: 'English' }] },
			assets: { image: { filename: 'image.jpg', url: '/media/image.jpg', kind: 'image' } },
		};
		const element = host(payload);

		await vi.waitFor(() => expect(element.querySelector('test-control')).not.toBeNull());

		const control = element.querySelector<Control>('test-control')!;
		expect(mocks.loadElement).toHaveBeenCalledOnce();
		expect(mocks.loadElement).toHaveBeenCalledWith('acme/control.js');
		expect(control.value).toEqual(payload.value);
		expect(control.meta).toEqual(payload.meta);
		expect(control.format).toBe('cosray-richtext');
		expect(control.field).toEqual(payload.field);
		expect(control.node).toBe('node-a');
		expect(control.locale).toBe('en');
		expect(control.locales).toEqual(payload.locales);
		expect(control.assets).toEqual(payload.assets);
		expect(formValue(element)).toEqual({
			value: payload.value,
			meta: payload.meta,
			format: 'cosray-richtext',
			version: 1,
		});
	});

	it('mirrors change details into one form value', async () => {
		const element = host({ value: { en: 'Before' }, meta: { old: true } });
		await vi.waitFor(() => expect(element.querySelector('test-control')).not.toBeNull());

		element.dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: {
					value: { en: 'After' },
					meta: { changed: true },
					format: 'cosray-richtext',
					version: 1,
				},
			}),
		);

		expect(formValue(element)).toEqual({
			value: { en: 'After' },
			meta: { changed: true },
			format: 'cosray-richtext',
			version: 1,
		});
	});

	it('forwards locale changes to a mounted control', async () => {
		const element = host({ value: null });
		await vi.waitFor(() => expect(element.querySelector('test-control')).not.toBeNull());

		element.locale = 'de';

		expect(element.locale).toBe('de');
		expect(element.querySelector<Control>('test-control')!.locale).toBe('de');
	});

	it('reports malformed payloads and still mounts an empty control', async () => {
		const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
		const malformed = document.createElement('cosray-host') as CosrayHost;
		malformed.setAttribute('tag', 'test-control');
		malformed.setAttribute('module', 'acme/control.js');
		malformed.innerHTML = '<script type="application/json">{</script>';
		document.body.append(malformed);

		await vi.waitFor(() => expect(malformed.querySelector('test-control')).not.toBeNull());

		expect(error).toHaveBeenCalledWith(
			'Could not parse the control payload.',
			malformed,
			expect.any(SyntaxError),
		);
		expect(formValue(malformed)).toEqual({});
	});

	it('leaves the host empty when its module cannot load', async () => {
		const failure = new Error('offline');
		mocks.loadElement.mockRejectedValue(failure);
		const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
		const element = host({ value: 'kept' });

		await vi.waitFor(() =>
			expect(error).toHaveBeenCalledWith(
				'Could not load the editor control module "acme/control.js".',
				failure,
			),
		);

		expect(element.querySelector('test-control')).toBeNull();
		expect(formValue(element)).toEqual({ value: 'kept' });
	});
});

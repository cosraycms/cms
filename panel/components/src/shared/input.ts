export type ValueInput = HTMLInputElement | HTMLTextAreaElement;

export function findValueInput(host: HTMLElement): ValueInput | null {
	const id = host.getAttribute('data-value-input') ?? host.getAttribute('value-input');

	if (id === null || id.trim() === '') {
		return null;
	}

	const input = document.getElementById(id);

	if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
		return input;
	}

	return null;
}

export function syncValueInput(host: HTMLElement, input: ValueInput | null, value: string): void {
	if (input !== null && input.value !== value) {
		input.value = value;
		input.dispatchEvent(new Event('input', { bubbles: true }));
	}

	host.dispatchEvent(
		new CustomEvent('cosray:change', {
			bubbles: true,
			composed: true,
			detail: { value },
		}),
	);
}

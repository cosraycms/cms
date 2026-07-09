type Catalog = {
	plural?: string;
	messages?: Record<string, string | string[]>;
};

function readCatalog(): Record<string, string | string[]> {
	const el = typeof document === 'undefined' ? null : document.getElementById('cosray-messages');

	if (!el?.textContent) {
		return {};
	}

	try {
		return (JSON.parse(el.textContent) as Catalog).messages ?? {};
	} catch {
		return {};
	}
}

const messages = readCatalog();

export function __(id: string): string {
	const entry = messages[id];

	return typeof entry === 'string' ? entry : id;
}

import { afterEach, beforeEach, vi } from 'vitest';

function message(args: unknown[]): string {
	return args
		.map((value) => {
			if (value instanceof Error) {
				return value.stack ?? value.message;
			}

			return typeof value === 'string' ? value : String(value);
		})
		.join(' ');
}

beforeEach(() => {
	vi.spyOn(console, 'error').mockImplementation((...args: unknown[]) => {
		throw new Error(`Unexpected console.error: ${message(args)}`);
	});
});

afterEach(() => {
	vi.restoreAllMocks();
});

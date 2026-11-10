import { expect, test } from '@playwright/test';
import { signIn } from './support';

test('a new entry starts in a dialog with its required fields and opens as a draft', async ({
	page,
}) => {
	await signIn(page);
	await page.goto('/panel/collection/articles');
	await page.getByRole('link', { name: 'New entry' }).click();

	const dialog = page.getByRole('dialog');
	const title = dialog.getByLabel('Title');
	const create = dialog.getByRole('button', { name: 'Create' });
	await expect(title).toBeFocused();
	await expect(dialog.getByLabel('Teaser')).toHaveCount(0);
	await expect(create).toBeDisabled();

	await title.fill('Made in a dialog');
	await dialog.getByLabel('Date').fill('2026-11-12');
	await expect(create).toBeEnabled();
	await title.press('Enter');

	await expect(page).toHaveURL(/\/panel\/node\//);
	await expect(page.getByRole('dialog')).toHaveCount(0);
	await expect(page.locator('h1')).toHaveText('Made in a dialog');
	const states = await page
		.locator('#editor-status-dots > .dot')
		.evaluateAll((dots) => dots.map((dot) => (dot as HTMLElement).dataset.state));
	expect(states).toEqual(['none', 'present', 'none']);
	await expect(page.getByRole('button', { name: 'Publish', exact: true })).toBeEnabled();
});

test('cancelling the dialog creates nothing', async ({ page }) => {
	await signIn(page);
	await page.goto('/panel/collection/articles');
	const rows = await page.locator('tr[data-uid]').count();
	await page.getByRole('link', { name: 'New entry' }).click();
	await page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click();

	await expect(page.getByRole('dialog')).toHaveCount(0);
	await page.reload();
	await expect(page.locator('tr[data-uid]')).toHaveCount(rows);
});

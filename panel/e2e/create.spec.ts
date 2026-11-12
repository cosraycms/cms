import { expect, test } from '@playwright/test';
import { head, signIn } from './support';

test('a new entry starts in a dialog with its required fields and opens unpublished', async ({
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
		.locator('#editor-status-dots > [data-stage]')
		.evaluateAll((dots) => dots.map((dot) => (dot as HTMLElement).dataset.state));
	expect(states).toEqual(['unpublished', 'none', 'none']);
	await expect(head(page).getByRole('button', { name: 'Publish', exact: true })).toBeEnabled();
	await expect(head(page).getByRole('button', { name: 'Apply', exact: true })).toBeHidden();

	// Edits offer to apply them, kept unpublished or published with them.
	await page.getByLabel('Title').fill('Made in a dialog, written on');
	const apply = head(page).getByRole('button', { name: 'Apply', exact: true });
	await expect(apply).toBeEnabled();
	await expect(head(page).getByRole('button', { name: 'Publish', exact: true })).toBeHidden();
	await apply.click();
	const question = page.getByRole('dialog', { name: 'Apply to the main version?' });
	await expect(question).toContainText('The page is offline.');
	await expect(question.getByRole('button', { name: 'Apply', exact: true })).toBeFocused();
	await page.keyboard.press('Escape');
	await expect(question).toBeHidden();
	await expect(apply).toBeFocused();

	await head(page).getByRole('button', { name: 'More ways to apply' }).click();
	const menu = page.getByRole('menu');
	await expect(menu.getByRole('menuitem')).toHaveCount(1);
	await menu.getByRole('menuitem', { name: /^Apply draft and publish/ }).click();

	await expect(page.locator('#cms-toasts')).toContainText('Applied and published');
	await expect(page.locator('#editor-status-dots > [data-stage="main"]')).toHaveAttribute(
		'data-state',
		'published',
	);
	await expect(page.locator('h1')).toHaveText('Made in a dialog');
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

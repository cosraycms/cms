import { expect, type Page, test } from '@playwright/test';
import { discardMine, mine, openNode, save } from './support';

async function leaveEdits(page: Page, name: string, title: string): Promise<void> {
	await openNode(page, name);
	await edit(page, title);
	await page.reload();
}

async function edit(page: Page, title: string): Promise<void> {
	const autosaved = page.waitForResponse(
		(response) => response.url().endsWith('/autosave') && response.ok(),
	);
	await page.getByLabel('Title').fill(title);
	await autosaved;
	await expect(mine(page)).toHaveAttribute('data-state', 'present');
}

test('edits come back with the editor, marked, until they are saved', async ({ page }) => {
	let prompted = false;
	page.on('dialog', async (prompt) => {
		prompted = true;
		await prompt.accept();
	});
	await leaveEdits(page, 'restore', 'Kept for later');

	expect(prompted).toBe(false);
	await expect(page.getByRole('dialog')).toHaveCount(0);
	await expect(page.getByLabel('Title')).toHaveValue('Kept for later');
	await expect(mine(page)).toHaveAttribute('data-state', 'present');
	await expect(page.locator('.cms-field[data-field="title"] [data-restored-mark]')).toBeVisible();

	await save(page);
	await expect(page.locator('#cms-toasts')).toContainText(/saved/i);
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
	await expect(page.locator('[data-restored-mark]')).toHaveCount(0);
	await page.reload();
	await expect(page.getByLabel('Title')).toHaveValue('Kept for later');
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
});

test('discarding my changes returns to the stored state', async ({ page }) => {
	await leaveEdits(page, 'later', 'Not wanted');

	await discardMine(page);

	await expect(page.getByLabel('Title')).not.toHaveValue('Not wanted');
	await expect(page.locator('#cms-toasts')).toContainText('My changes discarded');
	await expect(page).not.toHaveURL(/_token/);
	await page.reload();
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
});

test('leaving inside the panel does not ask once the edits are kept', async ({ page }) => {
	await openNode(page, 'later');
	await edit(page, 'Left behind');

	await page.locator('.breadcrumb a').click();

	await expect(page.getByRole('dialog')).toHaveCount(0);
	await expect(page).not.toHaveURL(/\/panel\/node\//);
});

test('edits on a state saved meanwhile are a conflict until saved anyway', async ({
	page,
	context,
}) => {
	await openNode(page, 'restore');
	await edit(page, 'Mine, from an older state');

	const other = await context.newPage();
	await other.goto(page.url());
	await other.getByLabel('Title').fill('Saved meanwhile');
	await save(other);
	await other.close();

	await edit(page, 'Mine, still older');
	await page.reload();

	const banner = page.locator('#editor-conflict');
	await expect(banner).toBeVisible();
	await expect(mine(page)).toHaveAttribute('data-state', 'conflict');
	await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeDisabled();

	await page.getByRole('button', { name: 'Save anyway' }).click();
	await expect(page.locator('#cms-toasts')).toContainText(/saved/i);
	await expect(banner).toBeHidden();
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
	await expect(page.getByRole('button', { name: 'Save draft', exact: true })).toBeDisabled();
});

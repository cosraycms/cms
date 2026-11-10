import { expect, type Page, test } from '@playwright/test';
import { openNode } from './support';

async function leaveEdits(page: Page, name: string, title: string): Promise<void> {
	await openNode(page, name);
	const autosaved = page.waitForResponse(
		(response) => response.url().endsWith('/autosave') && response.ok(),
	);
	await page.getByLabel('Title').fill(title);
	await autosaved;
	await page.reload();
}

function mine(page: Page) {
	return page.locator('#editor-status-dots > [data-stage="mine"]');
}

function question(page: Page) {
	return page.getByRole('dialog', { name: 'Unsaved changes' });
}

test('edits survive a reload and come back on request', async ({ page }) => {
	let prompted = false;
	page.on('dialog', async (prompt) => {
		prompted = true;
		await prompt.accept();
	});
	await leaveEdits(page, 'restore', 'Kept for later');
	const editor = page.url();
	expect(prompted).toBe(false);

	await expect(question(page).getByText(/were not saved\. Show loads them/)).toBeVisible();
	await question(page).getByRole('button', { name: 'Show' }).click();

	await expect(page.getByLabel('Title')).toHaveValue('Kept for later');
	await expect(mine(page)).toHaveAttribute('data-state', 'present');
	await expect(page.locator('#editor-restored')).toBeVisible();
	await expect(page.locator('.cms-field[data-field="title"] [data-restored-mark]')).toBeVisible();
	expect(page.url()).toBe(editor);

	await page.getByRole('button', { name: 'Save', exact: true }).click();
	await expect(page.locator('#cms-toasts')).toContainText(/saved/i);
	await expect(page.locator('#editor-restored')).toHaveCount(0);
	await expect(page.locator('[data-restored-mark]')).toHaveCount(0);
	await page.reload();
	await expect(page.getByLabel('Title')).toHaveValue('Kept for later');
	await expect(question(page)).toHaveCount(0);
});

test('discarding the offer keeps the stored state and asks no more', async ({ page }) => {
	await leaveEdits(page, 'later', 'Not wanted');
	const discarded = page.waitForResponse((response) =>
		response.url().endsWith('/autosave/discard'),
	);
	await question(page).getByRole('button', { name: 'Discard' }).click();
	await discarded;

	await expect(page.getByLabel('Title')).not.toHaveValue('Not wanted');
	await page.reload();
	await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeVisible();
	await expect(question(page)).toHaveCount(0);
});

test('closing the offer asks again next time', async ({ page }) => {
	await leaveEdits(page, 'later', 'Maybe later');
	await page.keyboard.press('Escape');

	await expect(question(page)).toHaveCount(0);
	await expect(page.getByLabel('Title')).not.toHaveValue('Maybe later');
	await page.reload();
	await expect(question(page)).toBeVisible();
});

test('discarding restored edits returns to the stored state', async ({ page }) => {
	await leaveEdits(page, 'restore', 'Shown, then dropped');
	await question(page).getByRole('button', { name: 'Show' }).click();
	await expect(page.getByLabel('Title')).toHaveValue('Shown, then dropped');

	await page.locator('#editor-restored').getByRole('button', { name: 'Discard' }).click();
	await page.getByRole('dialog').getByRole('button', { name: 'Discard' }).click();

	await expect(page.locator('#editor-restored')).toHaveCount(0);
	await expect(page.getByLabel('Title')).not.toHaveValue('Shown, then dropped');
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
	await page.reload();
	await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeVisible();
	await expect(question(page)).toHaveCount(0);
});

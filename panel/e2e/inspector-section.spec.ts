import { expect, test } from '@playwright/test';
import { mine, openNode } from './support';

test('a plugin section acts at once through its own form and follows the node', async ({
	page,
}) => {
	await openNode(page, 'section');
	const section = page.locator('#cms-inspector-section-browser-notes');

	await expect(section.getByRole('heading', { name: 'Notes' })).toBeVisible();
	await expect(section.locator('[data-state]')).toHaveText('online');

	// Typing in the section is no edit of the node.
	await section.getByLabel('Note').fill('Remember the photos');
	await expect(page.locator('#editor-dirty')).toBeHidden();
	await section.getByRole('button', { name: 'Note' }).click();

	await expect(page.locator('#cms-toasts')).toContainText('Noted');
	await expect(section.locator('[data-note]')).toHaveText('Remember the photos');
	await expect(section.getByLabel('Note')).toHaveValue('');
	await expect(mine(page)).toHaveAttribute('data-state', 'none');

	// A switch of the node draws the section again.
	await page.locator('#editor-published-switch').uncheck();
	await expect(section.locator('[data-state]')).toHaveText('offline');
	await page.locator('#editor-published-switch').check();
	await expect(section.locator('[data-state]')).toHaveText('online');
});

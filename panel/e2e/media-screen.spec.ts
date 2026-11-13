import { expect, test } from '@playwright/test';
import { file, signIn } from './support';

test('searches texts, narrows to unused files and deletes a selection', async ({ page }) => {
	await signIn(page);
	await page.goto('/panel/media');
	await page
		.locator('[data-media-upload-input]')
		.setInputFiles([file('screen-1.jpg'), file('screen-2.jpg')]);
	await expect(page.locator('[data-media-tile]').filter({ hasText: 'screen-1.jpg' })).toBeVisible();
	await expect(page.locator('[data-media-tile]').filter({ hasText: 'screen-2.jpg' })).toBeVisible();

	await page.locator('[data-media-tile]').filter({ hasText: 'screen-1.jpg' }).click();
	await expect(page.locator('#media-detail .cms-media-detail-head h2')).toHaveText('screen-1.jpg');
	const form = page.locator('#media-detail form').first();
	await form.locator('input[name="meta[attribution]"]').fill('Findable Photographer');
	await form.getByRole('button', { name: 'Save' }).click();
	await expect(form.getByText('Saved')).toBeVisible();

	const search = page.getByRole('searchbox');
	await search.fill('Findable');
	await search.press('Enter');
	await expect(page.locator('[data-media-tile]')).toHaveCount(1);
	await expect(page.locator('[data-media-tile]')).toContainText('screen-1.jpg');

	await page.goto('/panel/media?status=unused');
	const unused = page.locator('.cms-asset-grid-cell').filter({ hasText: /screen-[12]\.jpg/ });
	await expect(unused).toHaveCount(2);

	// The tile keeps opening the file, while its checkbox selects it.
	await unused.first().hover();
	await unused.first().getByRole('checkbox').check();
	await unused.nth(1).getByRole('checkbox').check();
	await expect(page.locator('[data-media-selection-count]')).toHaveText('2 selected');

	await page.locator('[data-media-delete-selected]').click();
	const dialog = page.getByRole('dialog', { name: 'Delete selected' });
	await expect(dialog.getByText('Delete the 2 selected files?')).toBeVisible();
	await dialog.getByRole('button', { name: 'Delete', exact: true }).click();

	await expect(
		page.locator('.cms-asset-grid-cell').filter({ hasText: /screen-[12]\.jpg/ }),
	).toHaveCount(0);
	await expect(page.getByText('2 files deleted.')).toBeVisible();
});

test('replacing a file keeps its uid and shows the new picture', async ({ page }) => {
	await signIn(page);
	await page.goto('/panel/media');
	await page.locator('[data-media-upload-input]').setInputFiles(file('screen-3.jpg'));
	const detail = page.locator('#media-detail');
	await expect(detail.locator('.cms-media-detail-head h2')).toHaveText('screen-3.jpg');
	const uid = await detail.getAttribute('data-media-detail');
	expect(uid).toBeTruthy();

	await page.locator('[data-media-replace-input]').setInputFiles(file('portrait.jpg'));

	await expect(detail.locator('.cms-media-detail-head h2')).toHaveText('portrait.jpg');
	await expect(detail).toHaveAttribute('data-media-detail', String(uid));
	await expect(detail.getByText('600 × 1200 px')).toBeVisible();
});

test('deleting a file asks in a panel dialog first', async ({ page }) => {
	await signIn(page);
	await page.goto('/panel/media');
	await page.locator('[data-media-upload-input]').setInputFiles(file('screen-4.jpg'));
	const detail = page.locator('#media-detail');
	await expect(detail.locator('.cms-media-detail-head h2')).toHaveText('screen-4.jpg');
	const tile = page.locator('[data-media-tile]').filter({ hasText: 'screen-4.jpg' });

	await detail.getByRole('button', { name: 'Delete' }).click();
	const question = page.getByRole('dialog', { name: 'Delete file' });
	await expect(question.getByText('Delete the file "screen-4.jpg"?')).toBeVisible();
	await expect(question.getByRole('button', { name: 'Cancel' })).toBeFocused();
	await page.keyboard.press('Escape');
	await expect(question).toHaveCount(0);
	await expect(tile).toBeVisible();

	await detail.getByRole('button', { name: 'Delete' }).click();
	await question.getByRole('button', { name: 'Delete file' }).click();
	await expect(tile).toHaveCount(0);
});

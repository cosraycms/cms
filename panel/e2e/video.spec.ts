import { expect, test } from '@playwright/test';
import { CLIP, field, file, publish, signIn, upload } from './support';

test('a poster chosen for a video reaches the page', async ({ page }) => {
	await signIn(page);
	await page.goto('/panel/media');
	await page.locator('[data-media-upload-input]').setInputFiles(file('still.jpg'));
	await expect(page.locator('#media-detail .cms-media-detail-head h2')).toHaveText('still.jpg');

	await page.goto('/panel/node/browservideo');
	const clip = field(page, CLIP);
	await upload(clip, 'clip.mp4');
	await expect(clip.locator('video')).toBeVisible();

	await clip.getByRole('button', { name: 'Edit' }).click();
	let dialog = page.locator('dialog[open]').last();
	await expect(dialog.getByRole('heading', { name: 'Video details' })).toBeVisible();
	await dialog.getByRole('button', { name: 'Choose a picture' }).click();
	const library = page.locator('dialog[open]').last();
	await library.locator('[data-pick]').filter({ hasText: 'still.jpg' }).click();
	dialog = page.locator('dialog[open]').last();
	await expect(dialog.locator('.cms-poster-row-name')).toHaveText('still.jpg');
	await dialog.getByRole('button', { name: 'Apply' }).click();
	await publish(page);

	await page.goto('/video');
	const video = page.locator('main video');
	await expect(video).toHaveAttribute('poster', /still-preview~[0-9a-f]{8}\.jpg$/);
	await expect(video.locator('source')).toHaveAttribute('src', /clip\.mp4$/);
});

import { describe, expect, it } from 'vitest';
import { assetLine, extension, fileIcon, humanSize } from '../../src/lib/library';

describe('human size', () => {
	it('formats bytes with growing units', () => {
		expect(humanSize(0)).toBe('0 B');
		expect(humanSize(512)).toBe('512 B');
		expect(humanSize(2048)).toBe('2.0 KB');
		expect(humanSize(5 * 1024 * 1024)).toBe('5.0 MB');
		expect(humanSize(3.4 * 1024 * 1024 * 1024)).toBe('3.4 GB');
	});
});

describe('file icon', () => {
	it('follows the mime type before the extension', () => {
		expect(fileIcon({ filename: 'report.bin', mime: 'application/pdf' })).toBe('file-earmark-pdf');
		expect(fileIcon({ filename: 'talk.mp3', mime: 'audio/mpeg' })).toBe('file-earmark-music');
		expect(fileIcon({ filename: 'clip.dat', mime: 'video/mp4' })).toBe('file-earmark-play');
		expect(
			fileIcon({
				filename: 'x',
				mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			}),
		).toBe('file-earmark-excel');
	});

	it('reads the extension when the mime type is missing or generic', () => {
		expect(fileIcon({ filename: 'terms.DOCX', mime: null })).toBe('file-earmark-word');
		expect(fileIcon({ filename: 'slides.pptx', mime: 'application/octet-stream' })).toBe(
			'file-earmark-ppt',
		);
		expect(fileIcon({ filename: 'backup.tar.gz' })).toBe('file-earmark-zip');
		expect(fileIcon({ filename: 'photo.webp' })).toBe('file-earmark-image');
	});

	it('falls back to the plain sheet', () => {
		expect(fileIcon({ filename: 'README', mime: 'application/octet-stream' })).toBe('file-earmark');
	});
});

describe('asset fact line', () => {
	it('reads the extension off the file name', () => {
		expect(extension('flyer.PDF')).toBe('PDF');
		expect(extension('archive.tar.gz')).toBe('GZ');
		expect(extension('README')).toBe('');
	});

	it('lists pixel dimensions and size for an image', () => {
		expect(assetLine({ filename: 'a.jpg', width: 2400, height: 1600, bytes: 862208 })).toBe(
			'2400 × 1600 px · 842.0 KB',
		);
	});

	it('falls back to the extension when dimensions are unknown', () => {
		expect(assetLine({ filename: 'notes.pdf', bytes: 12 })).toBe('PDF · 12 B');
		expect(assetLine({ filename: 'notes.pdf', width: null, height: null })).toBe('PDF');
	});

	it('is empty when nothing is known', () => {
		expect(assetLine({ filename: 'blob' })).toBe('');
	});
});

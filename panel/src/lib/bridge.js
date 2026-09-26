/**
 * The bridge types, re-exported for the controls that import them from here.
 *
 * @typedef {import('../types/bridge').ModalOptions} ModalOptions
 * @typedef {import('../types/bridge').UploadResult} UploadResult
 * @typedef {import('../types/bridge').BridgeSystem} BridgeSystem
 * @typedef {import('../types/bridge').CosrayBridge} CosrayBridge
 */

/** @returns {CosrayBridge} */
export function cosray() {
	if (!window.Cosray) {
		throw new Error(
			'window.Cosray is unavailable — editor controls only run on panel editor pages',
		);
	}

	return window.Cosray;
}

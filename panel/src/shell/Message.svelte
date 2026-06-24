<script lang="ts">
	import type { Snippet } from 'svelte';

	import IcoOctagonTimes from '$shell/icons/IcoOctagonTimes.svelte';
	import IcoShieldCheck from '$shell/icons/IcoShieldCheck.svelte';
	import IcoCircleInfo from '$shell/icons/IcoCircleInfo.svelte';
	import IcoTriangleExclamation from '$shell/icons/IcoTriangleExclamation.svelte';

	type Props = {
		type: any;
		text?: string;
		narrow?: boolean;
		children?: Snippet;
	};

	let { type, text = '', narrow = false, children }: Props = $props();

	function getToneClass() {
		switch (type) {
			case 'success':
				return 'cms-message-success';
			case 'info':
				return 'cms-message-info';
			case 'hint':
			case 'warning':
				return 'cms-message-warning';
			case 'error':
				return 'cms-message-error';
			default:
				return 'cms-message-info';
		}
	}

	function getTextToneClass() {
		switch (type) {
			case 'success':
				return 'cms-message-text-success';
			case 'info':
				return 'cms-message-text-info';
			case 'hint':
			case 'warning':
				return 'cms-message-text-warning';
			case 'error':
				return 'cms-message-text-error';
			default:
				return 'cms-message-text-info';
		}
	}
</script>

{#if type}
	<div class="message cms-message {getToneClass()}" class:narrow>
		<div class="cms-message-row">
			<div class="cms-message-icon {getTextToneClass()}" style="margin-top: -0.15rem">
				{#if type == 'success'}
					<IcoShieldCheck />
				{:else if type == 'info'}
					<IcoCircleInfo />
				{:else if type == 'warning'}
					<IcoTriangleExclamation />
				{:else if type == 'error'}
					<IcoOctagonTimes />
				{:else}
					<IcoCircleInfo />
				{/if}
			</div>
			<div class="cms-message-content" class:narrow>
				<div class="cms-message-text {getTextToneClass()}">
					{#if text}
						{@html text}
					{:else if children}
						{@render children()}
					{/if}
				</div>
			</div>
		</div>
	</div>
{/if}

<style>
	@layer panel {
		.cms-message {
			border-left: 4px solid transparent;
			padding: var(--space-4);
		}

		.cms-message.narrow {
			padding: var(--space-1) var(--space-2);
		}

		.cms-message-row {
			display: flex;
		}

		.cms-message-icon {
			flex-shrink: 0;
		}

		.cms-message-content {
			margin-left: var(--space-3);
		}

		.cms-message-content.narrow {
			margin-left: var(--space-2);
		}

		.cms-message-text {
			font-size: var(--font-size-sm);
		}

		.cms-message-success {
			background-color: color-mix(in srgb, var(--color-success-soft) 85%, white);
			border-left-color: color-mix(in srgb, var(--color-success) 70%, white);
		}

		.cms-message-info {
			background-color: color-mix(in srgb, var(--color-info) 8%, white);
			border-left-color: color-mix(in srgb, var(--color-info) 45%, white);
		}

		.cms-message-warning {
			background-color: color-mix(in srgb, var(--color-warning) 10%, white);
			border-left-color: color-mix(in srgb, var(--color-warning) 45%, white);
		}

		.cms-message-error {
			background-color: color-mix(in srgb, var(--color-danger) 10%, white);
			border-left-color: color-mix(in srgb, var(--color-danger) 45%, white);
		}

		.cms-message-text-success {
			color: var(--color-success);
		}

		.cms-message-text-info {
			color: var(--color-info);
		}

		.cms-message-text-warning {
			color: var(--color-warning);
		}

		.cms-message-text-error {
			color: var(--color-danger);
		}

		:global(.message em) {
			white-space: nowrap;
			font-weight: 600;
			font-style: italic;
		}
	}
</style>

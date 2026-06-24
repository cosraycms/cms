<script lang="ts">
	import { quintOut } from 'svelte/easing';
	import { crossfade } from 'svelte/transition';
	import { flip } from 'svelte/animate';
	import IcoTimes from '$shell/icons/IcoTimes.svelte';
	import toasts from '$lib/toast';
	import Toast from './Toast.svelte';
	import type { Toast as ToastType } from '$lib/toast';

	type Props = {
		center?: boolean;
	};

	let { center = false }: Props = $props();

	const [send, receive] = crossfade({
		duration: (d) => Math.sqrt(d * 200),

		fallback(node) {
			const style = getComputedStyle(node);
			const transform = style.transform === 'none' ? '' : style.transform;

			return {
				duration: 600,
				easing: quintOut,
				css: (t) => `
                    transform: ${transform} scale(${t});
                    opacity: ${t}
                `,
			};
		},
	});

	function remove(toast: ToastType) {
		return () => {
			toasts.remove(toast);
		};
	}
</script>

<div class="toasts" class:pos-bottom={!center} class:pos-center={center}>
	{#each $toasts as toast (toast)}
		<button
			onclick={remove(toast)}
			class="toast"
			class:toast-offset={!center}
			class:toast-success={toast.kind === 'success'}
			class:toast-error={toast.kind === 'error'}
			class:toast-warning={toast.kind === 'warning'}
			animate:flip={{ duration: 150 }}
			in:receive={{ key: toast }}
			out:send={{ key: toast }}
		>
			<Toast {toast} />
			{#if toast.kind === 'error'}
				<span class="cms-toast-close-icon">
					<IcoTimes />
				</span>
			{/if}
		</button>
	{/each}
</div>

<style>
	@layer panel {
		.toasts {
			position: fixed;
			z-index: 50;
			font-size: var(--cms-font-size-sm);
		}

		.toast {
			position: relative;
			display: block;
			margin-bottom: var(--cms-space-2);
			padding: var(--cms-space-4) var(--cms-space-4) var(--cms-space-5);
			border: none;
			text-align: left;
			cursor: pointer;
		}

		.toast:last-child {
			margin-bottom: var(--cms-space-4);
		}

		.toast-offset {
			margin-right: var(--cms-space-4);
		}

		.toast-success {
			background-color: var(--cms-color-success-600);
		}

		.toast-error {
			background-color: var(--cms-color-danger-700);
		}

		.toast-warning {
			background-color: var(--cms-color-warning-700);
		}

		.cms-toast-close-icon {
			position: absolute;
			top: var(--cms-space-1);
			right: var(--cms-space-1);
			height: var(--cms-space-4);
			width: var(--cms-space-4);
			cursor: pointer;
			border-radius: var(--cms-radius-full);
			color: var(--cms-color-white);
		}

		.pos-bottom {
			bottom: 0;
			right: 0;
			padding-right: var(--cms-space-8);
		}

		.pos-center {
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
		}
	}
</style>

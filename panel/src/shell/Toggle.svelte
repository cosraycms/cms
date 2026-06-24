<script lang="ts">
	import { setDirty } from '$lib/state';

	type Props = {
		value: boolean;
	};

	let { value = $bindable() }: Props = $props();

	function toggle() {
		value = !value;
		setDirty();
	}
</script>

<button
	type="button"
	onclick={toggle}
	aria-labelledby="change value"
	class="cms-toggle"
	class:is-on={value}
	role="switch"
	aria-checked="false"
>
	<span class="cms-toggle-knob" class:is-on={value}>
		<span class="cms-toggle-icon cms-toggle-off" class:is-hidden={value} aria-hidden="true">
			<svg class="cms-toggle-icon-svg" fill="none" viewBox="0 0 12 12">
				<path
					d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2"
					stroke="currentColor"
					stroke-width="2"
					stroke-linecap="round"
					stroke-linejoin="round"
				/>
			</svg>
		</span>
		<span class="cms-toggle-icon cms-toggle-on" class:is-hidden={!value} aria-hidden="true">
			<svg class="cms-toggle-icon-svg" fill="currentColor" viewBox="0 0 12 12">
				<path
					d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"
				/>
			</svg>
		</span>
	</span>
</button>

<style>
	@layer panel {
		.cms-toggle {
			position: relative;
			display: inline-flex;
			width: 2.75rem;
			height: 1.5rem;
			flex-shrink: 0;
			cursor: pointer;
			border-radius: var(--radius-full);
			border: 2px solid transparent;
			background-color: var(--color-neutral-200);
			transition: background-color 200ms ease-in-out;
		}

		.cms-toggle.is-on {
			background-color: var(--color-success-600);
		}

		.cms-toggle-knob {
			pointer-events: none;
			position: relative;
			display: inline-block;
			width: 1.25rem;
			height: 1.25rem;
			transform: translateX(0);
			border-radius: var(--radius-full);
			background-color: var(--color-white);
			box-shadow: var(--shadow);
			transition: transform 200ms ease-in-out;
		}

		.cms-toggle-knob.is-on {
			transform: translateX(1.25rem);
		}

		.cms-toggle-icon {
			position: absolute;
			inset: 0;
			display: flex;
			width: 100%;
			height: 100%;
			align-items: center;
			justify-content: center;
			opacity: 1;
			transition: opacity 180ms ease-in-out;
		}

		.cms-toggle-icon.is-hidden {
			opacity: 0;
		}

		.cms-toggle-off {
			color: var(--color-neutral-400);
		}

		.cms-toggle-on {
			color: var(--color-success-600);
		}

		.cms-toggle-icon-svg {
			width: 0.75rem;
			height: 0.75rem;
		}
	}
</style>

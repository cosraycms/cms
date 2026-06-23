import { writable } from 'svelte/store';

let initNavVisibility = true;

if (localStorage.getItem('navVisible') === 'false') {
	initNavVisibility = false;
}

const navVisible = writable(initNavVisibility);

function closeNav() {
	navVisible.set(false);
	localStorage.setItem('navVisible', 'false');
}

function openNav() {
	navVisible.set(true);
	localStorage.setItem('navVisible', 'true');
}

function toggleNav() {
	navVisible.update((state) => {
		let newState = !state;

		if (newState) {
			localStorage.setItem('navVisible', 'true');
		} else {
			localStorage.setItem('navVisible', 'false');
		}

		return newState;
	});
}

export { openNav, closeNav, toggleNav, navVisible };

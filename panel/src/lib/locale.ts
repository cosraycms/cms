import { __, __d, __dn, __n, activate, load } from '@celemas/verba';

const translator = load();

if (translator) {
	activate(translator);
}

export { __, __d, __dn, __n };

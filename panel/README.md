# Cosray panel

The panel combines PHP SSR views in `views/`, htmx behaviors in `src/behaviors/`, and custom elements in `src/elements/`, plain JavaScript modules the panel serves as they are.

## Development

Node.js and pnpm versions are declared in [package.json](package.json). From this directory:

```bash
pnpm test
pnpm run check
pnpm run modules:check
```

Nothing is built. The application serves `src/`, `styles/`, `icons/` and the vendored `modules/` from the package as they are, so an edit shows on the next reload. `pnpm run modules` refreshes `modules/` and its import map after a runtime dependency in `package.json` changes; `pnpm run check` type-checks the JSDoc-typed sources with `tsc`. Vitest resolves bare imports through `modules/importmap.json`, so tests run the copies the browser gets rather than `node_modules`.

A module a Composer package ships, such as `@celema/verba` from `celema/verba`, is listed in `package.json#cosray.composerModules` instead of being vendored. PHP serves it from the installed package, and `tsc`, Vitest and `modules:check` read it from `../vendor`, so install the Composer dependencies before the panel checks.

The panel includes the live reload script of `celema/server` like a site's layout does, so with `--watch` a panel page reloads when a file matching the application's watch patterns changes.

Native field tests render the actual PHP views before exercising browser-side behavior, so they need PHP 8.5 and the repository's Composer dependencies. No database is needed. These tests use jsdom; browser-only features such as form-associated custom elements still need real-browser verification.

`pnpm run format` formats the panel. For scoped changes, run Prettier only on the files changed rather than formatting unrelated files.

## Third-party modules

Most runtime packages come from npm: `pnpm run modules` copies the files the panel imports from `node_modules` into `modules/` unchanged.

A package in `package.json#cosray.sources` is built from its source repository instead, currently `prism-code-editor`, whose npm build hashes file names and renames exports to single letters, so every update would rewrite most of its files. `pnpm run modules` fetches the pinned `commit` into `.cache/sources/`, where git verifies it against the hash, and compiles each file the panel reaches on its own with TypeScript: types removed, comments kept, and relative imports spelled out as file paths. `modules/` then mirrors the package's source tree. Next to the modules lie the declarations the types of the named exports reach, for `tsc`; the Composer package leaves them out. The compiled output depends on the TypeScript version, so a TypeScript update can change it too.

`exports` lists the specifiers `src/` may import. A key ending in a slash maps a whole directory with a single import map entry, however many of its modules the panel loads; since a browser appends nothing to such a mapping, `src/` imports those modules with their extension (`prism-code-editor/languages/php.js`). Such modules are loaded for their side effects and get no declarations; [src/types/prism-code-editor.d.ts](src/types/prism-code-editor.d.ts) declares them all at once. `tsconfig.json` maps the named exports to their declarations. `modules:check` needs the checkout, so its first run fetches it.

### Updating a module

Update only for a reason: a bug the panel hits, a feature it needs, or a security advisory for code it ships. Packages built from source are not in `dependencies`, so `pnpm audit` does not cover them.

1. Change the version in `package.json` and run `pnpm install`, or for a package built from source, set `commit` and `version` to the release (`git ls-remote --tags <repository>` lists the commits).
2. Run `pnpm run modules`.
3. Review the diff of `modules/` before committing. For a package built from source, the upstream history is at hand too: `git -C .cache/sources/<name> diff <old> <new>`, after `git -C .cache/sources/<name> fetch --depth 1 <repository> <old>` if the old commit is missing. Look for new network or storage access, `eval`, `Function` or computed imports, new HTML insertion, obfuscated or minified code, changes that no upstream commit explains, new dependencies, and license changes.
4. Name the old and new version, the reason, and what the review found in the commit message.

## References

- [Editor controls](../docs/controls.md): descriptors, form transport, host properties/events, and modal lifecycle.
- [Panel styles](../docs/panel-styles.md): current CSS conventions and the live styleguide at `{panel.path}/styleguide` with `app.debug` enabled.
- [Panel keyboard](../docs/panel-keyboard.md): current keys and interaction tradeoffs.
- [Shared fixtures](../contract/README.md): PHP/TypeScript behavior boundaries.

The styleguide is for trying components and difficult states, not freezing their appearance. Use real screens as well when testing navigation, layout, and focus.

## License

Cosray-authored files are licensed under [MIT](../LICENSES/MIT.txt). Bundled third-party files retain their respective licenses; see [REUSE.toml](../REUSE.toml).

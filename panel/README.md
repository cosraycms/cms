# Cosray panel

The panel combines PHP SSR views in `views/`, htmx behaviors in `src/behaviors/`, and custom elements in `src/elements/`, plain JavaScript modules the panel serves as they are.

## Development

Node.js and pnpm versions are declared in [package.json](package.json). From this directory:

```bash
pnpm test
pnpm run check
pnpm run modules:check
```

Nothing is built. The application serves `src/`, `styles/`, `icons/` and the vendored `modules/` from the package as they are, so an edit shows on the next reload. `pnpm run modules` refreshes `modules/` and its import map after a runtime dependency in `package.json` changes; `pnpm run check` type-checks the JSDoc-typed sources with `tsc`.

A module a Composer package ships, such as `@celema/verba` from `celema/verba`, is listed in `package.json#cosray.composerModules` instead of being vendored. PHP serves it from the installed package, and `tsc`, Vitest and `modules:check` read it from `../vendor`, so install the Composer dependencies before the panel checks.

A prefix in `package.json#cosray.modulePrefixes`, such as `prism-code-editor/languages/`, gets a single import map entry for its directory, so lazily loaded modules like editor languages don't each add one. A browser appends nothing to a prefix mapping, so `src/` imports those modules with their extension (`prism-code-editor/languages/php.js`); `tsconfig.json` and `vitest.config.ts` map that form to `node_modules`, since the package's exports lack it.

The panel includes the live reload script of `celema/server` like a site's layout does, so with `--watch` a panel page reloads when a file matching the application's watch patterns changes.

Native field tests render the actual PHP views before exercising browser-side behavior, so they need PHP 8.5 and the repository's Composer dependencies. No database is needed. These tests use jsdom; browser-only features such as form-associated custom elements still need real-browser verification.

`pnpm run format` formats the panel. For scoped changes, run Prettier only on the files changed rather than formatting unrelated files.

## References

- [Editor controls](../docs/controls.md): descriptors, form transport, host properties/events, and modal lifecycle.
- [Panel styles](../docs/panel-styles.md): current CSS conventions and the live styleguide at `{panel.path}/styleguide` with `app.debug` enabled.
- [Panel keyboard](../docs/panel-keyboard.md): current keys and interaction tradeoffs.
- [Shared fixtures](../contract/README.md): PHP/TypeScript behavior boundaries.

The styleguide is for trying components and difficult states, not freezing their appearance. Use real screens as well when testing navigation, layout, and focus.

## License

Cosray-authored files are licensed under [MIT](../LICENSES/MIT.txt). Bundled third-party files retain their respective licenses; see [REUSE.toml](../REUSE.toml).

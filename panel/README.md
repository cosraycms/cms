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

For styling work, `pnpm dev` starts an optional Vite dev server; set `COSRAY_PANEL_DEV=1` for the application. The panel then takes its stylesheet from Vite, which applies style changes in place, and the page reloads when a file in `src/`, `views/` or `icons/` changes. Everything else still loads from PHP as in production, so check the result without the dev server too. `COSRAY_PANEL_DEV_ORIGIN` sets the server's origin; otherwise the request host with `COSRAY_PANEL_DEV_SCHEME` (default `http`) and `COSRAY_PANEL_DEV_PORT` (default `2001`) name it, and `COSRAY_PANEL_DEV_HOST` sets the address Vite listens on. The dev server is [vite.config.ts](vite.config.ts) and [dev.js](dev.js) plus one switch in [Controller\Panel\Panel](../src/Controller/Panel/Panel.php), so it can go again without leaving traces.

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

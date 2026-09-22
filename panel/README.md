# Cosray panel

The panel combines PHP SSR views in `views/`, htmx behaviors in `src/behaviors/`, and Svelte custom elements in `src/elements/`. There is no separate SvelteKit panel to maintain.

## Development

Node.js and pnpm versions are declared in [package.json](package.json). From this directory:

```bash
pnpm dev
pnpm test
pnpm run check
pnpm run build
```

The application loads Vite assets when `COSRAY_PANEL_DEV=1`. `COSRAY_PANEL_DEV_ORIGIN` overrides the origin; otherwise the request host and `COSRAY_PANEL_DEV_PORT` (default `2001`) select it. Production client installation is described in [application setup](../docs/application.md#panel-installation-and-theming).

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

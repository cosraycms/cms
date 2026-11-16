# Panel kit

Plugin pages build on a small set of documented panel components: Boiler partials under [panel/views/kit/](../panel/views/kit/) with stable classes from [kit.css](../panel/styles/kit.css), and the controller methods below. Other panel markup and classes are internal and change without notice. The [styleguide](panel-styles.md) shows the kit in its "Panel kit" section, `{panel.path}/styleguide?section=kit` with `app.debug` on.

## A page

```php
<?php $this->layout('layer/main') ?>

<div class="cms-page cms-kit-page">
    <?php $this->include('kit/head', [
        'title' => $report['title'],
        'crumbs' => [[__('acme-shop:orders'), $ordersUrl]],
        'count' => __n('acme-shop:count', 'acme-shop:count-plural', $total),
        'actions' => [['label' => __('acme-shop:export'), 'url' => $exportUrl, 'icon' => 'cloud-download', 'download' => true]],
    ]) ?>
    <div class="cms-kit-body">
        <div class="cms-page-toolbar">
            <?php $this->include('kit/filter', ['label' => __('acme-shop:status'), 'links' => $statuses]) ?>
        </div>
        <div class="cms-kit-scroll">
            <?php if ($orders === []): ?>
                <?php $this->include('kit/empty', ['title' => __('acme-shop:no-orders')]) ?>
            <?php else: ?>
                <ul class="cms-kit-list"><!-- one <li> per entry --></ul>
            <?php endif ?>
        </div>
        <?php $this->include('kit/pagination', compact('start', 'end', 'total', 'previous', 'next')) ?>
    </div>
</div>
```

`.cms-kit-page` fills the content region, `.cms-kit-body` is the pane below the head, and `.cms-kit-scroll` is the part that scrolls, so the head, the toolbar and the pagination stay in place. On narrow screens the whole page scrolls instead.

## Partials

| Partial | Arguments |
| --- | --- |
| `kit/head` | `title`; optional `crumbs` (list of `[label, url]`), `count` (text after the title), `actions` (list of `kit/button` arguments) |
| `kit/button` | `label`; `url` for a link, or `action` for a form that posts there with the CSRF field, or neither for a submit button of the surrounding form (`name`, `value`); optional `icon`, `variant` (`primary`, `secondary`, `danger`), `download`, `confirm` with `confirmAction`, `iconOnly` |
| `kit/filter` | `label` for screen readers; `links`, each `['label' => …, 'url' => …, 'active' => bool, 'count' => int]` |
| `kit/pagination` | `start`, `end`, `total`, `previous` and `next` (URL or null) |
| `kit/empty` | `title`; optional `text` |
| `kit/pill` | `label`; optional `tone` |
| `kit/field` | `name`, `label`; optional `type` (`text`, `textarea`, `select`, `file` or another input type), `value`, `options` (for `select`), `description`, `error`, `placeholder`, `rows`, `required`, `accept`, `form`, `id` |

A field's description and error are tied to its control with `aria-describedby`, and an error marks the control `aria-invalid`. Group fields in `.cms-kit-fields`, a grid that wraps on narrow screens.

Core partials that also belong to the kit:

- `component/csrf`: the hidden `_token` field every form that posts needs. See [CSRF protection](application.md#csrf-protection).
- `component/toast`: `['kind' => 'success', 'message' => …]` adds a toast; `kind` is `success`, `info`, `warning` or `error`.
- A confirm dialog: `hx-confirm="Question?"` on a form, with `data-confirm-action` as the label of the confirming button and `data-confirm-tone="danger"` for a destructive one. `kit/button` with `confirm` writes these.

## Classes

| Class | Use |
| --- | --- |
| `.cms-kit-list` | a list whose items are rows with wrapping columns |
| `.cms-kit-cards` | a column of cards with spacing |
| `.cms-kit-card` | a card; `data-tone` colors its left edge. Inside: `.cms-kit-card-head` (heading and `.cms-kit-card-meta`), free content, `.cms-kit-card-actions` |
| `.cms-kit-pill` | a pill; `data-tone` |
| `.cms-button` with `.cms-button-primary`, `-secondary`, `-danger`, `-small` | buttons and links that look like them |
| `.cms-input`, `.cms-textarea`, `.cms-select` | form controls |
| `.cms-page-toolbar` | a row of filters or forms above the scrolling part |

## Tones

`data-tone` takes `neutral` (the default), `info`, `success`, `warning` or `danger`. A plugin's own CSS reads the same colors from the tone tokens `--cms-color-{tone}`, `--cms-color-{tone}-surface` and `--cms-color-{tone}-text` for `info`, `success`, `warning` and `danger`. They follow the light and dark theme.

Plugin stylesheets prefix their classes with the plugin id, such as `.acme-shop-order`, and never define `cms-` classes.

## Controller methods

Plugin controllers extend [Panel](../src/Controller/Panel/Panel.php), whose constructor receives `Config`, the `Container` and the `Request`. These protected methods are plugin API:

| Method | Returns |
| --- | --- |
| `context(array $data)` | the page's context for the panel layout, merged with `$data` |
| `render($factory, $template, $context, $status)` | a rendered page with another status than 200 |
| `url($path, $query)` | a URL below the plugin's pages |
| `panelPath()` | `panel.path` |
| `target()` | the id of the element an htmx request swaps, `''` otherwise |
| `permits($permission)` | whether the signed-in user has a permission |
| `currentUser()` | the signed-in `User` |
| `actor()` | the signed-in user as the `Actor` of a change |
| `formData()` | the submitted form fields |
| `formatLocale()` | the locale dates and numbers are formatted in, like `de_DE` |
| `timezone()` | the time zone of the panel's timestamps |

`#[Permission('{id}.{name}')]` on a method refuses users without the permission. The other protected methods are internal.

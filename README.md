# Enterprise Content Platform for Shopware

This Shopware plugin integrates a connected Neos-based Enterprise Content Platform into Shopware.
It allows editors to create, manage, preview and render CMS layouts and Content Pages
from Neos directly inside the Shopware administration and storefront.

## What this plugin does

The plugin connects Shopware with a Neos installation and enables a Neos editing workflow for Shopware CMS content.

It allows you to:

- Use an embeded Neos content editor in the Shopware administration to create and edit CMS layouts and content pages.
- Create and edit CMS layouts for products, categories and content pages in Neos.
- Extend the Shopware navigation with pages provided by Neos.
- Render Neos-driven CMS content in the Shopware storefront.
- Provide storefront preview URLs for different sales channels and languages.

## Navigation extension

When a storefront request doesn't match anything in Shopware itself (no category, product, or CMS
route), the plugin checks whether Neos has something for that path before giving up:

1. It first checks a cached snapshot of Neos's page tree (refreshed at most once a day, per sales
   channel and language). An exact match there renders the page through Neos.
2. If the path isn't in that snapshot either, the plugin asks Neos directly instead of returning
   Shopware's own 404 straight away - Neos may still know a redirect (e.g. from its Redirect
   module) or serve an asset at a path that never made it into the page tree. Whatever Neos
   answers with (a redirect, content, or a genuine 404) becomes the final response.

This live fallback only applies to real storefront requests. Requests that merely check whether a
path is already taken (e.g. Shopware's Admin API validating a new SEO URL) only ever consult the
cached page tree, never Neos directly - so creating a Shopware SEO URL stays fast and Shopware's
own routing stays authoritative there.

Enable or disable this behavior per sales channel via the plugin's `extendNavigation` setting.

### Request data passed to Neos

Neos renders these pages through the content-by-path API, so it never sees the visitor's request
itself. The plugin passes on what page content might depend on:

- The query string of the storefront request is appended to the API request.
- The `x-sw-request-uri` header carries the public URL the visitor requested, e.g. for form
  submissions that store the page they were sent from.

Pages are cached by Shopware's HTTP cache like any other storefront page. If Neos answers with
`Cache-Control: no-store`, e.g. because a form was prefilled with personal data, the plugin
disables the HTTP cache for that response.

### Custom fields of Neos pages

Neos pages can carry installation specific data for the navigation, e.g. a teaser text and image
for a flyout menu. The plugin copies the `customFields` of every page in Neos's page tree into the
custom fields of the navigation category built from it, so a theme can use them like the custom
fields of any other category:

```twig
{% set teaserText = treeItem.category.translated.customFields.teaserText ?? null %}
{% if teaserText %}
    <p>{{ teaserText }}</p>
{% endif %}
```

The fields are added on the Neos side, see "Adding custom fields to pages of the page tree" in the
README of `netlogix/neos-shopware`. Their values are passed through unchanged, so escape them in the
template as usual.

## SEO redirects

Renaming or moving a Shopware category/product leaves its old SEO URL in place as a redirect to
the new one. Only Shopware's *canonical* (currently active) SEO URLs are followed as-is - a
canonical match always wins. For a stale, non-canonical one, the plugin checks the Neos page tree
first: if a Neos page has since taken over that same path, it's rendered instead of following
Shopware's redirect. If no Neos page exists there, Shopware's own redirect proceeds exactly as
before.

## SEO URL templates for Neos pages

By default a Neos page is served under the path Neos sends for it in the page tree. To change
these URLs, set a template in the "SEO URL template for Enterprise Content Platform pages" card
under Settings → SEO. As with Shopware's own SEO URL templates, there is a global template and
an optional override per sales channel. The card shows a preview of the resulting URLs.

The template is Twig. Its output is slugified, the same as Shopware's own SEO URL templates.
These variables are available:

| Variable            | Content                                                     |
|---------------------|-------------------------------------------------------------|
| `page.path`         | Path of the page in Neos, without leading/trailing slashes   |
| `page.label`        | Label of the page                                           |
| `page.identifier`   | Node identifier of the page                                 |
| `page.customFields` | Custom fields of the page (see above)                       |
| `page.breadcrumb`   | Labels from the top level page down to the page itself      |

```twig
{# today's URLs, the default #}
{{ page.path|raw }}

{# prefixed #}
content/{{ page.path|raw }}

{# built from the page labels #}
{% for part in page.breadcrumb %}{{ part }}/{% endfor %}
```

The rendered URL is used for navigation links, breadcrumbs, the sitemap, canonical and hreflang
tags, and the language switch. A trailing slash in the template (e.g. `{{ page.path|raw }}/`)
is kept in all of these. As with Shopware's own category URLs, the same path without the slash
still opens the page, and its canonical tag points to the URL with the slash. A GET request to a page's original Neos path (e.g. a link inside
Neos content) is redirected with a `301` to its new URL. Form submissions to the original path
keep working. The home page and shortcuts to external URLs are never rewritten. If a page's
template renders empty, that page keeps its Neos path. An invalid template is logged, and all
pages keep their Neos paths. Saving the template clears the cached page tree, so new URLs apply
right away.

## Requirements

- Shopware `>= 6.6.10.4`
- PHP `^8.2`
- Composer (for installation with composer)
- A reachable Neos instance with the required Shopware API integration
  - Registration for Demo possible

## Installation
### composer

```bash
composer require netlogix/neos-content
```
Make sure to run the shopware default plugin commands to install and activate the plugin, as well as building the administration JavaScript assets.


### Shopware Plugin Store
You can also install the plugin via the Shopware Plugin Store. Search for "Enterprise Content Platform" and follow the installation instructions.


## Contributing

If you want to contribute to this plugin, please fork the repository and create a pull request with your changes.
We welcome contributions that improve the functionality, performance, or documentation of the plugin.

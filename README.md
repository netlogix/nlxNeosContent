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

## SEO redirects

Renaming or moving a Shopware category/product leaves its old SEO URL in place as a redirect to
the new one. Only Shopware's *canonical* (currently active) SEO URLs are followed as-is - a
canonical match always wins. For a stale, non-canonical one, the plugin checks the Neos page tree
first: if a Neos page has since taken over that same path, it's rendered instead of following
Shopware's redirect. If no Neos page exists there, Shopware's own redirect proceeds exactly as
before.

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

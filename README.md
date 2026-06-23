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

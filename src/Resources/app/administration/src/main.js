//Permissions for Neos Content
import './acl'
import './module/neos';
import './module/neos/page/neos';
import './module/neos/neos-landing-page/';
import './init/route.service';
import './component/nlx-url-test-button';

Shopware.Component.register('nlx-preview-modal', () => import('./module/nlx-preview-modal/index'));

Shopware.Component.override('sw-cms-list',  () => import('./module/sw-cms/page/sw-cms-list'));
Shopware.Component.override('sw-cms-list-item',  () => import('./module/sw-cms/component/sw-cms-list-item'));
Shopware.Component.override('sw-product-detail-layout', () => import('./module/sw-product/view/sw-product-detail-layout'));
Shopware.Component.override('sw-category-layout-card',  () => import('./module/sw-category/component/sw-category-layout-card'));
Shopware.Component.override('sw-product-layout-assignment', () => import('./module/sw-product/component/sw-product-layout-assignment'));

import './services/urlTest.service'
import localeDE from './snippet/de_DE.json';
import localeEN from './snippet/en_GB.json';
Shopware.Locale.extend('de-DE', localeDE);
Shopware.Locale.extend('en-GB', localeEN);

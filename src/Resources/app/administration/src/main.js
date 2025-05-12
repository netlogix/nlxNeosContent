//Permissions for Neos Content
import './acl'
import './module/neos';
import './module/neos/page/neos';
import './module/neos/neos-landing-page/';


Shopware.Component.override('sw-cms-list',  () => import('./module/sw-cms/page/sw-cms-list'));
Shopware.Component.override('sw-cms-list-item',  () => import('./module/sw-cms/component/sw-cms-list-item'));
Shopware.Component.override('sw-category-layout-card',  () => import('./module/sw-category/component/sw-category-layout-card'));
Shopware.Component.override('sw-product-layout-assignment', () => import('./module/sw-product/component/sw-product-layout-assignment'));

//Permissions for Neos Content
import './acl'
import './module/neos';
import './module/neos/page/neos';
import './module/neos/neos-landing-page/';
import './module/sw-category';
import './view/nlx-sw-category-detail-neos';
import './component/nlx-url-test-button';
import './component/nlx-update-neos-templates-button'
import './component/nlx-invalidate-cms-page-caches-button'

import RouteService from "./services/route.service";
import './services/urlTest.service'
import NlxNeosContentApiService from './services/api.service';
import NlxCategoryStoreService from './services/categoryStore.service';

const { Application } = Shopware;

Shopware.Component.register('nlx-preview-modal', () => import('./module/nlx-preview-modal/index'));

Shopware.Service().register('nlxRoutes', () => {
    return new RouteService();
});

Application.addServiceProvider('nlxNeosContentApiService', (container) => {
    const initContainer = Application.getContainer('init');

    return new NlxNeosContentApiService(
        initContainer.httpClient,
        container.loginService
    );
})

Application.addServiceProvider('nlxCategoryStoreService', (container) => {
    return new NlxCategoryStoreService(container.nlxNeosContentApiService)
})

Shopware.Component.override('sw-cms-list',  () => import('./module/sw-cms/page/sw-cms-list'));
Shopware.Component.override('sw-cms-list-item',  () => import('./module/sw-cms/component/sw-cms-list-item'));
Shopware.Component.override('sw-product-detail-layout', () => import('./module/sw-product/view/sw-product-detail-layout'));
Shopware.Component.override('sw-category-layout-card',  () => import('./module/sw-category/component/sw-category-layout-card'));
Shopware.Component.override('sw-product-layout-assignment', () => import('./module/sw-product/component/sw-product-layout-assignment'));
Shopware.Component.override('sw-category-tree',  () => import('./module/sw-category/component/sw-category-tree'));
Shopware.Component.extend('nlx-sw-category-tree-item', 'sw-tree-item', () => import('./component/nlx-sw-category-tree-item'));
Shopware.Component.extend('nlx-neos-layout-card', 'sw-category-layout-card', () => import('./component/nlx-neos-layout-card'));
Shopware.Component.extend('nlx-neos-layout-card', 'sw-category-layout-card', () => import('./component/nlx-neos-layout-card'));
Shopware.Component.extend('nlx-neos-category-list-item', 'sw-cms-list-item', () => import('./component/nlx-neos-category-list-item'));

Shopware.State.registerModule('nlxNeosCategory', {
    namespaced: true,

    state() {
        return {
            data: null,
        }
    },

    mutations: {
        setData(state, data) {
            state.data = data;
        }
    }
});

Shopware.State.registerModule('nlxNeosCategories', {
    namespaced: true,

    state() {
        return {
            data: null,
        }
    },

    mutations: {
        setData(state, data) {
            state.data = data;
        }
    }
});

import localeDE from './snippet/de_DE.json';
import localeEN from './snippet/en_GB.json';
Shopware.Locale.extend('de-DE', localeDE);
Shopware.Locale.extend('en-GB', localeEN);

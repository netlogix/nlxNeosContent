const {Module} = Shopware;

Module.register('sw-category-detail-neos', {
    routes: {
        index: {
            component: 'nlx-sw-category-detail-neos',
            path: ':id',
            name: 'sw.category.detail.neos.index',
            meta: {
                parentPath: 'sw.category.index',
                privilege: 'category.viewer',
            },

            props: {
                default(route) {
                    return {
                        neosId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },
});

Shopware.Module.register('nlx-neos', {
    type: 'plugin',
    name: 'Enterprise Content Platform',
    color: '#00adee',
    icon: 'default-shopping-paper-bag-product',
    title: 'Enterprise Content Platform',
    description: 'Neos CMS embedded in Shopware',

    /*
        The navigation item we create here has to be nested under the 'sw-content' navigation item
        This is because we want to publish it in the Shopware Store and Plugins that do not follow this rule will be rejected.
        Depending on how good our resulting product will be we might be able to get an exception to this rule.
     */
    navigation: [
        {
            id: 'nlx-neos',
            label: 'Enterprise Content Platform',
            color: '#00adee',
            path: 'nlx.neos.index',
            icon: 'default-shopping-paper-bag-product',
            parent: 'sw-content',
            position: -10000,
            privilege: "neos.viewer"
        }
    ],

    routes: {
        index: {
            component: 'neos-index',
            name: 'nlx.neos.index',
            path: 'index',
            meta: {
                privilege: "neos.viewer"
            // the parentPath is useful for being able to navigate back to the parent route sw-standard way. In our case we dont need it.
            //     parentPath: 'sw.content.index'
            }
        },
        detail: {
            component: 'neos-index',
            name: 'nlx.neos.detail',
            path: 'detail/:nodeIdentifier/:entityId/:entityName',
            meta: {
                privilege: "neos.viewer"
            },
        }
    }
});

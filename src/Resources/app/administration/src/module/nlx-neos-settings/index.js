import './page/nlx-neos-settings';

const { Module } = Shopware;

Module.register('nlx-neos-settings', {
    type: 'plugin',
    name: 'nlxNeosSettings',
    title: 'nlx-neos-settings.title',
    description: 'nlx-neos-settings.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'regular-content',
    routes: {
        index: {
            component: 'nlx-neos-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.shop'
            }
        }
    },
    settingsItem: {
        group: 'plugins',
        to: 'nlx.neos.settings.index',
        icon: 'regular-content',
    }
});

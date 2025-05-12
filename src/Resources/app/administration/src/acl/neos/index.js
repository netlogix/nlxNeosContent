Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'neos',
    roles: {
        viewer: {
            privileges: [
                'neos:read',
                'nlx_neos_node:read'
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'neos:edit',
                'nlx_neos_node:update'
            ],
            dependencies: [
                'neos.viewer'
            ]
        }
    }
});

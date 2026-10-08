Shopware.Store.register({
    id: 'nlxNeosCategory',

    state: () => {
        return {
            data: null,
        };
    },

    actions: {
        setData(data) {
            this.data = data;
        },
    },
});

Shopware.Store.register({
    id: 'nlxNeosCategories',

    state: () => {
        return {
            data: null,
        };
    },

    actions: {
        setData(data) {
            this.data = data;
        },
    },
});

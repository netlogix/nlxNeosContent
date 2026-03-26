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

import template from './sw-category-tree.html.twig';

export default {
    template,

    inject: [
        'nlxCategoryStoreService',
    ],

    data() {
        return {
            neosPagesData: [],
        }
    },
    computed: {
        categories() {
            const result = this.$super('categories');

            let rootParentId = '';
            let afterCategoryId = '';
            Object.values(this.loadedCategories).forEach((category) => {
                if (category.parentId === null && category.afterCategoryId === null) {
                    rootParentId = category.id;
                }
                if (category.parentId !== null && category.afterCategoryId !== null) {
                    afterCategoryId = category.id;
                }
            });

            this.neosPagesData.forEach((page) => {
                if (page.parentId === '' && rootParentId !== '') {
                    page.parentId = rootParentId;
                }

                if (this.afterCategoryId !== '') {
                    page.afterCategoryId = afterCategoryId;
                }

                if (this.loadedParentIds.includes(page.parentId)) {
                    this.loadedParentIds.push(page.id);
                    result.push(page);
                }
            });

            return result;
        },
    },

    async created() {
        this.neosPagesData = await this.nlxCategoryStoreService.getCategories()
    },

    methods: {
        changeCategory(category) {
            let routeName = 'sw.category.detail';
            if (category.data?.neos) {
                routeName = 'sw.category.detail.neos.index';
                Shopware.State.commit('nlxNeosCategory/setData', category);
            }
            const route = {
                name: routeName,
                params: {id: category.id},
            };

            if (this.category && this.categoryRepository.hasChanges(this.category)) {
                this.$emit('unsaved-changes', route);
            } else {
                this.$router.push(route);
            }
        }
    }
}

import template from './sw-category-tree.html.twig';

export default {
    template,

    inject: ['nlxNeosContentApiService'],

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

    created() {
        this.nlxNeosContentApiService.getNeosPageTree().then((response) => {
            if (response.success) {
                this.neosPagesData = this.resolveNeosPageData(response.data.response.pages);
            } else {
                throw new Error('Failed to Neos page tree: ' + response.data.message);
            }
        });
    },

    methods: {
        changeCategory(category) {
            let routeName = 'sw.category.detail';
            if (category.data?.neos) {
                routeName = 'sw.category.detail.neos.index';
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
        },

        resolveNeosPageData(neosPageData, parentId = '', level = 2) {
            if (!neosPageData || !Array.isArray(neosPageData)) {
                return [];
            }

            let pages = [];
            neosPageData.forEach((page) => {
                let childCount = 0;
                if (page.children && page.children.length > 0) {
                    childCount = page.children.length;
                }

                pages.push({
                    active: true,
                    breadcrumb: [],
                    childCount: page.children.length ?? 0,
                    children: [],
                    cmsPageId: '',
                    cmsPageIdSwitched: true,
                    cmsPageVersionId: null,
                    createdAt: new Date().toISOString(),
                    displayNestedProducts: false,
                    id: page.identifier,
                    level: level,
                    name: page.label,
                    afterCategoryId: '',
                    afterCategoryVersionId: null,
                    versionId: null,
                    parentId: parentId,
                    type: 'page',
                    visible: true,
                    visibleChildCount: 0,
                    navigationSalesChannels: [],
                    footerSalesChannels: [],
                    nestedProducts: [],
                    serviceSalesChannels: [],
                    neos: true
                });

                if (childCount > 0) {
                    pages.push(...this.resolveNeosPageData(page.children, page.identifier, level + 1));
                }
            });

            return pages;
        }
    }
}
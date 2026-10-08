import template from './nlx-sw-category-detail-neos.html.twig'

Shopware.Component.extend(
    'nlx-sw-category-detail-neos',
    'sw-category-detail',
    {
        template,

        inject: [
            'nlxCategoryStoreService',
        ],

        props: {
            neosId: {
                type: String,
                required: true,
            }
        },

        data() {
            return {
                nlxNeosCategory: {
                    name: '',
                    id: '',
                },
            }
        },

        metaInfo() {
            return {
                title: this.nlxNeosCategory ? this.nlxNeosCategory.name : this.$t('sw-category.detailTitle'),
            }
        },

        created() {
            this.nlxCategoryStoreService.getCategory(this.neosId).then((category) => {
                this.nlxNeosCategory = category;
            });
        },

        watch: {
            neosId() {
                this.isLoading = true;
                this.nlxCategoryStoreService.getCategory(this.neosId)
                .then((category) => {
                    this.nlxNeosCategory = category;
                })
                .finally(() => {
                    this.isLoading = false;
                });
            },

            nlxNeosCategory() {
                if (this.nlxNeosCategory) {
                    Shopware.Store.get('nlxNeosCategory').setData(this.nlxNeosCategory);
                }
            }
        }
    }
);

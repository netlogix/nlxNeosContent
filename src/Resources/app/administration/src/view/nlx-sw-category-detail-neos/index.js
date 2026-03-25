import template from './nlx-sw-category-detail-neos.html.twig'

const {Application} = Shopware;

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
                    Shopware.State.commit('nlxNeosCategory/setData', this.nlxNeosCategory);
                }
            }
        }
    }
);

const {Mixin} = Shopware;

import template from './sw-product-layout-assignment.html.twig';
import './sw-product-layout-assignment.scss';

export default {
    template,

    inject: ['nlxNeosContentApiService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isCreatingInNeos: false,
        };
    },

    computed: {
        isNeosPage() {
            if (!this.cmsPage) {
                return;
            }

            return this.cmsPage.extensions?.nlxNeosNode?.neosConnection;
        },

        productRepository() {
            return this.repositoryFactory.create('product')
        },
    },

    emits: [
        'modal-preview-open'
    ],

    methods: {
        openInNeos() {
            if (!this.cmsPage) {
                // this should never happen
                console.error('No cmsPage provided');
            }

            this.$router.push({
                name: 'nlx.neos.detail',
                params: {
                    cmsPageId: this.cmsPage.id,
                    entityId: this.$router.currentRoute.value.params.id,
                    entityName: 'product',
                },
            });
        },

        openPreviewModal() {
            this.$emit('modal-preview-open');
        },

        async createInNeos() {
            this.isCreatingInNeos = true;

            const criteria = new Shopware.Data.Criteria(1, 1);
            criteria.addFilter(Shopware.Data.Criteria.equals('id', this.$router.currentRoute.value.params.id));
            criteria.addAssociation('visibilities');

            const product = await this.productRepository.search(criteria, Shopware.Context.api).then((products) => {
                return products.first();
            });

            this.nlxNeosContentApiService.createNeosLayout(
                {
                    title: product.translated.name,
                    pageType: this.cmsPage.type
                }
            ).then((response) => {
                if (response.success) {
                    if (response.data.success) {
                        this.$router.push({
                            name: 'nlx.neos.detail',
                            params: {
                                cmsPageId: response.data.cmsPageId,
                                entityId: this.$router.currentRoute.value.params.id,
                                entityName: 'product',
                            },
                        });
                    } else if (!response.data.success && response.data.foundPresentNode) {
                        this.createNotificationInfo({
                            title: this.$tc('sw-product.base.cms.createInNeos.infoTitle'),
                            message: this.$tc('sw-product.base.cms.createInNeos.infoMessage', {
                                layout: response.data.entityName
                            })
                        });
                    }
                }
            }).finally(() => this.isCreatingInNeos = false);
        }
    }
}

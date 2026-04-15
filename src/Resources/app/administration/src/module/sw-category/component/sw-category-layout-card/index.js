const {Mixin} = Shopware;
import template from './sw-category-layout-card.html.twig';

export default {
    template,

    inject: ['nlxNeosContentApiService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            showPreviewModal: false,
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
                    entityId: this.category.id,
                    entityName: 'category',
                },
            });
        },

        openPreviewModal() {
            this.showPreviewModal = true;
        },

        onClosePreviewModal() {
            this.showPreviewModal = false;
        },

        createInNeos() {
            this.isCreatingInNeos = true;

            this.nlxNeosContentApiService.createNeosLayout(
                {
                    title: this.category.translated.name,
                    pageType: this.cmsPage.type
                }
            ).then((response) => {
                if (response.success) {
                    if (response.data.success) {
                        this.$router.push({
                            name: 'nlx.neos.detail',
                            params: {
                                cmsPageId: response.data.cmsPageId,
                                entityId: this.category.id,
                                entityName: 'category',
                            },
                        });
                    } else if (!response.data.success && response.data.foundPresentNode) {
                        this.createNotificationInfo({
                            title: this.$tc('sw-category.base.cms.createInNeos.infoTitle'),
                            message: this.$tc('sw-category.base.cms.createInNeos.infoMessage', {
                                layout: response.data.entityName
                            })
                        });
                    }
                }
            }).finally(() => this.isCreatingInNeos = false);
        },
    },
}

import template from './sw-category-layout-card.html.twig';

export default {
    template,

    data() {
        return {
            showPreviewModal: false,
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
        }
    },
}

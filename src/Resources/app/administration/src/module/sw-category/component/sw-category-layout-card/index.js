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

            return this.cmsPage.extensions?.nlxNeosNode?.nodeIdentifier !== undefined;
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
            if (!this.cmsPage.extensions?.nlxNeosNode?.nodeIdentifier) {
                // this should never happen
                console.error('No nodeIdentifier provided');
            }

            this.$router.push({
                name: 'nlx.neos.detail',
                query: {
                    nodeIdentifier: this.cmsPage.extensions.nlxNeosNode.nodeIdentifier,
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

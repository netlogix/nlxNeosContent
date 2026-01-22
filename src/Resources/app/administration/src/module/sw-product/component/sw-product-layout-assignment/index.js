import template from './sw-product-layout-assignment.html.twig';

export default {
    template,

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
                    entityId: this.$router.currentRoute.value.params.id,
                    entityName: 'product',
                },
            });
        },

        openPreviewModal() {
            this.$emit('modal-preview-open');
        },
    },
}

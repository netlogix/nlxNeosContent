import template from './sw-product-detail-layout.html.twig';

export default {
    template,

    data() {
        return {
            showPreviewModal: false,
        };
    },

    methods: {
        onOpenPreviewModal() {
            this.showPreviewModal = true;
        },

        onClosePreviewModal() {
            this.showPreviewModal = false;
        }
    }
}

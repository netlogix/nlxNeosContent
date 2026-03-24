import template from './nlx-neos-layout-card.html.twig';

export default {
    template,

    props: {
        nlxNeosCategory: {
            type: Object,
            required: true,
        }
    },

    methods: {
        openInNeos() {
            this.$router.push({
                name: 'nlx.neos.cbp',
                params: {
                    nodeIdentifier: this.nlxNeosCategory.id,
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

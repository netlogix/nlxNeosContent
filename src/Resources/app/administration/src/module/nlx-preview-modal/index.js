import template from './nlx-preview-modal.html.twig';

export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        cmsPageId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            languageId: null,
            salesChannelId: null,
            newSalesChannelId: null,
            newLanguageId: null,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        domainRepository() {
            return this.repositoryFactory.create('sales_channel_domain')
        }
    },

    created() {
        this.initializeModal();
    },

    methods: {
        async initializeModal() {
            //TODO Add configuration to Plugin to allow setting a "favorite" sales-channel that will be preselected or some other kind of allowing a pre-selection

            const salesChannels = await this.salesChannelRepository.search(new Shopware.Data.Criteria(), Shopware.Context.api);
            const salesChannel = salesChannels.first();
            this.salesChannelId = salesChannel.id;

            //preset language to the current editing context
            this.languageId = Shopware.Context.api.language.id;
        },

        onSalesChannelInput (newSalesChannelId){
            this.salesChannelId = newSalesChannelId;
        },

        onLanguageInput (newLanguageId){
            this.languageId = newLanguageId;
        },

        async onConfirm() {
            const criteria = new Shopware.Data.Criteria(1, 1);
            criteria.addFilter(
                Shopware.Data.Criteria.equals('salesChannelId', this.salesChannelId)
            );
            criteria.addFilter(
                Shopware.Data.Criteria.equals('languageId', this.languageId)
            );

            const domains = await this.domainRepository.search(criteria, Shopware.Context.api);
            const domain = domains.first();

            if (!domain) {
                console.error('Could not resolve URL for SalesChannel with id: "' + this.salesChannelId + '" and language with id: "' + this.languageId + '"');
            }

            const baseUrl = domain.url;

            const previewUrl = new URL('preview', baseUrl + '/');
            previewUrl.searchParams.set('cmsPageId', this.cmsPageId);
            previewUrl.searchParams.set('entityId', this.$router.currentRoute.value.params.id || null);

            window.open(previewUrl.toString(), '_blank')

            this.modalClose();
        },

        onAbort() {
            this.modalClose();
        },

        modalClose(saveAfterClose = false) {
            this.$emit('modal-close', saveAfterClose);
        }
    }
}

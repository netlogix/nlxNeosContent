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
            isElementVisibleInSalesChannel: false,
            elementId: null,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        domainRepository() {
            return this.repositoryFactory.create('sales_channel_domain')
        },

        productRepository() {
            return this.repositoryFactory.create('product')
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
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

            this.elementId = this.$router.currentRoute.value.params.id;

            //preset language to the current editing context
            this.languageId = Shopware.Context.api.language.id;

            if (this.elementId) {
                await this.checkElementVisibility(this.elementId, this.salesChannelId);
            }
        },

        async checkElementVisibility(elementId, salesChannelId) {
            const product = await this.checkForProductVisibility(elementId)
            if (product) {
                this.isElementVisibleInSalesChannel = product.visibilities.some(visibility =>
                    visibility.salesChannelId === salesChannelId
                );
                return;
            }

            const category = await this.checkForCategoryVisibility(elementId)
            if (category) {
                const criteria = new Shopware.Data.Criteria(1, 1);
                criteria.addFilter(Shopware.Data.Criteria.equals('navigationCategoryId', category.id));

                const salesChannels = await this.salesChannelRepository.search(criteria, Shopware.Context.api);
                const catSalesChannels = salesChannels.first();
                if(catSalesChannels){
                    this.isElementVisibleInSalesChannel = catSalesChannels.id === salesChannelId
                }
                return;
            }

            this.isElementVisibleInSalesChannel = false;
        },

        async checkForProductVisibility(productId) {
            const criteria = new Shopware.Data.Criteria(1, 1);
            criteria.addFilter(Shopware.Data.Criteria.equals('id', productId));
            criteria.addAssociation('visibilities');

            const products = await this.productRepository.search(criteria, Shopware.Context.api);
            return products.first();
        },

        async checkForCategoryVisibility(categoryId) {
            const criteria = new Shopware.Data.Criteria(1, 1);
            criteria.addFilter(Shopware.Data.Criteria.equals('id', categoryId));

            const categories = await this.categoryRepository.search(criteria, Shopware.Context.api);
            const category = categories.first()
            if(category && category.parentId !== null){
                return await this.checkForCategoryVisibility(category.parentId)
            }

            return category
        },

        async onSalesChannelInput (newSalesChannelId){
            this.salesChannelId = newSalesChannelId;

            if (this.elementId) {
                await this.checkElementVisibility(this.elementId, newSalesChannelId);
            }
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

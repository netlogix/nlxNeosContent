import template from './sw-cms-list.html.twig';

export default {
    template,
    data() {
        return {
            currentPage: null,
            showNodeIdentifierChangeModal: false,
        }
    },
    computed: {
        listCriteria() {
            const criteria = this.$super('listCriteria');
            criteria.addAssociation('nlxNeosNode');
            return criteria;
        },

        nlxNeosNodeRepository() {
            return this.repositoryFactory.create('nlx_neos_node');
        },

        cmsPage: {
            async get() {
                return this.currentPage;
            },

            async set(value) {
                this.currentPage = value;
                this.currentPage.extensions ??= {};
                this.currentPage.extensions.nlxNeosNode ??= this.nlxNeosNodeRepository.create();
                this.currentPage.extensions.nlxNeosNode.cmsPageId = this.currentPage.id;
                this.currentPage.extensions.nlxNeosNode.versionId = this.currentPage.versionId;
                this.currentPage.extensions.nlxNeosNode.cmsPageVersionId = this.currentPage.versionId;
            }
        }
    },
    methods: {
        onChangeNeosNodeIdentifier(page) {
            this.neosNodeIdentifier = page.neosNodeIdentifier;
            this.showNodeIdentifierChangeModal = true;
            this.cmsPage = page;
        },

        onCloseNodeIdentifierChangeModal() {
            this.showNodeIdentifierChangeModal = false;
            this.currentPage = null;
            this.$emit('closeNeosNodeIdentifierModal');
        },

        async onConfirmNodeIdentifierChangeModal() {
            if (!this.currentPage) {
                this.createNotificationError({
                    //TODO translate
                    title: 'Error',
                    message: 'Failed could not resolve cms_page: ' + 1742907120
                });
                return;
            }

            try {
                await this.pageRepository.save(this.currentPage, Shopware.Context.api);
                this.$emit('closeNeosNodeIdentifierModal');
                this.createNotificationSuccess({
                    //TODO translate
                    title: 'Success',
                    message: 'Custom data saved successfully!'
                });
                this.showNodeIdentifierChangeModal = false;
            } catch (error) {
                this.createNotificationError({
                    //TODO translate
                    title: 'Error',
                    message: 'Failed to save data.'
                });
            }
        },

        isNeosPage(page) {
            return page.extensions?.nlxNeosNode?.nodeIdentifier !== undefined;
        },

        openInNeos(page) {
            this.$router.push({
                name: 'nlx.neos.detail',
                params: {
                    nodeIdentifier: page.extensions.nlxNeosNode.nodeIdentifier
                },
            });
        },

        onListItemClick(page) {
            if(this.isNeosPage(page)){
                this.openInNeos(page)
            }else{
                this.$super('onListItemClick', page);
            }
        }
    }
}

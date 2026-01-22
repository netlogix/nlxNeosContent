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
        isNeosPage(page) {
            return page.extensions?.nlxNeosNode?.neosConnection;
        },

        openInNeos(page) {
            this.$router.push({
                name: 'nlx.neos.detail',
                params: {
                    cmsPageId: page.id
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

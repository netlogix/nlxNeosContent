export default class CategoryStoreService {
    constructor(nlxNeosContentApiService) {
        this.nlxNeosContentApiService = nlxNeosContentApiService;
    }

    async getCategories() {
        await this._checkStoreLoaded()
        return Shopware.State.get('nlxNeosCategories').data;
    }

    async getCategory(id) {
        await this._checkStoreLoaded();
        return Shopware.State.get('nlxNeosCategories').data.find((category) => category.id === id);
    }

    async _checkStoreLoaded() {
        if (!Shopware.State.get('nlxNeosCategories').data) {
            const categories = await this._fetchCategories();
            Shopware.State.commit('nlxNeosCategories/setData', categories);
        }
    }

    async _fetchCategories() {
        const response = await this.nlxNeosContentApiService.getNeosPageTree()
        if (response.success) {
            return this._resolveNeosPageData(response.data.pages);
        } else {
            throw new Error('Failed to load Neos page tree: ' + response.data.message);
        }
    }

    _resolveNeosPageData(neosPageData, parentId = null, level = 2) {
        if (!neosPageData || !Array.isArray(neosPageData)) {
            return [];
        }

        let pages = [];
        neosPageData.forEach((page) => {
            let childCount = 0;
            if (page.children && page.children.length > 0) {
                childCount = page.children.length;
            }

            pages.push({
                active: true,
                breadcrumb: [],
                childCount: page.children.length ?? 0,
                children: [],
                cmsPageId: '',
                cmsPageIdSwitched: true,
                cmsPageVersionId: null,
                createdAt: new Date().toISOString(),
                displayNestedProducts: false,
                id: page.identifier,
                level: level,
                name: page.label,
                afterCategoryId: '',
                afterCategoryVersionId: null,
                versionId: null,
                parentId: parentId ?? '',
                type: 'page',
                visible: true,
                visibleChildCount: 0,
                navigationSalesChannels: [],
                footerSalesChannels: [],
                nestedProducts: [],
                serviceSalesChannels: [],
                neos: true,
                activeElementId: 'id',
            });

            if (childCount > 0) {
                pages.push(...this._resolveNeosPageData(page.children, page.identifier, level + 1));
            }
        });

        return pages;
    }
}

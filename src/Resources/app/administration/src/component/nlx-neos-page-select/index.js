import template from './nlx-neos-page-select.html.twig';
import './nlx-neos-page-select.scss';

const { debounce } = Shopware.Utils;

let pageTreeRequest = null;

function loadPageTree(nlxNeosContentApiService) {
    if (!pageTreeRequest) {
        pageTreeRequest = nlxNeosContentApiService.getNeosPageTree().then((response) => {
            if (!response.success) {
                return [];
            }

            const pages = (response.data && response.data.pages) || [];
            return flattenPages(pages);
        });
    }

    return pageTreeRequest;
}

function flattenPages(pages, result = []) {
    pages.forEach((page) => {
        result.push({
            identifier: page.identifier,
            label: page.label,
            path: page.path,
        });

        if (page.children && page.children.length) {
            flattenPages(page.children, result);
        }
    });

    return result;
}

Shopware.Component.register('nlx-neos-page-select', {
    template,

    inject: [
        'nlxNeosContentApiService',
    ],

    emits: ['update:value'],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },
        label: {
            type: String,
            required: false,
            default: null,
        },
        placeholder: {
            type: String,
            required: false,
            default: '',
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            isLoading: false,
            allPages: [],
        };
    },

    computed: {
        singleSelection() {
            if (!this.value) {
                return null;
            }

            return this.allPages.find((page) => page.identifier === this.value) || null;
        },

        results() {
            const term = this.searchTerm.trim().toLowerCase();

            if (!term) {
                return this.allPages.slice(0, 25);
            }

            return this.allPages
                .filter((page) => page.label.toLowerCase().includes(term))
                .slice(0, 25);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            loadPageTree(this.nlxNeosContentApiService).then((pages) => {
                this.allPages = pages;
                this.isLoading = false;
            });
        },

        onSelectExpanded() {
            this.isExpanded = true;
            this.searchTerm = '';

            this.$nextTick(() => {
                this.$refs.swSelectInput.select();
                this.$refs.swSelectInput.focus();
            });
        },

        onSelectCollapsed() {
            this.$refs.swSelectInput.blur();
            this.searchTerm = '';
            this.isExpanded = false;
        },

        onInputSearchTerm: debounce(function updateSearchTerm(event) {
            this.searchTerm = event.target.value;
        }, 250),

        setValue(item) {
            this.closeResultList();
            this.$emit('update:value', item.identifier);
        },

        clearSelection() {
            this.$emit('update:value', null);
        },

        clearInput() {
            this.searchTerm = '';
            this.clearSelection();
            this.closeResultList();
        },

        closeResultList() {
            this.$refs.selectBase.collapse();
        },

        displayLabelProperty(item) {
            return item ? item.label : '';
        },

        isSelected(item) {
            return item.identifier === this.value;
        },
    },
});

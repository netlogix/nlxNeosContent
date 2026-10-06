import template from './nlx-neos-seo-url-template-card.html.twig';

const { Component, Mixin } = Shopware;
const { debounce } = Shopware.Utils;

const CONFIG_DOMAIN = 'NlxNeosContent.config';
const CONFIG_KEY = `${CONFIG_DOMAIN}.neosPageSeoUrlTemplate`;
const DEFAULT_TEMPLATE = '{{ page.path|raw }}';

Component.register('nlx-neos-seo-url-template-card', {
    template,

    inject: ['systemConfigApiService', 'nlxNeosContentApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            salesChannelId: null,
            // Template per sales channel, keyed like systemConfigApiService.batchSave() expects ("null" = global).
            values: {},
            savedValues: {},
            isLoading: false,
            previews: [],
            previewError: null,
            previewLoading: false,
        };
    },

    computed: {
        currentKey() {
            return String(this.salesChannelId);
        },

        placeholder() {
            return (this.salesChannelId && this.values.null) || DEFAULT_TEMPLATE;
        },
    },

    created() {
        this.debouncedFetchPreview = debounce(this.fetchPreview, 400);
        this.loadTemplate();
    },

    methods: {
        async loadTemplate() {
            if (!(this.currentKey in this.values)) {
                this.isLoading = true;
                try {
                    const config = await this.systemConfigApiService.getValues(CONFIG_DOMAIN, this.salesChannelId);
                    this.values[this.currentKey] = config[CONFIG_KEY] ?? null;
                    this.savedValues[this.currentKey] = this.values[this.currentKey];
                } finally {
                    this.isLoading = false;
                }
            }

            this.fetchPreview();
        },

        onSalesChannelChanged(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.loadTemplate();
        },

        async fetchPreview() {
            const template = this.values[this.currentKey] ?? this.values.null ?? DEFAULT_TEMPLATE;

            this.previewLoading = true;
            const response = await this.nlxNeosContentApiService.previewSeoUrlTemplate(template, this.salesChannelId);
            this.previewLoading = false;

            this.previews = response.success ? response.data : [];
            this.previewError = response.success ? null : (response.data.message ?? this.$tc('nlx-neos-seo-url-template-card.textPreviewNotPossible'));
        },

        save() {
            if (this.previewError) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('nlx-neos-seo-url-template-card.messageSaveError'),
                });

                return Promise.resolve();
            }

            // Only changed values: every saved value invalidates the page tree and navigation caches.
            const changed = Object.entries(this.values)
                .filter(([salesChannelId, value]) => (value || null) !== (this.savedValues[salesChannelId] || null));
            if (changed.length === 0) {
                return Promise.resolve();
            }

            const data = Object.fromEntries(
                changed.map(([salesChannelId, value]) => [salesChannelId, { [CONFIG_KEY]: value || null }]),
            );

            return this.systemConfigApiService.batchSave(data).then(() => {
                this.savedValues = { ...this.values };
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('nlx-neos-seo-url-template-card.messageSaveError'),
                });
            });
        },
    },
});

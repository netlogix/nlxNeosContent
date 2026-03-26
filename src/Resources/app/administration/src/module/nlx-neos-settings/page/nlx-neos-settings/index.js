import template from './nlx-neos-settings.html.twig';
import './nlx-neos-settings.scss';

const { Component, Mixin } = Shopware;

Component.register('nlx-neos-settings', {
    template,

    inject: [
        'shopwareExtensionService',
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            extension: null,
        };
    },

    computed: {
        myExtensions() {
            return Shopware.Store.get('shopwareExtensions').myExtensions.data;
        },

        image() {
            if (this.extension?.icon) {
                return this.extension.icon;
            }

            if (this.extension?.iconRaw) {
                return `data:image/png;base64, ${this.extension.iconRaw}`;
            }

            return Shopware.Filter.getByName('asset')(
                'administration/administration/static/img/theme/default_theme_preview.jpg',
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            if (!this.myExtensions.length) {
                await this.shopwareExtensionService.updateExtensionData();
            }

            this.refreshExtension();
        },

        refreshExtension() {
            this.extension =
                this.myExtensions.find((extension) => {
                    return extension.name === 'NlxNeosContent';
                }) ?? null;
        },

        async saveAll() {
            this.isLoading = true;

            try {
                await this.$refs.systemConfig.saveAll();

                this.isSaveSuccessful = true;

                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('nlx-neos-settings.messageSaveSuccess')
                });

            } catch (error) {
                this.isSaveSuccessful = false;

                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: error
                });
            }

            this.isLoading = false;
        }
    }
});

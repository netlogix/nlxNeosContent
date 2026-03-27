const { Component, Mixin } = Shopware;
import template from './nlx-url-test-button.html.twig';

Component.register('nlx-url-test-button', {
    template,

    props: ['label'],
    inject: ['nlxUrlTestService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent && $parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData?.[null] || {};
        },

        neosBaseUri() {
            return this.pluginConfig['NlxNeosContent.settings.neosBaseUri'];
        }
    },

    watch: {
        neosBaseUri(newValue, oldValue) {
            if (newValue !== oldValue) {
                this.isSaveSuccessful = false;
            }
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            const url = this.neosBaseUri;
            if (!url) {
                this.createNotificationError({
                    title: this.$tc('nlx-url-test-button.label'),
                    message: this.$tc('nlx-url-test-button.emptyUrlError')
                });
                return;
            }

            this.isLoading = true;
            this.nlxUrlTestService.check(url).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('nlx-url-test-button.label'),
                        message: this.$tc('nlx-url-test-button.success')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('nlx-url-test-button.label'),
                        message: this.$tc('nlx-url-test-button.error')
                    });
                }

                this.isLoading = false;
            });
        }
    }
})

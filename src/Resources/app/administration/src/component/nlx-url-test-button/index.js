const { Component, Mixin } = Shopware;
import template from './nlx-url-test-button.html.twig';

Component.register('nlx-url-test-button', {
    template,

    props: ['label'],
    inject: ['nlxUrlTest'],

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

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData;
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
            this.nlxUrlTest.check(this.pluginConfig).then((res) => {
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

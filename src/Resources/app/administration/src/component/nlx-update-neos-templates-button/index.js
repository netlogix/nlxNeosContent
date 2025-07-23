const { Component, Mixin } = Shopware;
import template from './nlx-update-neos-templates-button.html.twig';

Component.register('nlx-update-neos-templates-button', {
    template,

    props: ['label'],
    inject: ['nlxNeosContentApiService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
        };
    },

    methods: {
        updateNeosPages() {
            this.isLoading = true;
            this.nlxNeosContentApiService.updateNeosPages().catch((error) => {
                this.createNotificationError(error);
            }).finally(() => {
                this.createNotificationSuccess({
                    title: this.$tc('nlx-update-neos-templates-button.label'),
                    message: this.$tc('nlx-update-neos-templates-button.success')
                });
                this.isLoading = false;
            });
        }
    }
})

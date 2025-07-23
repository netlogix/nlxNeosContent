const { Component, Mixin } = Shopware;
import template from './nlx-invalidate-cms-page-caches-button.html.twig';

Component.register('nlx-invalidate-cms-page-caches-button', {
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
        clearNeosPageCaches() {
            this.isLoading = true;
            this.nlxNeosContentApiService.clearNeosPageCaches().catch((error) => {
                this.createNotificationError(error);
            }).finally(() => {
                this.createNotificationSuccess({
                    title: this.$tc('nlx-invalidate-cms-page-caches-button.label'),
                    message: this.$tc('nlx-invalidate-cms-page-caches-button.success')
                })
                this.isLoading = false;
            });
        }
    }
})

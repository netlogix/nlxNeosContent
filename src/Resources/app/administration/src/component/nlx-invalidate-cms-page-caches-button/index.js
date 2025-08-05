const {Component, Mixin} = Shopware;
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
            this.nlxNeosContentApiService.clearNeosPageCaches().then((response) => {
                if (response.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('nlx-invalidate-cms-page-caches-button.label'),
                        message: this.$tc('nlx-invalidate-cms-page-caches-button.success')
                    })
                } else {
                    const message = response.data.errors[0].detail ?? this.$tc('nlx-invalidate-cms-page-caches-button.error');
                    this.createNotificationError({
                        title: this.$tc('nlx-invalidate-cms-page-caches-button.error-title'),
                        message: message
                    })
                }
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
})

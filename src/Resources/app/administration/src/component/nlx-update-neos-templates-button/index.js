const {Component, Mixin} = Shopware;
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
            this.nlxNeosContentApiService.updateNeosPages().then((response) => {
                if (response.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('nlx-update-neos-templates-button.label'),
                        message: this.$tc('nlx-update-neos-templates-button.success')
                    });
                } else {
                    const message = response.data.errors[0].detail ?? this.$tc('nlx-update-neos-templates-button.error');
                    this.createNotificationError({
                        title: this.$tc('nlx-update-neos-templates-button.error-title'),
                        message: message
                    })
                }
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});

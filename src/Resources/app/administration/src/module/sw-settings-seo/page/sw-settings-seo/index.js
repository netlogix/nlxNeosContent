import template from './sw-settings-seo.html.twig';

export default {
    template,

    methods: {
        onClickSave() {
            this.$super('onClickSave');
            this.$refs.nlxNeosSeoUrlTemplateCard.save();
        },
    },
};

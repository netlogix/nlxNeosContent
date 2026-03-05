import template from './nlx-sw-category-detail-neos.html.twig'

const {Application} = Shopware;

Shopware.Component.extend(
    'nlx-sw-category-detail-neos',
    'sw-category-detail',
    {
        template,

        props: {
            neosId: {
                type: String,
                required: true,
            }
        },

        metaInfo() {
            return {
                title: 'Neos-Category'
            }
        },
        created() {
            console.log('sw-category props:', this.props);
        }
    }
);
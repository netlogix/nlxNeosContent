import template from './neos-index.html.twig';
import './neos-index.scss';

Shopware.Component.register('neos-index', {
    template,

    inject: ['systemConfigApiService'],

    props: {
        neosBaseUri: {
            type: String,
            required: true,
        }
    },

    data() {
        return {
            config: {
                neosBaseUri: null
            }
        };
    },

    created() {
        this.loadConfig();
        this.createdComponent();
    },

    methods: {
        loadConfig() {
            const configService = Shopware.Service('systemConfigApiService');
            const loginService = Shopware.Service('loginService');
            if (!loginService.isLoggedIn()) {
                console.error('User is not logged in');
                return;
            }
            const token = loginService.getToken();

            const currentRoute = this.$router.currentRoute;

            if (currentRoute._value.name === 'nlx.neos.index') {
                configService.getValues('NlxNeosContent').then((response) => {
                    this.config.neosBaseUri = `${response['NlxNeosContent.config.neosBaseUri']}/neos/shopware/login/${token}`;
                });
            }

            if (currentRoute._value.name === 'nlx.neos.detail') {
                configService.getValues('NlxNeosContent').then((response) => {
                    const nodeIdentifier = currentRoute.value.params.nodeIdentifier;
                    const language = 'english'; //FIXME: Somehow get the current language (language) either from the route or append it to the url calling this component
                    this.config.neosBaseUri = `${response['NlxNeosContent.config.neosBaseUri']}/neos/shopware/login/${token}?nodeIdentifier=${nodeIdentifier}&language=${language}`;
                });
            }
        },

        createdComponent() {
            Shopware.Store.get('adminMenu').collapseSidebar();
        }
    }
});

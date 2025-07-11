import template from './neos-index.html.twig';
import './neos-index.scss';

const getNeosBaseUri = async () => {
    const configService = Shopware.Service('systemConfigApiService');
    const netlogixNeosContentConfig = await configService.getValues('NetlogixNeosContent');

    return netlogixNeosContentConfig['NetlogixNeosContent.config.neosBaseUri'];
}

Shopware.Component.register('neos-index', {
    template,

    inject: ['systemConfigApiService', 'nlxRoutes', 'repositoryFactory'],

    props: {
        neosBaseUri: {
            type: String,
            required: true,
        },
        token: {
            type: String,
            required: true,
        },
        apiUrl: {
            type: String,
            required: true,
        },
        inactiveConfiguration: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            config: {
                neosBaseUri: null,
                token: null,
                apiUrl: null,
            },
            inactiveConfiguration: false
        };
    },

    created() {
        this.loadConfig();
        this.createdComponent();
    },

    mounted() {
        this.$nextTick(async () => {
            const form = this.$refs.neosIframeForm;
            const neosBaseUri = await getNeosBaseUri();
            this.inactiveConfiguration = !neosBaseUri;
            if (this.inactiveConfiguration) {
                // If Neos is not active, we load the Fillout registration script
                const script = document.createElement('script');
                script.id = 'fillout-registration';
                script.src = 'https://server.fillout.com/embed/v1/';
                script.async = true;
                document.body.appendChild(script);
                return;
            }
            //FIXME: This is a workaround since the url in form.action differs from the url set in the html form action property
            //FIXME: Get rid of the timeout and ensure that the correct url is set
            setTimeout(() => {
                form.submit();
            }, 1000);

            const loginService = Shopware.Service('loginService');
            if (!loginService.isLoggedIn()) {
                console.error('User is not logged in');
                return;
            }

            // send refreshed token to Neos
            loginService.addOnTokenChangedListener(auth => {
                const iframe = this.$refs.iframe;
                const token = auth.access;

                iframe.contentWindow.postMessage({
                    nlxShopwareMessageType: 'token-changed',
                    token: token,
                    apiUrl: Shopware.Context.api.schemeAndHttpHost,
                }, neosBaseUri);
            });
        });
    },

    computed: {
        localeRepository() {
            return this.repositoryFactory.create('locale');
        }
    },

    methods: {
        async loadConfig() {
            const loginService = Shopware.Service('loginService');
            if (!loginService.isLoggedIn()) {
                console.error('User is not logged in');
                return;
            }

            this.config.token = loginService.getToken();
            this.config.apiUrl = Shopware.Context.api.schemeAndHttpHost;

            const currentRoute = this.$router.currentRoute;
            const neosBaseUri = await getNeosBaseUri();
            if (currentRoute._value.name === 'nlx.neos.index') {
                this.config.neosBaseUri = this.nlxRoutes.getNeosIndexRoute(neosBaseUri);
            }

            if (currentRoute._value.name === 'nlx.neos.detail') {
                const queryParams = await this.getDetailQueryParams();
                this.config.neosBaseUri = this.nlxRoutes.getNeosDetailRoute(neosBaseUri, queryParams);
            }
        },

        createdComponent() {
            Shopware.Store.get('adminMenu').collapseSidebar();
        },

        async getDetailQueryParams() {
            const queryParams = [];

            const fallbackLocale = Shopware.Context.app.fallbackLocale;

            let localeId = Shopware.Context.api.language.localeId;
            let languageParam = "";

             const criteria = new Shopware.Data.Criteria();
             criteria.addFilter(Shopware.Data.Criteria.equals('id', localeId));

            const locales = await this.localeRepository.search(criteria, Shopware.Context.api);
            const locale = locales.first();

            if (!locale) {
                console.warn('No locale found using fallback:', fallbackLocale);
                languageParam = fallbackLocale;
            } else {
                languageParam = locale.code;
            }

            queryParams.push({ key: 'nodeIdentifier', value: this.$router.currentRoute.value.query.nodeIdentifier });
            queryParams.push({ key: 'language', value: languageParam });
            queryParams.push({ key: 'swEntityId', value: this.$router.currentRoute.value.query.entityId ?? '' });
            queryParams.push({ key: 'swEntityName', value: this.$router.currentRoute.value.query.entityName ?? '' });
            return queryParams;
        }
    }
});

import template from './neos-index.html.twig';
import './neos-index.scss';

const getNeosBaseUri = async () => {
    const configService = Shopware.Service('systemConfigApiService');
    const nlxNeosContentConfig = await configService.getValues('NlxNeosContent');

    return nlxNeosContentConfig['NlxNeosContent.config.neosBaseUri'];
}

Shopware.Component.register('neos-index', {
    template,

    inject: ['systemConfigApiService', 'nlxRoutes', 'repositoryFactory', 'nlxNeosContentApiService'],

    props: {
        neosLoginRoute: {
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
        },
        shopwareVersion: {
            type: String,
            required: true,
        }
    },

    data() {
        return {
            config: {
                neosLoginRoute: null,
                token: null,
                apiUrl: null,
                shopwareVersion: Shopware.Context.app.config.version
            },
            inactiveConfiguration: false
        };
    },

    create() {
        this.createdComponent();
    },

    mounted() {
        this.$nextTick(async () => {
            await this.loadConfig();
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

            const loginService = Shopware.Service('loginService');
            if (!loginService.isLoggedIn()) {
                console.error('User is not logged in');
                return;
            }

            form.action = this.config.neosLoginRoute;
            this.addHiddenInputToForm(form, 'shopwareAccessToken', this.config.token);
            this.addHiddenInputToForm(form, 'apiUrl', this.config.apiUrl);
            this.addHiddenInputToForm(form, 'shopwareVersion', this.config.shopwareVersion);
            form.submit();

            // send refreshed token to Neos
            loginService.addOnTokenChangedListener(async () => {
                const iframe = this.$refs.iframe;
                const token = await this.nlxNeosContentApiService.getNeosToken().then((response) => {
                    if (response.success) {
                        return response.data.token;
                    } else {
                        throw new Error('Failed to retrieve Neos token: ' + response.data.message);
                    }
                });

                iframe.contentWindow.postMessage({
                    nlxShopwareMessageType: 'token-changed',
                    token: token,
                    apiUrl: Shopware.Context.api.schemeAndHttpHost,
                    shopwareVersion: this.config.shopwareVersion
                }, this.config.neosLoginRoute);
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

            this.config.token = await this.nlxNeosContentApiService.getNeosToken().then((response) => {
                if (response.success) {
                    return response.data.token;
                } else {
                    throw new Error('Failed to retrieve Neos token: ' + response.data.message);
                }
            });
            this.config.apiUrl = Shopware.Context.api.schemeAndHttpHost;

            const currentRoute = this.$router.currentRoute;
            const neosBaseUri = await getNeosBaseUri();
            if (currentRoute._value.name === 'nlx.neos.index') {
                this.config.neosLoginRoute = this.nlxRoutes.getNeosIndexRoute(neosBaseUri);
            }

            if (currentRoute._value.name === 'nlx.neos.detail') {
                const queryParams = await this.getDetailQueryParams();
                this.config.neosLoginRoute = this.nlxRoutes.getNeosDetailRoute(neosBaseUri, queryParams);
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

            queryParams.push({ key: 'nodeIdentifier', value: this.$router.currentRoute.value.params.nodeIdentifier });
            queryParams.push({ key: 'language', value: languageParam });
            queryParams.push({ key: 'swEntityId', value: this.$router.currentRoute.value.params.entityId ?? '' });
            queryParams.push({ key: 'swEntityName', value: this.$router.currentRoute.value.params.entityName ?? '' });
            return queryParams;
        },

        addHiddenInputToForm(form, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
    }
});

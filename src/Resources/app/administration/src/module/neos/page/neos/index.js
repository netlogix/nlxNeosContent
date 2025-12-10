import template from './neos-index.html.twig';
import './neos-index.scss';
const { Criteria } = Shopware.Data;
const { api  } = Shopware.Context;

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
            isLoading: true,
            iframeSrc: null,
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

            this.loadNeosIntoIframe().catch((error) => {
                console.error('Failed to load Neos into iframe:', error);
            });

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

                if (!this.config.neosLoginRoute) {
                    console.error('Could not refresh token: neosLoginRoute is undefined');
                    return;
                }

                iframe.contentWindow.postMessage(
                    {
                        nlxShopwareMessageType: 'token-changed',
                        token: token,
                        apiUrl: api.schemeAndHttpHost,
                        shopwareVersion: this.config.shopwareVersion,
                    },
                    this.config.neosLoginRoute
                );
            });
        });
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
        async getSalesChannels() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('typeId', '8a243080f92e4c719546314b577cf82b')); // SALES_CHANNEL_TYPE_STOREFRONT

            return await this.salesChannelRepository.search(criteria, api);
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
            this.config.apiUrl = api.schemeAndHttpHost;

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

        async loadNeosIntoIframe() {
            const salesChannel = await this.getSalesChannels.then(sc => sc.first());
            const response = await fetch(this.config.neosLoginRoute, {
                method: 'POST',
                credentials: 'include',
                redirect: 'follow',
                headers: {
                    'x-sw-language-id': api.language.id,
                    'x-sw-sales-channel-id': salesChannel.id,
                    'x-sw-context-token': salesChannel.accessKey,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    shopwareAccessToken: this.config.token,
                    apiUrl: this.config.apiUrl,
                    shopwareVersion: this.config.shopwareVersion,
                })
            });

            response.json().then(content => {
                this.iframeSrc = content.iframeUri;
                this.isLoading = false;
            });
        },

        createdComponent() {
            Shopware.Store.get('adminMenu').collapseSidebar();
        },

        async getDetailQueryParams() {
            const queryParams = [];
            queryParams.push({key: 'nodeIdentifier', value: this.$router.currentRoute.value.params.nodeIdentifier});
            queryParams.push({key: 'swEntityId', value: this.$router.currentRoute.value.params.entityId ?? ''});
            queryParams.push({key: 'swEntityName', value: this.$router.currentRoute.value.params.entityName ?? ''});
            return queryParams;
        }
    }
});

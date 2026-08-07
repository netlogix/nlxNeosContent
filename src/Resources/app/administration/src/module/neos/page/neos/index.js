const {Mixin} = Shopware;
import template from './neos-index.html.twig';
import './neos-index.scss';

const {Criteria} = Shopware.Data;
const {api} = Shopware.Context;

const DEFAULT_TIMEOUT_MS = 15000;

Shopware.Component.register('neos-index', {
    template,

    inject: ['nlxRoutes', 'repositoryFactory', 'nlxNeosContentApiService', 'nlxConfigService', 'nlxAsyncService'],

    mixins: [
        Mixin.getByName('notification')
    ],

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
        },
        cmsPageId: {
            type: String,
            required: false,
        },
        entityId: {
            type: String,
            required: false,
        },
        entityName: {
            type: String,
            required: false,
        },
        nodeIdentifier: {
            type: String,
            required: false,
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

    watch: {
        inactiveConfiguration(value) {
            this.toggleContentScroll(value);
        }
    },

    created() {
        Shopware.Store.get('adminMenu').collapseSidebar();
        this._isUnmounted = false;
        this._onWindowMessage = (event) => {
            if (event.data && event.data.type === 'nlxOpenCmsPage') {
                this.$router.push({
                    name: 'sw.cms.detail',
                    params: {id: event.data.cmsPageId},
                });
            }
        };
        window.addEventListener('message', this._onWindowMessage);
    },

    beforeUnmount() {
        this.toggleContentScroll(false);
        this._isUnmounted = true;
        window.removeEventListener('message', this._onWindowMessage);
    },

    mounted() {
        this.$nextTick(async () => {
            try {
                await this.bootstrapNeosIframe();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('neos.loadNeosIntoIframe.loginError.title'),
                    message: this.$tc('neos.loadNeosIntoIframe.loginError.message', {
                        errorMessage: error.message
                    })
                });
                this.isLoading = false;
            }
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
        async bootstrapNeosIframe() {
            await this.loadConfig();

            const neosBaseUri = await this.nlxAsyncService.withRetry(() => this.nlxAsyncService.withTimeout(
                this.nlxConfigService.getSetting('neosBaseUri'), DEFAULT_TIMEOUT_MS, 'Loading Neos configuration'
            ));
            this.inactiveConfiguration = !neosBaseUri;
            this.toggleContentScroll(this.inactiveConfiguration);
            if (this.inactiveConfiguration) {
                this.loadFilloutRegistrationScript();
                this.isLoading = false;
                return;
            }

            const loginService = Shopware.Service('loginService');
            if (!loginService.isLoggedIn()) {
                this.triggerLoginModal(loginService);
                this.isLoading = false;
                return;
            }

            this.loadNeosIntoIframe();
            this.registerTokenRefreshListener(loginService, neosBaseUri);
        },

        loadFilloutRegistrationScript() {
            const script = document.createElement('script');
            script.id = 'fillout-registration';
            script.src = 'https://server.fillout.com/embed/v1/';
            script.async = true;
            document.body.appendChild(script);
        },

        registerTokenRefreshListener(loginService, neosBaseUri) {
            loginService.addOnTokenChangedListener(() => this.refreshNeosToken(neosBaseUri));
        },

        async refreshNeosToken(neosBaseUri) {
            if (this._isUnmounted) return;
            const iframe = this.$refs.iframe;
            if (!iframe) return;

            let token;
            try {
                token = await this.nlxAsyncService.withRetry(async () => {
                    const response = await this.nlxAsyncService.withTimeout(
                        this.nlxNeosContentApiService.getNeosToken(), DEFAULT_TIMEOUT_MS, 'Refreshing Neos token'
                    );
                    if (!response.success) {
                        throw new Error('Failed to retrieve Neos token: ' + response.data.message);
                    }
                    return response.data.token;
                });
            } catch (error) {
                // Best-effort background refresh - Shopware fires another token-changed event in
                // ~5 minutes, so a failed attempt here isn't worth interrupting the user for.
                return;
            }

            const tokenRefreshRoute = this.nlxRoutes.getNeosIndexRoute(neosBaseUri);
            if (!tokenRefreshRoute) {
                this.createNotificationWarning({
                    message: this.$tc('neos.tokenRefresh.noRouteWarning.message'),
                });
                return;
            }

            iframe.contentWindow.postMessage(
                {
                    nlxShopwareMessageType: 'token-changed',
                    token: token,
                    apiUrl: api.schemeAndHttpHost,
                    shopwareVersion: this.config.shopwareVersion,
                },
                tokenRefreshRoute
            );
        },

        async loadConfig() {
            const loginService = Shopware.Service('loginService');
            if (!loginService.isLoggedIn()) {
                this.triggerLoginModal(loginService);
                return;
            }

            this.config.token = await this.nlxAsyncService.withRetry(async () => {
                const response = await this.nlxAsyncService.withTimeout(
                    this.nlxNeosContentApiService.getNeosToken(), DEFAULT_TIMEOUT_MS, 'Fetching Neos token'
                );
                if (!response.success) {
                    throw new Error('Failed to retrieve Neos token: ' + response.data.message);
                }
                return response.data.token;
            });
            this.config.apiUrl = api.schemeAndHttpHost;

            const currentRoute = this.$router.currentRoute;
            const neosBaseUri = await this.nlxAsyncService.withRetry(() => this.nlxAsyncService.withTimeout(
                this.nlxConfigService.getSetting('neosBaseUri'), DEFAULT_TIMEOUT_MS, 'Loading Neos configuration'
            ));
            if (currentRoute._value.name === 'nlx.neos.index') {
                this.config.neosLoginRoute = this.nlxRoutes.getNeosIndexRoute(neosBaseUri);
            }

            const queryParams = await this.getDetailQueryParams();
            if (currentRoute._value.name === 'nlx.neos.detail') {
                this.config.neosLoginRoute = this.nlxRoutes.getNeosDetailRoute(neosBaseUri, queryParams);
            }

            if (currentRoute._value.name === 'nlx.neos.cbp') {
                this.config.neosLoginRoute = this.nlxRoutes.getNeosDetailRoute(neosBaseUri, queryParams);
            }
        },

        async loadNeosIntoIframe() {
            try {
                this.iframeSrc = await this.singleFlightLogin();
                this.isLoading = false;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('neos.loadNeosIntoIframe.loginError.title'),
                    message: this.$tc('neos.loadNeosIntoIframe.loginError.message', {
                        errorMessage: error.message
                    })
                });
                this.isLoading = false;
            }
        },

        async singleFlightLogin() {
            const MIN_INTERVAL_MS = 60000;
            const storageKey = 'nlxNeosLastLogin:' + this.config.neosLoginRoute;

            const doLogin = async () => {
                const salesChannel = await this.getSalesChannels.then(sc => sc.first());

                const abortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                const timeoutId = abortController ? setTimeout(() => abortController.abort(), DEFAULT_TIMEOUT_MS) : null;

                const fetchOptions = {
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
                };

                if (abortController) {
                    fetchOptions.signal = abortController.signal;
                }

                try {
                    const response = await fetch(this.config.neosLoginRoute, fetchOptions);
                    return (await response.json()).iframeUri;
                } finally {
                    if (timeoutId) {
                        clearTimeout(timeoutId);
                    }
                }
            };
            // doLogin() already bounds the fetch itself via AbortSignal; withTimeout additionally
            // covers the sales channel lookup ahead of it, which has no abort signal of its own.
            const doLoginWithRetry = () => this.nlxAsyncService.withRetry(
                () => this.nlxAsyncService.withTimeout(doLogin(), DEFAULT_TIMEOUT_MS * 2, 'Logging into Neos')
            );

            if (typeof navigator === 'undefined' || !navigator.locks || typeof navigator.locks.request !== 'function') {
                return doLoginWithRetry();
            }

            return navigator.locks.request('nlx-neos-login', async () => {
                let cached = null;
                try {
                    cached = JSON.parse(localStorage.getItem(storageKey) || 'null');
                } catch (e) {
                    cached = null;
                }

                if (cached && Date.now() - cached.timestamp < MIN_INTERVAL_MS) {
                    return cached.iframeUri;
                }
                const iframeUri = await doLoginWithRetry();
                try {
                    localStorage.setItem(storageKey, JSON.stringify({timestamp: Date.now(), iframeUri}));
                } catch (e) {
                    // ignore cache write errors
                }
                return iframeUri;
            });
        },

        async getDetailQueryParams() {
            const queryParams = [];
            queryParams.push(...[
                {key: 'swCmsPageId', value: this.cmsPageId},
                {key: 'swEntityId', value: this.entityId},
                {key: 'swEntityName', value: this.entityName},
                {key: 'nodeIdentifier', value: this.nodeIdentifier}
            ].filter(m => m.value));
            return queryParams;
        },

        triggerLoginModal(loginService) {
            loginService.logout(true, true);
        },

        redirectToSettings() {
            this.$router.push({
                name: 'nlx.neos.settings.index',
            })
        },

        toggleContentScroll(isInactive) {
            const contentEl = document.querySelector('.sw-desktop__content');
            if (!contentEl) return;
            contentEl.classList.toggle('nlx-neos-inactive-configuration', isInactive);
        }
    }
});

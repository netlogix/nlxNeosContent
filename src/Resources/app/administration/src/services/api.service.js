const {ApiService} = Shopware.Classes;

export default class NlxNeosContentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/neos') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'nlxNeosContentApiService';
    }

    updateNeosPages() {
        const apiRoute = `${this.getApiBasePath()}/update-neos-pages`;
        const data = {}
        return this.httpClient
            .post(
                apiRoute,
                data,
                {
                    headers: this.getBasicHeaders(),
                }
            ).catch((error) => {
                return {
                    success: false,
                    data: error.response.data ?? {message: 'Network error'}
                };
            }).then((response) => {
                if (response.status === 200) {
                    return {
                        success: true,
                        data: response.data
                    };
                }
                return {
                    success: false,
                    data: response.data ?? {message: 'Network error'}
                };
            });
    }

    clearNeosPageCaches(data = {}) {
        const apiRoute = `${this.getApiBasePath()}/clear-cache`;
        return this.httpClient
            .post(
                apiRoute,
                data,
                {
                    headers: this.getBasicHeaders(),
                }).catch((error) => {
                return {
                    success: false,
                    data: error.response.data ?? {message: 'Network error'}
                };
            }).then((response) => {
                if (response.status === 200) {
                    return {
                        success: true,
                        data: response.data
                    };
                }
                return {
                    success: false,
                    data: response.data ?? {message: 'Network error'}
                };
            });
    }

    getNeosToken() {
        const apiRoute = `${this.getApiBasePath()}/token`;
        return this.httpClient
            .get(
                apiRoute,
                {
                    headers: this.getBasicHeaders(),
                }
            ).catch((error) => {
                return {
                    success: false,
                    data: error.response.data ?? {message: 'Network error'}
                };
            }).then((response) => {
                if (response.status === 200) {
                    return {
                        success: true,
                        data: response.data
                    };
                }
                return {
                    success: false,
                    data: response.data ?? {message: 'Network error'}
                }
            });
    }

    previewSeoUrlTemplate(template, salesChannelId = null) {
        const apiRoute = `${this.getApiBasePath()}/seo-url-template/preview`;
        return this.httpClient
            .post(
                apiRoute,
                { template, salesChannelId },
                { headers: this.getBasicHeaders() }
            ).then((response) => {
                return { success: true, data: response.data };
            }).catch((error) => {
                return { success: false, data: error.response?.data ?? { message: 'Network error' } };
            });
    }

    getNeosPageTree() {
        const apiRoute = `${this.getApiBasePath()}/page-tree`;
        return this.toResult(this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }));
    }

    createNeosLayout(payload) {
        const apiRoute = `${this.getApiBasePath()}/layout`;
        return this.toResult(this.httpClient.post(apiRoute, payload, { headers: this.getBasicHeaders() }));
    }

    toResult(request) {
        return request.then((response) => {
            return { success: true, data: response.data };
        }).catch((error) => {
            return { success: false, data: error.response?.data ?? { message: 'Network error' } };
        });
    }
}

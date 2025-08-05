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
}

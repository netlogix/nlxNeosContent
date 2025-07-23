const { ApiService } = Shopware.Classes;

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
            )
            .then((response) => {
                return ApiService.handleResponse(response);
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
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

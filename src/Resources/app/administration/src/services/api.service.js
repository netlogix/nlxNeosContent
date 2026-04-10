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

    getNeosPageTree() {
        return this.proxyGetRequest('pagetree');
    }

    createNeosLayout(payload) {
        return this.proxyPostRequest('createPage', payload);
    }

    proxyGetRequest(action) {
        const apiRoute = `${this.getApiBasePath()}/request`;
        return this.httpClient
            .post(
                apiRoute,
                {
                    action: action,
                    method: 'GET'
                },
                {headers: this.getBasicHeaders()}
            ).catch((error) => {
                return {
                    success: false,
                    data: error.response.data ?? {message: 'Network error'}
                }
            }).then((response) => {
                if (response.status === 200) {
                    return {
                        success: true,
                        data: response.data
                    }
                } else {
                    return {
                        success: false,
                        data: {message: 'Invalid Status'}
                    }
                }
            });
    }

    proxyPostRequest(action, payload) {
        const apiRoute = `${this.getApiBasePath()}/request`;
        return this.httpClient
            .post(
                apiRoute,
                {
                    action: action,
                    method: 'POST',
                    payload: payload
                },
                {headers: this.getBasicHeaders()}
            ).catch((error) => {
                return {
                    success: false,
                    data: error.response.data ?? {message: 'Network error'}
                }
            }).then((response) => {
                if (response.status === 200) {
                    return {
                        success: true,
                        data: response.data
                    }
                } else {
                    return {
                        success: false,
                        data: {message: 'Invalid Status'}
                    }
                }
            });
    }
}

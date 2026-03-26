const ApiService = Shopware.Classes.ApiService;

export default class UrlTestService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nlx-url-test') {
        super(httpClient, loginService, apiEndpoint);
    }

    check(neosBaseUri) {
        return fetch(neosBaseUri + '/neos/shopware/availability/url-check', {
            method: 'GET',
        }).catch((error) => {
            return {
                success: false,
                data: error.response ? error.response.data : {message: 'Network error'}
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
                data: response.data
            };
        });
    }
}

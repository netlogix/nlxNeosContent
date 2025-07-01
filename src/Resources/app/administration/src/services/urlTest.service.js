const ApiService = Shopware.Classes.ApiService;
const {Application} = Shopware;

class ApiClient extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nlx-url-test') {
        super(httpClient, loginService, apiEndpoint);
    }

    check(values) {
        const headers = this.getBasicHeaders({});
        delete headers['sw-language-id'];
        headers['Accept'] = 'application/json';

        const neosBaseUri = values['null']['NlxNeosContent.config.neosBaseUri'];

        return this.httpClient
            .get(neosBaseUri + '/url-check', {
                headers: headers
            }).catch((error) => {
                return {
                    success: false,
                    data: error.response ? error.response.data : {message: 'Network error'}
                };
            })
            .then((response) => {
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

Application.addServiceProvider('nlxUrlTest', (container) => {
    const initContainer = Application.getContainer('init');
    return new ApiClient(initContainer.httpClient, container.loginService);
});

const ApiService = Shopware.Classes.ApiService;
const {Application} = Shopware;

class ApiClient extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'nlx-url-test') {
        super(httpClient, loginService, apiEndpoint);
    }

    check(values) {
        const neosBaseUri = values['null']['NetlogixNeosContent.config.neosBaseUri'];

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

Application.addServiceProvider('nlxUrlTest', (container) => {
    const initContainer = Application.getContainer('init');
    return new ApiClient(initContainer.httpClient, container.loginService);
});

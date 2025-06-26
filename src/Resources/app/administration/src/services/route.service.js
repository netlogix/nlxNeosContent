export default class RouteService {
    getNeosIndexRoute(baseUri) {
        return `${baseUri}/neos/shopware/login/`;
    }

    getNeosDetailRoute(baseUri, queryParams) {
        let uri = `${baseUri}/neos/shopware/login/`;
        queryParams.forEach((param, index) => {
            if (!param.value) {
                return;
            }
            if (index === 0) {
                uri += '?';
            } else {
                uri += '&';
            }
            uri += `${param.key}=${param.value}`;
        });
        return uri;
    }
}

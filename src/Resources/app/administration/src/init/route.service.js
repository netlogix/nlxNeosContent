import RouteService from '../services/route.service'

Shopware.Service().register('nlxRoutes', () => {
    return new RouteService();
});

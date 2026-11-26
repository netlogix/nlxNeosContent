/**
 * Plugin settings the administration needs. The plugin adds them to /api/_info/config
 * (AdminInfoConfigListener), which the administration loads before any plugin code runs,
 * so they can be read without the system_config:read privilege.
 */
export default class ConfigService {
    get config() {
        return Shopware.Context.app.config.nlxNeosContent ?? {};
    }

    getNeosBaseUri() {
        return this.config.neosBaseUri ?? null;
    }

    isNavigationExtensionEnabled() {
        return this.config.navigationExtensionEnabled ?? false;
    }
}

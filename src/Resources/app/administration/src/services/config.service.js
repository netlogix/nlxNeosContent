export default class ConfigService {
    systemConfigApiService;

    constructor() {
        this.systemConfigApiService = Shopware.ApiService.getByName('systemConfigApiService');
    }

    async getSetting(settingKey) {
        const settings = await this.systemConfigApiService.getValues('NlxNeosContent');
        return settings[`NlxNeosContent.settings.${settingKey}`];
    }

    async getConfig(configKey) {
        const configs = await this.systemConfigApiService.getValues('NlxNeosContent');
        return configs[`NlxNeosContent.config.${configKey}`];
    }
}

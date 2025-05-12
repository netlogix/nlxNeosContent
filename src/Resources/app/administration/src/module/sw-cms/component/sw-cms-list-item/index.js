import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

export default {
    template,

    computed: {
        isNeosPage() {
            if (!this.page) {
                return false;
            }

            return this.page.extensions?.nlxNeosNode?.nodeIdentifier !== undefined;
        },
    },
}

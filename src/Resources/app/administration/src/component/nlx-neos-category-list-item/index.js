import template from './nlx-neos-category-list-item.html.twig';
import './nlx-neos-category-list-item.scss';

export default {
    template,

    props: {
        pageTitle: {
            type: String,
            required: true,
        }
    }
}

const defaultSearchConfiguration = {
    _searchable: true,
    name: {
        _searchable: true,
        _score: 500,
    },
    tags: {
        name: {
            _searchable: true,
            _score: 500,
        },
    },
};

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;

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
export default defaultSearchConfiguration;

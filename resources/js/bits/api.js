export default {

};

function buildQuery(params = {}) {

    if (Object.keys(params).length === 0) {
        return '';
    }

    return Object.entries(params)
        .map(([key, val]) => `${encodeURIComponent(key)}=${encodeURIComponent(val)}`)
        .join('&')
    ;
}
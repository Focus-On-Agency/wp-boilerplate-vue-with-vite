const request = async function(method, route, data = {}, pluginScoped = true) {

    // Is false make domain.com/wp-json
    const url = pluginScoped
        ? `${window.PluginClassName.rest.url}/${route}`
        : `${window.PluginClassName.rest.root}${route}`;
    ;

    const headers = {
        'X-WP-Nonce': window.PluginClassName.rest.nonce,
    };

    let hasBody = method.toUpperCase() !== 'GET';

    if (['PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }
    if (hasBody) headers['Content-Type'] = 'application/json';

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);

    const options = {
        method,
        headers,
        signal: controller.signal,
        credentials: 'same-origin',
    };

    if (hasBody) options.body = JSON.stringify(data);

    try {
        const response = await fetch(url, options);
        clearTimeout(timeout);
        
        if (response.status === 204) {
            return null;
        }

        const responseBody = await response.text();
        let parsedBody;
        try {
            parsedBody = JSON.parse(responseBody);
        } catch (e) {
            parsedBody = responseBody;
        }

        if (!response.ok) {
            const error = new Error(parsedBody?.message || `HTTP error! Status: ${response.status}`);
            error.status = response.status;
            error.body = parsedBody;
            throw error;
        }

        return parsedBody;

    } catch (error) {
        clearTimeout(timeout);
        if (error.name === 'AbortError') {
            console.error('Request timeout:', url);
            const timeoutError = new Error('Request timed out');
            timeoutError.status = 408;
            timeoutError.body = { message: 'Request timed out' };
            throw timeoutError;
        }

        console.error('Request failed:', error);
        throw error;
    }
};

export default {
    get(route, data = {}, pluginScoped = true) {
        return request('GET', route, data, pluginScoped);
    },
    post(route, data = {}, pluginScoped = true) {
        return request('POST', route, data, pluginScoped);
    },
    delete(route, data = {}, pluginScoped = true) {
        return request('DELETE', route, data, pluginScoped);
    },
    put(route, data = {}, pluginScoped = true) {
        return request('PUT', route, data, pluginScoped);
    },
    patch(route, data = {}, pluginScoped = true) {
        return request('PATCH', route, data, pluginScoped);
    }
};
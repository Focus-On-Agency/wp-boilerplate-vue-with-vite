import {
    applyFilters,
    addFilter,
    addAction,
    doAction,
    removeAllActions
} from '@wordpress/hooks';
import ajax from '../bits/AJAX';

export default {
    install: (app) => {
        const appVars = window.PluginClassNameAdmin;

        const utils = {
            appVars,
            applyFilters,
            addFilter,
            addAction,
            doAction,
            removeAllActions,
            $get: (url, options = {}) => ajax.get(url, options),
            $post: (url, options = {}) => ajax.post(url, options),
            $del: (url, options = {}) => ajax.delete(url, options),
            $put: (url, options = {}) => ajax.put(url, options),
            $patch: (url, options = {}) => ajax.patch(url, options),
            saveData(key, data) {
                let existingData = window.localStorage.getItem('__pluginlowercase_data');
                existingData = existingData ? JSON.parse(existingData) : {};
                existingData[key] = data;
                window.localStorage.setItem('__pluginlowercase_data', JSON.stringify(existingData));
            },
            getData(key, defaultValue = false) {
                let existingData = window.localStorage.getItem('__pluginlowercase_data');
                existingData = existingData ? JSON.parse(existingData) : {};
                return existingData[key] || defaultValue;
            },
            ucFirst(text) {
                return text[0].toUpperCase() + text.slice(1).toLowerCase();
            },
            ucWords(text) {
                return (text + '').replace(/^(.)|\s+(.)/g, ($1) => $1.toUpperCase());
            },
            slugify(text) {
                return text.toString().toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            },
            $handleError(response) {
                if (response.responseJSON) {
                    response = response.responseJSON;
                }
                let errorMessage = '';
                if (typeof response === 'string') {
                    errorMessage = response;
                } else if (response && response.message) {
                    errorMessage = response.message;
                } else {
                    errorMessage = this.convertToText(response);
                }
                if (!errorMessage) {
                    errorMessage = 'Something is wrong!';
                }
                const toast = app.config.globalProperties.$toast;
                if (toast) {
                    toast.add({
                        severity: 'error',
                        summary: 'Errore',
                        detail: errorMessage,
                        life: 4000
                    });
                } else {
                    console.error('Error:', errorMessage);
                }
            },
            convertToText(obj) {
                const string = [];
                if (typeof (obj) === 'object' && (obj.join === undefined)) {
                    for (const prop in obj) {
                        string.push(utils.convertToText(obj[prop]));
                    }
                } else if (typeof (obj) === 'object') {
                    for (const prop in obj) {
                        string.push(utils.convertToText(obj[prop]));
                    }
                } else if (typeof (obj) === 'string') {
                    string.push(obj);
                }
                return string.join('<br />');
            }
        };

        app.provide('app', utils);
    }
};
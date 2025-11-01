const { addFilter, applyFilters, removeAllActions, doAction, addAction  } = wp.hooks;
const { __, _x, _n, _nx } = wp.i18n;
import ajax from '@/bits/AJAX';

export default {
    install: (app) => {
        const appVars = window.PluginClassNameAdmin;

        const utils = {
            appVars,
            __,
            _x,
            _n,
            _nx,
            applyFilters,
            addFilter,
            addAction,
            doAction,
            removeAllActions,
            $get: (url, data = {}, pluginScoped = true) => ajax.get(url, data, pluginScoped),
            $post: (url, data = {}, pluginScoped = true) => ajax.post(url, data, pluginScoped),
            $del: (url, data = {}, pluginScoped = true) => ajax.delete(url, data, pluginScoped),
            $put: (url, data = {}, pluginScoped = true) => ajax.put(url, data, pluginScoped),
            $patch: (url, data = {}, pluginScoped = true) => ajax.patch(url, data, pluginScoped),
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
            delData(key) {
                let existingData = window.localStorage.getItem('__pluginlowercase_data');
                existingData = existingData ? JSON.parse(existingData) : {};
                delete existingData[key];
                window.localStorage.setItem('__pluginlowercase_data', JSON.stringify(existingData));
            },
            getNestedValue(obj, path) {
                return path.split('.').reduce((acc, key) => acc?.[key], obj);
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
            },
            /**
             * Formatta una data in base al formato specificato (stile PHP-like).
             *
             * @param {string|Date} input - La data da formattare, come oggetto Date o stringa ISO.
             * @param {string} format - Il formato desiderato, supporta i seguenti valori:
             *   Y - anno a 4 cifre (es: 2025)
             *   y - anno a 2 cifre (es: 25)
             *   m - mese con zero iniziale (01-12)
             *   n - mese senza zero iniziale (1-12)
             *   d - giorno con zero iniziale (01-31)
             *   j - giorno senza zero iniziale (1-31)
             *   H - ore con zero iniziale (00-23)
             *   i - minuti con zero iniziale (00-59)
             *   s - secondi con zero iniziale (00-59)
             *   M - nome breve del mese (es: gen, feb, mar) [locale it-IT]
             *   F - nome completo del mese (es: gennaio, febbraio) [locale it-IT]
             *
             * @returns {string} - La data formattata o stringa vuota se non valida.
             */
            formatDate(input, format = 'Y-m-d', timezone = 'Europe/Rome', lang = 'it-IT') {
                const date = new Date(input);
                if (isNaN(date)) return '';
            
                const pad = (n) => n.toString().padStart(2, '0');
            
                const getParts = new Intl.DateTimeFormat(lang, {
                    timeZone: timezone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }).formatToParts(date).reduce((acc, part) => {
                    if (part.type !== 'literal') acc[part.type] = part.value;
                    return acc;
                }, {});
            
                const map = {
                    Y: getParts.year,
                    y: getParts.year?.slice(-2),
                    m: getParts.month,
                    n: parseInt(getParts.month),
                    d: getParts.day,
                    j: parseInt(getParts.day),
                    H: getParts.hour,
                    i: getParts.minute,
                    s: getParts.second,
                    M: new Intl.DateTimeFormat(lang, { month: 'short', timeZone: timezone }).format(date),
                    F: new Intl.DateTimeFormat(lang, { month: 'long', timeZone: timezone }).format(date),
                };
            
                return format.replace(/Y|y|m|n|d|j|H|i|s|M|F/g, (match) => map[match] || match);
            },            
            formatTime(input, format = 'H:i') {
                let date;
            
                // Se input è una stringa tipo "21:40:00", costruiamo una data fittizia
                if (typeof input === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(input)) {
                    // Aggiungiamo i secondi se mancano
                    const [h, m, s = '00'] = input.split(':');
                    date = new Date();
                    date.setHours(parseInt(h), parseInt(m), parseInt(s), 0);
                } else {
                    date = new Date(input);
                }
            
                if (isNaN(date.getTime())) return '';
            
                const pad = (n) => n.toString().padStart(2, '0');
            
                const map = {
                    H: pad(date.getHours()),
                    i: pad(date.getMinutes()),
                    s: pad(date.getSeconds()),
                };
            
                return format.replace(/H|i|s/g, (match) => map[match] || match);
            },
            getNowInTimezone(timezone = 'Europe/Rome', lang = 'it-IT') {
                const now = new Date();

                const parts = new Intl.DateTimeFormat(lang, {
                    timeZone: timezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hourCycle: "h23",
                }).formatToParts(now);

                const map = Object.fromEntries(parts.map(p => [p.type, p.value]));

                return new Date(
                    `${map.year}-${map.month}-${map.day}T${map.hour}:${map.minute}:${map.second}`
                );
            },
            formatCurrency(input, currency = 'EUR') {
                if (input === null || input === undefined || input === '') {
                    return '—';
                }

                const value = Number(input);

                if (Number.isNaN(value)) {
                    return '—';
                }

                return new Intl.NumberFormat('it-IT', {
                    style: 'currency',
                    currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(value);
            },
        };

        app.provide('app', utils);
        app.provide('i18n', { __, _x, _n, _nx });
    }
};
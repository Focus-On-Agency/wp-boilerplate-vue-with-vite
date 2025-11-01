import { inject } from 'vue';
import { useToast } from 'primevue/usetoast';

export const useApp = () => {
    const app = inject('app');
    const i18n = inject('i18n');
    const toast = useToast();

    if (!app || !i18n) {
        throw new Error('App or i18n not provided');
    }

    const $handleError = (response, life = 4000) => {
        if (response.responseJSON) {
            response = response.responseJSON;
        }

        if (response instanceof Error && response.body) {
            response = response.body;
        }

        let errorMessage = '';
        if (typeof response === 'string') {
            errorMessage = response;
        } else if (response && response.message) {
            errorMessage = response.message;
        } else {
            errorMessage = app.convertToText(response);
        }
        if (!errorMessage) {
            errorMessage = 'Something is wrong!';
        }

        toast.add({
            severity: 'error',
            summary: 'Errore',
            detail: errorMessage,
            life: life,
        });

        console.error('Error:', errorMessage);
    };

    const $handleToast = (summary, message, type = 'success', life = 4000) => {
        toast.add({
            severity: type,
            summary: summary,
            detail: message,
            life: life,
        });
    }

    return {
        ...app,
        ...i18n,
        $handleError,
        $handleToast,
    };
};
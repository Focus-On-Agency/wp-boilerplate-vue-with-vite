import { inject } from 'vue';

export const useApp = () => {
    const app = inject('app');
    if (!app) {
        throw new Error('App not provided');
    }
    return app;
};
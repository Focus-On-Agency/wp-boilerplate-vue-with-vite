import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import '../../../css/tailwind.css';

const app = createApp({});

app.use(PrimeVue, {
    unstyled: true
});

app.use(ToastService);

export default app;

import routes from '@frontend/routes';
import { createWebHashHistory, createRouter } from 'vue-router';
import app from '@/bits/elements';
import AppPlugin from '@/plugins/AppPlugin';

const router = createRouter({
	history: createWebHashHistory(),
	routes
});

window.PluginClassNameApp = pluginlowercaseFrontend;

app.use(AppPlugin);
app.use(router);

window.PluginClassNameApp = app.mount('#pluginlowercase_app');

router.afterEach((to, from) => {
	document.querySelectorAll('.pluginlowercase_menu_item').forEach(el => el.classList.remove('active'));
	
	let active = to.meta.active;
	if (active) {
		let activeElement = document.querySelector(`.pluginlowercase_main-menu-items li[data-key="${active}"]`);
		if (activeElement) {
			activeElement.classList.add('active');
		}
	}
});
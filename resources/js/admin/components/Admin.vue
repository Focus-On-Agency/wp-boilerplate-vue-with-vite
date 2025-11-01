<template>
	<div>
		<Dashboard/>
	</div>
</template>
<script type="module">

import Dashboard from './Dashboard.vue';
import Toast from 'primevue/toast';
export default {
	name: "admin-layout",
	components: {
		Dashboard
	},
};
</script>

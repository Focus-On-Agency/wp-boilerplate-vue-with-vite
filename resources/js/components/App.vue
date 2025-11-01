<template>
	<router-view />
	<Toast
		position="bottom-right"
	/>

	<ConfirmDialog></ConfirmDialog>
</template>

<script setup>
	import ConfirmDialog from 'primevue/confirmdialog';
    import Toast from 'primevue/toast';
</script>
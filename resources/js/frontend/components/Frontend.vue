<template>
	<div v-if="ready" class="w-full">
		
	</div>
</template>

<script setup>
	import { computed, onMounted, ref } from 'vue';
	import { useApp } from '@/composables/useApp';
	import api from '@/bits/api';

	const { appVars, $get, saveData, $handleError } = useApp();
	const type = computed(() => appVars?.varShortcode?.type ?? null)
	const ready = ref(false);

	onMounted(async () => {
		//
	});
</script>
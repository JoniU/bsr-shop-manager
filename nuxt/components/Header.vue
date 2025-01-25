<template>
    <header class="bg-gray-100 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-700">
        <div class="flex items-center justify-between w-full">

            <div class="flex items-center space-x-4 pl-2 pb-2">
                <UButton color="secondary" :to="'/wp-admin/'" size="xl" variant="soft" icon="i-dashicons-wordpress"
                    class="p-1" />
            </div>

            <!-- Tabs (aligned to the left) -->
            <UTabs :items="pages" v-model="selectedPage" size="md" variant="link" class="flex-1"
                @update:model-value="switchPage" />

            <!-- Theme Switch (aligned to the right) -->
            <div class="flex items-center space-x-2 pr-2">
                <UIcon :name="isDark ? 'i-lucide-moon' : 'i-lucide-sun'" />
                <USwitch :model-value="isDark" @update:model-value="toggleTheme" color="primary" size="sm" />
            </div>

        </div>
    </header>
</template>

<script setup>
import { useAppStore } from '~/stores/app'; // Import the Pinia store

const appStore = useAppStore(); // Access the store

const pages = ref([
    /*{
        label: 'Analytics',
        value: 'Analytics',
        icon: 'i-lucide-bar-chart', // Suitable icon for analytics
    }, */
    {
        label: 'Editor',
        value: 'Editor',
        icon: 'i-lucide-edit', // Icon for editing
    },
    {
        label: 'Inventory',
        value: 'Inventory',
        icon: 'i-lucide-box', // Icon for inventory
    },
    {
        label: 'Settings',
        value: 'Settings',
        icon: 'i-lucide-settings', // Icon for settings
    },
    {
        label: 'Order Data',
        value: 'OrderData',
        icon: 'i-lucide-settings', // Icon for settings
    },
]);

const selectedPage = ref(appStore.currentPage);

// Update the active section in the store
function switchPage(newSection) {
    appStore.setCurrentPage(newSection);
}

// Theme toggling
const isDark = computed(() => appStore.theme === 'dark'); // Check if the current theme is dark

function toggleTheme(newValue) {
    const theme = newValue ? 'dark' : 'light';
    appStore.setTheme(theme); // Update the theme in the store
}
</script>
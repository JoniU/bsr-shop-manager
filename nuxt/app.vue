<template>
    <div id="bsr-shop-manager">
        <div :class="{ dark: isDark }" class="app min-h-full">
            <UApp>
                <Header />
                <main>
                    <component :is="currentComponent" />
                </main>
            </UApp>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAppStore } from '~/stores/app';
import Header from '~/components/Header.vue';
import Analytics from '~/components/Analytics.vue';
import Editor from '~/components/Editor.vue';
import Inventory from '~/components/Inventory.vue';
import Settings from '~/components/Settings.vue';

const appStore = useAppStore();

appStore.initializeTheme();

const isDark = computed(() => appStore.theme === 'dark');

const currentComponent = computed(() => components[appStore.currentPage]);
const components = {
    Analytics,
    Editor,
    Inventory,
    Settings,
};

</script>
<style lang="css" scoped></style>
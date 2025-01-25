import { defineStore } from 'pinia';
import { getLocalStorage, setLocalStorage } from '~/utils/localStorage';

// Define the state type
interface AppState {
    currentPage: string; // Currently active page
    theme: 'light' | 'dark'; // Theme preference
}

export const useAppStore = defineStore('app', {
    state: (): AppState => ({
        currentPage: getLocalStorage('currentPage', 'Editor'), // Safely fetch the value
        theme: getLocalStorage('theme', 'dark'), // Safely fetch the theme
    }),

    actions: {
        setCurrentPage(page: string) {
            this.currentPage = page;
            setLocalStorage('currentPage', page);
        },

        setTheme(theme: 'light' | 'dark') {
            this.theme = theme;
            setLocalStorage('theme', theme);
            document.documentElement.classList.toggle('dark', theme === 'dark');
        },

        initializeTheme() {
            this.setTheme(this.theme);
        },
    },
});
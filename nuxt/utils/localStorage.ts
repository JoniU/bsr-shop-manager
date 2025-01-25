export function getLocalStorage<T>(key: string, defaultValue: T): T {
    try {
        const value = localStorage.getItem(key);
        return value ? (JSON.parse(value) as T) : defaultValue;
    } catch (error) {
        console.warn(`Error parsing localStorage key "${key}":`, error);
        return defaultValue;
    }
}

export function setLocalStorage<T>(key: string, value: T) {
    localStorage.setItem(key, JSON.stringify(value));
}
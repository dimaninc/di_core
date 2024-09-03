function get(key: string): Nullable<string> {
    try {
        return localStorage.getItem(key);
    } catch (err) {
        console.error(`Error getting ${key} from localStorage`, err);

        return null;
    }
}

function set(key: string, value: string) {
    try {
        localStorage.setItem(key, value);
    } catch (err) {
        console.error(`Error setting ${key} in localStorage`, err);
    }
}

function remove(key: string) {
    try {
        localStorage.removeItem(key);
    } catch (err) {
        console.error(`Error removing ${key} from localStorage`, err);
    }
}

export const LocalStorageHelper = {
    get,
    set,
    remove,
};

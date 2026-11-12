
import { API_URL } from "./env";

const originalFetch = window.fetch.bind(window);

window.fetch = (input, init = {}) => {
    // Jika input berupa URL string absolut, gunakan langsung
    if (
        typeof input === "string" &&
        /^https?:\/\//i.test(input)
    ) {
        return originalFetch(input, init);
    }

    // Jika input berupa Request object, jangan mengubahnya
    if (input instanceof Request) {
        return originalFetch(input, init);
    }

    // Gabungkan API_URL dengan endpoint relatif
    const baseURL = API_URL.endsWith("/")
        ? API_URL
        : `${API_URL}/`;

    const endpoint = String(input).replace(/^\/+/, "");

    const url = new URL(endpoint, baseURL).toString();

    return originalFetch(url, init);
};

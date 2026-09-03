// src/config/env.js

const host = window.location.hostname;

let API_URL;
let SOCKET_URL;

if (host === "localhost" || host === "127.0.0.1") {
    // Local development
    API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000";
    SOCKET_URL = import.meta.env.VITE_SOCKET_URL || "http://localhost:3001";
} else if (host.endsWith("github.io")) {
    // GitHub Pages
    API_URL = import.meta.env.VITE_API_URL;
    SOCKET_URL = import.meta.env.VITE_SOCKET_URL;
} else {
    // Akses dari PC/device lain di jaringan LAN
    API_URL = `http://${host}:8000`;
    SOCKET_URL = `http://${host}:3001`;
}

export { API_URL, SOCKET_URL };
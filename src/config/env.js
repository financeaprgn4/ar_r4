// src/config/env.js

const host = window.location.hostname;

let API_URL;
let SOCKET_URL;

// Jika dibuka dari HP / device lain (LAN)
if (host !== "localhost" && host !== "127.0.0.1") {
  API_URL = `http://${host}:8000`;
  SOCKET_URL = `http://${host}:3001`;
} else {
  API_URL = import.meta.env.VITE_API_URL;
  SOCKET_URL = import.meta.env.VITE_SOCKET_URL;
}

export { API_URL, SOCKET_URL };

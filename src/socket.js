import { io } from "socket.io-client";

export const socket = io(import.meta.env.VITE_SOCKET_URL, {
  transports: ["websocket"],   // 🔥 WAJIB
  autoConnect: false,
  reconnection: true,
  reconnectionAttempts: 5,
  reconnectionDelay: 1000,
});

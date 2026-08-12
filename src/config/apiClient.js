// src/config/apiClient.js
import { API_URL } from "./env";

export const apiFetch = (endpoint, options = {}) => {
  return fetch(`${API_URL}${endpoint}`, {
    headers: {
      "Accept": "application/json",
      ...(options.headers || {}),
    },
    ...options,
  });
};

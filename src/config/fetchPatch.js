import { API_URL } from "./env";

const originalFetch = window.fetch;

window.fetch = (input, init = {}) => {
  // jika sudah absolute, biarkan
  if (typeof input === "string" && input.startsWith("http")) {
    return originalFetch(input, init);
  }

  return originalFetch(`${API_URL}${input}`, init);
};

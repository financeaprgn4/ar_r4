import { API_URL } from './env.js';

export const createSSE = (path) => {
  const url = `${API_URL}${path}`;
  console.log('[SSE CONNECT]', url);
  return new EventSource(url);
};

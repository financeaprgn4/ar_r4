import { API_URL } from "../config/env";

export const openDownload = (path) => {
  window.open(`${API_URL}${path}`, '_blank');
};

import axios from "axios";
import { API_URL } from "./env";

const axiosInstance = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: "application/json",
  },
});

export default axiosInstance;

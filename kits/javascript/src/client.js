import axios from "axios";
import { ApiError, ConduitexError } from "./errors.js";

const DEFAULT_BASE_URL = "https://api.conduitex.com";
const DEFAULT_API_VERSION = "v1";
const trimSlashes = (value) => value.replace(/^\/+|\/+$/g, "");

const resolveBaseUrl = () => {
  const envBase = process.env.CONDUITEX_BASE_URL;
  const chosen = envBase ?? DEFAULT_BASE_URL;

  if (!chosen) {
    throw new ConduitexError("CONDUITEX_BASE_URL is required.");
  }

  return chosen.replace(/\/+$/, "");
};

export class ConduitexResponse {
  constructor(response) {
    this.status = response.status;
    this.headers = response.headers;
    this.data = response.data;
    this._raw = response;
  }

  get raw() {
    return this._raw;
  }
}

export class ConduitexClient {
  constructor({ baseUrl, vaultKey, apiVersion = DEFAULT_API_VERSION, timeout = 30000 } = {}) {
    if (baseUrl) {
      throw new ConduitexError("Base URL must be configured via CONDUITEX_BASE_URL.");
    }

    const resolvedBaseUrl = resolveBaseUrl();

    if (!vaultKey) {
      throw new ConduitexError("vaultKey is required");
    }

    this.baseUrl = resolvedBaseUrl;
    this.apiVersion = trimSlashes(apiVersion);

    this.http = axios.create({
      baseURL: `${resolvedBaseUrl}/api/${this.apiVersion}`,
      timeout,
      headers: {
        "X-Vault-Key": vaultKey,
        Accept: "application/json",
      },
    });
  }

  buildPath(serviceSlug, path = "") {
    const cleanService = trimSlashes(serviceSlug);
    const cleanPath = trimSlashes(path || "");
    return cleanPath ? `/proxy/${cleanService}/${cleanPath}` : `/proxy/${cleanService}`;
  }

  async request({
    serviceSlug,
    path = "",
    method = "GET",
    params,
    data,
    headers = {},
    idempotencyKey,
    signal,
    timeout,
  } = {}) {
    if (!serviceSlug) {
      throw new ConduitexError("serviceSlug is required");
    }

    const headerBag = { ...headers };

    if (idempotencyKey) {
      headerBag["Idempotency-Key"] = idempotencyKey;
    }

    try {
      const response = await this.http.request({
        url: this.buildPath(serviceSlug, path),
        method,
        params,
        data,
        headers: headerBag,
        signal,
        timeout,
      });

      return new ConduitexResponse(response);
    } catch (error) {
      if (error.response) {
        const { status, data: payload } = error.response;
        const message =
          (payload && payload.message) || `Request failed with status ${status}`;
        throw new ApiError(status, payload, message);
      }

      throw new ConduitexError(error.message);
    }
  }

  get(serviceSlug, path = "", options = {}) {
    return this.request({ serviceSlug, path, method: "GET", ...options });
  }

  post(serviceSlug, path = "", options = {}) {
    return this.request({ serviceSlug, path, method: "POST", ...options });
  }

  put(serviceSlug, path = "", options = {}) {
    return this.request({ serviceSlug, path, method: "PUT", ...options });
  }

  patch(serviceSlug, path = "", options = {}) {
    return this.request({ serviceSlug, path, method: "PATCH", ...options });
  }

  delete(serviceSlug, path = "", options = {}) {
    return this.request({ serviceSlug, path, method: "DELETE", ...options });
  }
}

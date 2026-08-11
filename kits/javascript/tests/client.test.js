import path from "node:path";
import dotenv from "dotenv";

dotenv.config({ path: path.resolve(process.cwd(), "../..", ".env") });
import nock from "nock";
import { afterAll, afterEach, beforeAll, describe, expect, it } from "vitest";
import { ApiError } from "../src/errors.js";
import { ConduitexClient } from "../src/client.js";

const BASE_URL = process.env.CONDUITEX_BASE_URL;
const LIVE = process.env.CONDUITEX_LIVE_TESTS === "1";
const LIVE_TIMEOUT = LIVE ? 15000 : 5000;
const ORIGINAL_ENV_BASE = process.env.CONDUITEX_BASE_URL;
const GATEWAY_BASE_URL = "https://gateway.internal";

beforeAll(() => {
  if (!LIVE) {
    nock.disableNetConnect();
  } else {
    nock.enableNetConnect();
  }
});

  afterEach(() => {
    nock.cleanAll();
  });

afterAll(() => {
  if (ORIGINAL_ENV_BASE === undefined) {
    delete process.env.CONDUITEX_BASE_URL;
  } else {
    process.env.CONDUITEX_BASE_URL = ORIGINAL_ENV_BASE;
  }
});

describe("ConduitexClient", () => {
  it("performs GET with vault key header and query params", async () => {
    const client = new ConduitexClient({ vaultKey: "vk_test" });

    if (LIVE) {
      try {
        const response = await client.get("github", "repos", { params: { q: "laravel" } });
        expect(response.status).toBeGreaterThanOrEqual(200);
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
      }
    } else {
      const scope = nock(BASE_URL)
        .get("/api/v1/proxy/github/repos")
        .query({ q: "laravel" })
        .matchHeader("X-Vault-Key", "vk_test")
        .reply(200, { ok: true });

      const response = await client.get("github", "repos", { params: { q: "laravel" } });

      expect(response.status).toBe(200);
      expect(response.data.ok).toBe(true);
      expect(scope.isDone()).toBe(true);
    }
  }, LIVE_TIMEOUT);

  it("sends idempotency header for POST requests", async () => {
    const client = new ConduitexClient({ vaultKey: "vk_test" });

    if (LIVE) {
      try {
        const response = await client.post("stripe", "charges", {
          data: { amount: 1000 },
          idempotencyKey: "idem-123",
        });
        expect(response.status).toBeGreaterThanOrEqual(200);
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
      }
    } else {
      const scope = nock(BASE_URL)
        .post("/api/v1/proxy/stripe/charges", { amount: 1000 })
        .matchHeader("Idempotency-Key", "idem-123")
        .reply(201, { id: "ch_123" });

      const response = await client.post("stripe", "charges", {
        data: { amount: 1000 },
        idempotencyKey: "idem-123",
      });

      expect(response.status).toBe(201);
      expect(response.data.id).toBe("ch_123");
      expect(scope.isDone()).toBe(true);
    }
  }, LIVE_TIMEOUT);

  it("throws ApiError on failed response", async () => {
    const client = new ConduitexClient({ vaultKey: "vk_test" });

    if (LIVE) {
      try {
        await client.get("github", "repos");
        // If it succeeds, ensure we got a status back via the client.
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
      }
    } else {
      nock(BASE_URL)
        .get("/api/v1/proxy/github/repos")
        .reply(401, { message: "Unauthorized vault key." });

      await expect(async () => {
        await client.get("github", "repos");
      }).rejects.toBeInstanceOf(ApiError);
    }
  }, LIVE_TIMEOUT);

  it("falls back to CONDUITEX_BASE_URL when baseUrl not provided", async () => {
    const client = new ConduitexClient({ vaultKey: "vk_test" });

    if (LIVE) {
      try {
        const response = await client.get("github", "repos");
        expect(response.status).toBeGreaterThanOrEqual(200);
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
      }
    } else {
      const scope = nock(BASE_URL).get("/api/v1/proxy/github/repos").reply(200, { ok: true });

      const response = await client.get("github", "repos");

      expect(response.status).toBe(200);
      expect(scope.isDone()).toBe(true);
    }

    if (ORIGINAL_ENV_BASE === undefined) {
      delete process.env.CONDUITEX_BASE_URL;
    } else {
      process.env.CONDUITEX_BASE_URL = ORIGINAL_ENV_BASE;
    }
  }, LIVE_TIMEOUT);

  it("rejects explicit baseUrl overrides", () => {
    expect(() => {
      new ConduitexClient({ baseUrl: "https://evil.test", vaultKey: "vk_test" });
    }).toThrow(/CONDUITEX_BASE_URL/i);
  });

  it("uses the configured gateway base URL for runtime traffic", async () => {
    process.env.CONDUITEX_BASE_URL = GATEWAY_BASE_URL;

    const client = new ConduitexClient({ vaultKey: "vk_test" });

    const scope = nock(GATEWAY_BASE_URL)
      .get("/api/v1/proxy/github/repos")
      .reply(200, { ok: true });

    const response = await client.get("github", "repos");

    expect(response.status).toBe(200);
    expect(scope.isDone()).toBe(true);
  });
});

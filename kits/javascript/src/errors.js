export class ConduitexError extends Error {
  constructor(message) {
    super(message);
    this.name = "ConduitexError";
  }
}

export class ApiError extends ConduitexError {
  constructor(status, data, message) {
    const friendly = message ?? `Request failed with status ${status}`;
    super(friendly);
    this.name = "ApiError";
    this.status = status;
    this.data = data;
  }
}

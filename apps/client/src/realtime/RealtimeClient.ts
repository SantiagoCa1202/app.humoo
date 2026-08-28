import { buildApiUrl } from "@/api/config";
import { runtimeConfig } from "@/config/runtime";
import type { RealtimeChange, RealtimeListener, RealtimeStatus } from "@/realtime/types";

type StatusListener = (status: RealtimeStatus) => void;

type PusherMessage = {
  channel?: string;
  data?: string | Record<string, unknown>;
  event?: string;
};

export class RealtimeClient {
  private socket: WebSocket | null = null;
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private reconnectAttempt = 0;
  private shouldReconnect = false;
  private token: string | null = null;
  private workspaceId: string | null = null;
  private listener: RealtimeListener | null = null;
  private statusListener: StatusListener | null = null;

  subscribe(
    token: string,
    workspaceId: string,
    listener: RealtimeListener,
    statusListener?: StatusListener,
  ): void {
    this.disconnect();
    this.token = token;
    this.workspaceId = workspaceId;
    this.listener = listener;
    this.statusListener = statusListener ?? null;
    this.shouldReconnect = true;
    this.connect();
  }

  disconnect(): void {
    this.shouldReconnect = false;
    this.clearReconnectTimer();
    this.socket?.close();
    this.socket = null;
    this.token = null;
    this.workspaceId = null;
    this.listener = null;
    this.statusListener?.("disconnected");
    this.statusListener = null;
  }

  pause(): void {
    this.shouldReconnect = false;
    this.clearReconnectTimer();
    this.socket?.close();
    this.socket = null;
    this.statusListener?.("disconnected");
  }

  resume(): void {
    if (this.token && this.workspaceId && this.listener && !this.socket) {
      this.shouldReconnect = true;
      this.connect();
    }
  }

  private connect(): void {
    if (!runtimeConfig.realtimeUrl || !runtimeConfig.realtimeKey || !this.workspaceId) {
      this.statusListener?.("disabled");
      return;
    }

    this.statusListener?.(this.reconnectAttempt > 0 ? "reconnecting" : "connecting");
    const separator = runtimeConfig.realtimeUrl.includes("?") ? "&" : "?";
    const url = `${runtimeConfig.realtimeUrl}${separator}protocol=7&client=humoo&version=1.0&flash=false`;
    const socket = new WebSocket(url);
    this.socket = socket;

    socket.onopen = () => {
      this.reconnectAttempt = 0;
    };
    socket.onmessage = (event) => {
      void this.handleMessage(socket, event.data).catch(() => {
        this.statusListener?.("error");
        socket.close();
      });
    };
    socket.onerror = () => {
      this.statusListener?.("error");
    };
    socket.onclose = () => {
      if (this.socket === socket) {
        this.socket = null;
      }

      if (this.shouldReconnect) {
        this.scheduleReconnect();
      }
    };
  }

  private async handleMessage(socket: WebSocket, raw: string): Promise<void> {
    let message: PusherMessage;

    try {
      message = JSON.parse(raw) as PusherMessage;
    } catch {
      return;
    }

    if (message.event === "pusher:connection_established") {
      await this.authorizeAndSubscribe(socket);
      return;
    }

    if (message.event === "pusher:subscription_succeeded") {
      this.statusListener?.("connected");
      return;
    }

    if (message.event !== "workspace.changed") {
      return;
    }

    const payload = typeof message.data === "string"
      ? this.parsePayload(message.data)
      : message.data ?? null;

    if (this.isChange(payload)) {
      this.listener?.(payload);
    }
  }

  private async authorizeAndSubscribe(socket: WebSocket): Promise<void> {
    if (!this.token || !this.workspaceId || socket !== this.socket) {
      return;
    }

    const channel = `private-workspace.${this.workspaceId}`;
    const response = await fetch(runtimeConfig.realtimeAuthUrl || buildApiUrl("/broadcasting/auth"), {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${this.token}`,
        "Content-Type": "application/json; charset=UTF-8",
        "X-Workspace-ID": this.workspaceId,
      },
      body: JSON.stringify({ channel_name: channel }),
    });

    if (!response.ok || socket !== this.socket) {
      throw new Error("Realtime channel authorization failed.");
    }

    const authorization = (await response.json()) as { auth?: string };

    if (!authorization.auth) {
      throw new Error("Realtime channel authorization is incomplete.");
    }

    socket.send(JSON.stringify({
      auth: authorization.auth,
      channel,
      event: "pusher:subscribe",
    }));
  }

  private scheduleReconnect(): void {
    if (this.reconnectTimer || !this.shouldReconnect) {
      return;
    }

    const delay = Math.min(30_000, 1_000 * 2 ** Math.min(this.reconnectAttempt, 5));
    this.reconnectAttempt += 1;
    this.reconnectTimer = setTimeout(() => {
      this.reconnectTimer = null;
      this.connect();
    }, delay);
  }

  private clearReconnectTimer(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }

  private parsePayload(raw: string): Record<string, unknown> | null {
    try {
      return JSON.parse(raw) as Record<string, unknown>;
    } catch {
      return null;
    }
  }

  private isChange(value: Record<string, unknown> | null): value is RealtimeChange {
    return Boolean(
      value &&
        typeof value.type === "string" &&
        typeof value.workspaceId === "string" &&
        typeof value.entityType === "string" &&
        typeof value.entityId === "string",
    );
  }
}

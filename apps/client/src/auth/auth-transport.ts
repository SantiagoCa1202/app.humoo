import {
  clearAuthCredential as clearStoredAuthCredential,
  readAuthCredential,
  writeAuthCredential,
  type StoredAuthCredential,
} from "@/auth/auth-storage";
import type { ApiRequestContext } from "@/api/types";

type AuthTransportEvent =
  | {
      credential: StoredAuthCredential;
      type: "authenticated" | "credential-restored";
    }
  | {
      context?: ApiRequestContext;
      reason?: string;
      type: "session-expired" | "unauthenticated";
    };

type AuthTransportListener = (event: AuthTransportEvent) => void;

let activeCredential: StoredAuthCredential | null = null;
let hasHydratedCredential = false;
let hasEmittedSessionExpiry = false;

const listeners = new Set<AuthTransportListener>();

export function subscribeAuthTransport(listener: AuthTransportListener) {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

export function getAuthCredential(): StoredAuthCredential | null {
  return activeCredential;
}

export function hasAuthCredentialHydrated(): boolean {
  return hasHydratedCredential;
}

export async function hydrateAuthCredential(): Promise<StoredAuthCredential | null> {
  if (hasHydratedCredential) {
    return activeCredential;
  }

  activeCredential = await readAuthCredential();
  hasHydratedCredential = true;

  if (activeCredential) {
    emit({
      type: "credential-restored",
      credential: activeCredential,
    });
  }

  return activeCredential;
}

export async function setAuthCredential(
  credential: StoredAuthCredential
): Promise<void> {
  activeCredential = credential;
  hasHydratedCredential = true;
  hasEmittedSessionExpiry = false;
  await writeAuthCredential(credential);
  emit({
    type: "authenticated",
    credential,
  });
}

export async function clearAuthCredential(
  reason: "session-expired" | "unauthenticated" = "unauthenticated",
  context?: ApiRequestContext
): Promise<void> {
  activeCredential = null;
  hasHydratedCredential = true;
  await clearStoredAuthCredential();

  emit({
    type: reason,
    context,
  });
}

export function notifySessionExpired(context: ApiRequestContext) {
  if (hasEmittedSessionExpiry) {
    return;
  }

  hasEmittedSessionExpiry = true;

  void clearAuthCredential("session-expired", context);
}

function emit(event: AuthTransportEvent) {
  for (const listener of listeners) {
    listener(event);
  }
}

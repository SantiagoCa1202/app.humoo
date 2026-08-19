import { setAuthCredential, hydrateAuthCredential } from "@/auth/auth-transport";
import {
  clearAuthCredential,
  clearSessionSnapshot,
  readSessionSnapshot,
  writeSessionSnapshot,
} from "@/auth/auth-storage";
import type { AppSession } from "@/auth/types";

export async function readSession(): Promise<AppSession | null> {
  const storedSession = await readSessionSnapshot();

  if (!storedSession) {
    return null;
  }

  if (storedSession.mode !== "api") {
    return storedSession;
  }

  const credential = await hydrateAuthCredential();

  if (!credential?.token) {
    await clearSessionSnapshot();
    return null;
  }

  return {
    ...storedSession,
    token: credential.token,
    createdAt: credential.createdAt || storedSession.createdAt,
  };
}

export async function writeSession(session: AppSession): Promise<void> {
  if (session.mode === "api" && session.token) {
    await setAuthCredential({
      createdAt: session.createdAt,
      token: session.token,
      type: "bearer",
    });
  } else {
    await clearAuthCredential();
  }

  await writeSessionSnapshot({
    ...session,
    token: null,
  });
}

export async function clearSession(): Promise<void> {
  await Promise.all([clearAuthCredential(), clearSessionSnapshot()]);
}

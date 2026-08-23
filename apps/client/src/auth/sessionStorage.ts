import { setAuthCredential } from "@/auth/auth-transport";
import {
  clearAuthCredential,
  clearSessionSnapshot,
  writeSessionSnapshot,
} from "@/auth/auth-storage";
import type { AppSession } from "@/auth/types";

export async function writeSession(session: AppSession): Promise<void> {
  if (session.token) {
    await setAuthCredential({
      createdAt: session.createdAt,
      token: session.token,
      type: "bearer",
    });
    await writeSessionSnapshot(session);
  } else {
    await clearAuthCredential();
    await clearSessionSnapshot();
  }
}

export async function clearSession(): Promise<void> {
  await clearAuthCredential();
  await clearSessionSnapshot();
}

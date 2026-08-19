import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

import type { AppSession } from "@/auth/types";

const AUTH_CREDENTIAL_KEY = "humoo.auth.credential";
const SESSION_SNAPSHOT_KEY = "humoo.session.snapshot";

export type StoredAuthCredential = {
  createdAt: string;
  token: string;
  type: "bearer";
};

type SessionSnapshot = AppSession;

export async function readAuthCredential(): Promise<StoredAuthCredential | null> {
  const raw = await readValue(AUTH_CREDENTIAL_KEY);

  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as Partial<StoredAuthCredential>;

    if (
      typeof parsed.token !== "string" ||
      !parsed.token.trim() ||
      typeof parsed.createdAt !== "string" ||
      !parsed.createdAt.trim()
    ) {
      await clearAuthCredential();
      return null;
    }

    return {
      createdAt: parsed.createdAt,
      token: parsed.token,
      type: "bearer",
    };
  } catch {
    await clearAuthCredential();
    return null;
  }
}

export async function writeAuthCredential(
  credential: StoredAuthCredential
): Promise<void> {
  await writeValue(AUTH_CREDENTIAL_KEY, JSON.stringify(credential), true);
}

export async function clearAuthCredential(): Promise<void> {
  await deleteValue(AUTH_CREDENTIAL_KEY, true);
}

export async function readSessionSnapshot(): Promise<SessionSnapshot | null> {
  const raw = await readValue(SESSION_SNAPSHOT_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as SessionSnapshot;
  } catch {
    await clearSessionSnapshot();
    return null;
  }
}

export async function writeSessionSnapshot(
  session: SessionSnapshot
): Promise<void> {
  await writeValue(SESSION_SNAPSHOT_KEY, JSON.stringify(session), false);
}

export async function clearSessionSnapshot(): Promise<void> {
  await deleteValue(SESSION_SNAPSHOT_KEY, false);
}

async function readValue(
  key: string,
  preferSecureStore = false
): Promise<string | null> {
  if (Platform.OS === "web") {
    return AsyncStorage.getItem(key);
  }

  if (preferSecureStore) {
    return SecureStore.getItemAsync(key);
  }

  return AsyncStorage.getItem(key);
}

async function writeValue(
  key: string,
  value: string,
  preferSecureStore = false
): Promise<void> {
  if (Platform.OS === "web") {
    await AsyncStorage.setItem(key, value);
    return;
  }

  if (preferSecureStore) {
    await SecureStore.setItemAsync(key, value);
    return;
  }

  await AsyncStorage.setItem(key, value);
}

async function deleteValue(
  key: string,
  preferSecureStore = false
): Promise<void> {
  if (Platform.OS === "web") {
    await AsyncStorage.removeItem(key);
    return;
  }

  if (preferSecureStore) {
    await SecureStore.deleteItemAsync(key);
    return;
  }

  await AsyncStorage.removeItem(key);
}

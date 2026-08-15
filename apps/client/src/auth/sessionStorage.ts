import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

import type { AppSession } from "@/auth/types";

const SESSION_KEY = "humoo.session";

export async function readSession(): Promise<AppSession | null> {
  const raw = await readValue();
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as AppSession;
  } catch {
    await clearSession();
    return null;
  }
}

export async function writeSession(session: AppSession): Promise<void> {
  const raw = JSON.stringify(session);

  if (Platform.OS === "web") {
    await AsyncStorage.setItem(SESSION_KEY, raw);
    return;
  }

  await SecureStore.setItemAsync(SESSION_KEY, raw);
}

export async function clearSession(): Promise<void> {
  if (Platform.OS === "web") {
    await AsyncStorage.removeItem(SESSION_KEY);
    return;
  }

  await SecureStore.deleteItemAsync(SESSION_KEY);
}

async function readValue(): Promise<string | null> {
  if (Platform.OS === "web") {
    return AsyncStorage.getItem(SESSION_KEY);
  }

  return SecureStore.getItemAsync(SESSION_KEY);
}

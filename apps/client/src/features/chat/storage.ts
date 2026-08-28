import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

const ACTIVE_CONVERSATION_PREFIX = "humoo.chat.active-conversation.";

function storageKey(workspaceId: string) {
  return `${ACTIVE_CONVERSATION_PREFIX}${workspaceId}`;
}

export async function readActiveConversationId(
  workspaceId: string,
): Promise<string | null> {
  const key = storageKey(workspaceId);

  if (Platform.OS === "web") {
    return globalThis.localStorage?.getItem(key) ?? null;
  }

  return SecureStore.getItemAsync(key);
}

export async function writeActiveConversationId(
  workspaceId: string,
  conversationId: string | null,
): Promise<void> {
  const key = storageKey(workspaceId);

  if (Platform.OS === "web") {
    if (conversationId) {
      globalThis.localStorage?.setItem(key, conversationId);
    } else {
      globalThis.localStorage?.removeItem(key);
    }
    return;
  }

  if (conversationId) {
    await SecureStore.setItemAsync(key, conversationId);
  } else {
    await SecureStore.deleteItemAsync(key);
  }
}

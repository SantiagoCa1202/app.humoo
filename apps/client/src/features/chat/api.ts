import { apiRequest } from "@/api/client";
import { coerceEventRecord } from "@/features/events/api";
import { coercePrepListRecord, coercePrepProgressRecord } from "@/features/prep/api";
import { coerceTaskRecord } from "@/features/tasks";

import type {
  ChatAssistantResponseRecord,
  ChatConfirmationRecord,
  ChatComponentBlockRecord,
  ChatConversationSummaryRecord,
  ChatConversationRecord,
  ChatMessageBlockRecord,
  ChatMessageRecord,
  ChatToolActionResponse,
  ChatToolMetadataRecord,
  ExecuteChatComponentActionInput,
  SendChatMessageInput,
  SendChatMessageResult,
} from "@/features/chat/types";

type ApiConversationResponse = {
  data?: {
    conversation?: unknown;
  };
};

type ApiConversationHistoryResponse = {
  data?: {
    conversations?: unknown;
  };
};

type ApiSendMessageResponse = {
  data?: {
    assistant_response?: unknown;
    conversation?: {
      id?: string | null;
      last_message_at?: string | null;
    } | null;
    user_message?: unknown;
  };
};

type ApiToolActionResponse = {
  data?: unknown;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" ? (value as Record<string, unknown>) : null;
}

function readArray(value: unknown): unknown[] {
  return Array.isArray(value) ? value : [];
}

function readNumber(value: unknown): number | null {
  return typeof value === "number" && Number.isFinite(value) ? value : null;
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

function readStringArray(value: unknown): string[] {
  return readArray(value)
    .map(readString)
    .filter((item): item is string => Boolean(item));
}

function mapBlock(value: unknown): ChatMessageBlockRecord | null {
  const record = asRecord(value);

  if (!record) {
    return null;
  }

  const type = readString(record.type);

  if (!type) {
    return null;
  }

  if (type === "component") {
    const component = readString(record.component);
    const schemaVersion = readNumber(record.schema_version) ?? 1;

    if (!component) {
      return null;
    }

    return {
      actions: readArray(record.actions),
      component,
      data: record.data,
      id: readString(record.id),
      instanceId: readString(record.instance_id),
      meta: asRecord(record.meta),
      registryKey:
        readString(record.registry_key) ?? `${component}@${schemaVersion}`,
      schemaVersion,
      type: "component",
    } satisfies ChatComponentBlockRecord;
  }

  return {
    data: record.data,
    id: readString(record.id),
    meta: asRecord(record.meta),
    text: readString(record.text),
    type: type === "error" || type === "status" ? type : "text",
  };
}

function mapMessage(value: unknown): ChatMessageRecord | null {
  const record = asRecord(value);
  const id = readString(record?.id);
  const senderType = readString(record?.sender_type);

  if (!record || !id || !senderType) {
    return null;
  }

  return {
    blocks: readArray(record.blocks)
      .map(mapBlock)
      .filter((block): block is ChatMessageBlockRecord => Boolean(block)),
    clientMessageId: readString(record.client_message_id),
    contentText: readString(record.content_text),
    conversationId: readString(record.conversation_id),
    createdAt: readString(record.created_at),
    errorCode: readString(record.error_code),
    errorMessage: readString(record.error_message),
    id,
    locale: readString(record.locale),
    parentMessageId: readString(record.parent_message_id),
    senderType:
      senderType === "assistant" ||
      senderType === "system" ||
      senderType === "tool"
        ? senderType
        : "user",
    status: readString(record.status),
    suggestions: readStringArray(record.suggestions),
    updatedAt: readString(record.updated_at),
  };
}

function mapConversation(value: unknown): ChatConversationRecord | null {
  const record = asRecord(value);
  const id = readString(record?.id);

  if (!record || !id) {
    return null;
  }

  return {
    createdAt: readString(record.created_at),
    id,
    lastMessageAt: readString(record.last_message_at),
    messages: readArray(record.messages)
      .map(mapMessage)
      .filter((message): message is ChatMessageRecord => Boolean(message)),
    scopeId: readString(record.scope_id),
    scopeType: readString(record.scope_type),
    status: readString(record.status),
    title: readString(record.title),
    updatedAt: readString(record.updated_at),
    visibility: readString(record.visibility),
  };
}

function mapConversationSummary(
  value: unknown,
): ChatConversationSummaryRecord | null {
  const record = asRecord(value);
  const id = readString(record?.id);

  if (!record || !id) {
    return null;
  }

  return {
    createdAt: readString(record.created_at),
    id,
    lastMessageAt: readString(record.last_message_at),
    messageCount: readNumber(record.message_count) ?? 0,
    preview: readString(record.preview),
    title: readString(record.title),
  };
}

function mapAssistantResponse(value: unknown): ChatAssistantResponseRecord | null {
  const record = asRecord(value);

  if (!record) {
    return null;
  }

  return {
    blocks: readArray(record.blocks)
      .map(mapBlock)
      .filter((block): block is ChatMessageBlockRecord => Boolean(block)),
    conversationId: readString(record.conversation_id),
    messageId: readString(record.message_id),
    suggestions: readStringArray(record.suggestions),
  };
}

function mapToolMetadata(value: unknown): ChatToolMetadataRecord | null {
  const record = asRecord(value);

  if (!record) {
    return null;
  }

  return {
    actionId: readString(record.action_id),
    component: readString(record.component),
    description: readString(record.description),
    entityType: readString(record.entity_type),
    key: readString(record.key),
    mode:
      readString(record.mode) === "read" || readString(record.mode) === "write"
        ? (readString(record.mode) as "read" | "write")
        : null,
    permission: readString(record.permission),
    requiresConfirmation:
      typeof record.requires_confirmation === "boolean"
        ? record.requires_confirmation
        : null,
    schemaVersion: readNumber(record.schema_version),
  };
}

function mapConfirmation(value: unknown): ChatConfirmationRecord | null {
  const record = asRecord(value);

  if (!record) {
    return null;
  }

  return {
    expiresAt: readString(record.expires_at),
    id: readString(record.id),
    status: readString(record.status),
    token: readString(record.token),
  };
}

function mapToolActionResponse(value: unknown): ChatToolActionResponse | null {
  const record = asRecord(value);

  if (!record) {
    return null;
  }

  return {
    assistantResponse: mapAssistantResponse(record.assistant_response),
    blocks: readArray(record.blocks)
      .map(mapBlock)
      .filter((block): block is ChatMessageBlockRecord => Boolean(block)),
    confirmation: mapConfirmation(record.confirmation),
    conversationId: readString(asRecord(record.conversation)?.id),
    conversationLastMessageAt: readString(asRecord(record.conversation)?.last_message_at),
    tool: mapToolMetadata(record.tool),
  };
}

export function assistantResponseToMessage(
  response: ChatAssistantResponseRecord,
  createdAt?: string | null,
): ChatMessageRecord {
  const firstTextBlock = response.blocks.find(
    (block) => block.type === "text" && typeof block.text === "string"
  );
  const contentText =
    firstTextBlock &&
    "text" in firstTextBlock &&
    typeof firstTextBlock.text === "string"
      ? firstTextBlock.text
      : null;

  return {
    blocks: response.blocks,
    contentText,
    conversationId: response.conversationId ?? null,
    createdAt: createdAt ?? null,
    id: response.messageId ?? createChatClientMessageId("assistant"),
    senderType: "assistant",
    status: "completed",
    suggestions: response.suggestions,
  };
}

export function createChatClientMessageId(prefix = "mobile") {
  const randomValue =
    globalThis.crypto && "randomUUID" in globalThis.crypto
      ? globalThis.crypto.randomUUID()
      : `${Date.now()}-${Math.round(Math.random() * 1_000_000)}`;

  return `${prefix}-${randomValue}`;
}

export async function getChatConversation(
  authToken: string,
  workspaceId: string,
  conversationId?: string | null,
): Promise<ChatConversationRecord> {
  const response = await apiRequest<ApiConversationResponse>("/chat", {
    authToken,
    query: conversationId ? { conversation_id: conversationId } : undefined,
    workspaceId,
  });
  const conversation = mapConversation(response.data?.conversation);

  if (!conversation) {
    throw new Error("Chat conversation response is invalid.");
  }

  return conversation;
}

export async function getChatHistory(
  authToken: string,
  workspaceId: string,
): Promise<ChatConversationSummaryRecord[]> {
  const response = await apiRequest<ApiConversationHistoryResponse>(
    "/chat/conversations",
    {
      authToken,
      workspaceId,
    },
  );

  return readArray(response.data?.conversations)
    .map(mapConversationSummary)
    .filter(
      (conversation): conversation is ChatConversationSummaryRecord =>
        Boolean(conversation),
    );
}

export async function deleteChatConversation(
  authToken: string,
  workspaceId: string,
  conversationId: string,
): Promise<void> {
  await apiRequest<null>(
    `/chat/conversations/${encodeURIComponent(conversationId)}`,
    {
      authToken,
      method: "DELETE",
      workspaceId,
    },
  );
}

export async function sendChatMessage(
  authToken: string,
  workspaceId: string,
  input: SendChatMessageInput
): Promise<SendChatMessageResult> {
  const response = await apiRequest<ApiSendMessageResponse>("/chat/messages", {
    authToken,
    body: JSON.stringify({
      client_message_id: input.clientMessageId ?? null,
      content: input.content.trim(),
      conversation_id: input.conversationId ?? null,
      locale: input.locale ?? null,
    }),
    method: "POST",
    workspaceId,
    timeoutMs: 60000,
  });
  const assistantResponse = mapAssistantResponse(response.data?.assistant_response);
  const userMessage = mapMessage(response.data?.user_message);

  if (!assistantResponse || !userMessage) {
    throw new Error("Chat send response is invalid.");
  }

  return {
    assistantResponse,
    conversationId: readString(response.data?.conversation?.id),
    conversationLastMessageAt: readString(response.data?.conversation?.last_message_at),
    userMessage,
  };
}

export async function executeChatComponentAction(
  authToken: string,
  workspaceId: string,
  input: ExecuteChatComponentActionInput
): Promise<ChatToolActionResponse> {
  const response = await apiRequest<ApiToolActionResponse>("/chat/actions", {
    authToken,
    body: JSON.stringify({
      action_id: input.actionId,
      component_instance_id: input.componentInstanceId,
      entity: input.entity ?? null,
      idempotency_key: input.idempotencyKey ?? null,
      input: input.input ?? null,
    }),
    method: "POST",
    workspaceId,
  });
  const result = mapToolActionResponse(response.data);

  if (!result) {
    throw new Error("Chat component action response is invalid.");
  }

  return result;
}

export async function confirmChatAction(
  authToken: string,
  workspaceId: string,
  token: string,
  input?: Record<string, unknown> | null,
  idempotencyKey?: string | null
): Promise<ChatToolActionResponse> {
  const response = await apiRequest<ApiToolActionResponse>(
    `/confirmations/${encodeURIComponent(token)}/confirm`,
    {
      authToken,
      body: input || idempotencyKey
        ? JSON.stringify({
            ...(input ? { input } : {}),
            ...(idempotencyKey ? { idempotency_key: idempotencyKey } : {}),
          })
        : undefined,
      method: "POST",
      workspaceId,
    }
  );
  const result = mapToolActionResponse(response.data);

  if (!result) {
    throw new Error("Chat confirmation response is invalid.");
  }

  return result;
}

export async function cancelChatAction(
  authToken: string,
  workspaceId: string,
  token: string
): Promise<ChatToolActionResponse> {
  const response = await apiRequest<ApiToolActionResponse>(
    `/confirmations/${encodeURIComponent(token)}/cancel`,
    {
      authToken,
      method: "POST",
      workspaceId,
    }
  );
  const result = mapToolActionResponse(response.data);

  if (!result) {
    throw new Error("Chat cancellation response is invalid.");
  }

  return result;
}

export function coerceChatEventRecords(value: unknown) {
  return readArray(value)
    .map(coerceEventRecord)
    .filter((item): item is NonNullable<typeof item> => Boolean(item));
}

export function coerceChatPrepEntries(value: unknown) {
  return readArray(value)
    .map((entry) => {
      const record = asRecord(entry);

      if (!record) {
        return null;
      }

      const prepList = coercePrepListRecord(record.prep_list);

      if (!prepList) {
        return null;
      }

      return {
        prepList,
        progress: coercePrepProgressRecord(record.progress),
      };
    })
    .filter((item): item is NonNullable<typeof item> => Boolean(item));
}

export function coerceChatTaskRecords(value: unknown) {
  return readArray(value)
    .map(coerceTaskRecord)
    .filter((item): item is NonNullable<typeof item> => Boolean(item));
}

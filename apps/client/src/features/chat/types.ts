export type ChatComponentRegistryKey =
  | "action.preview@1"
  | "action.confirm@1"
  | "action.result@1"
  | "clarification.options@1"
  | "clarification.options@2"
  | "error.recovery@1"
  | "events.list@1"
  | "events.summary@1"
  | "clients.list@1"
  | "clients.detail@1"
  | "contacts.list@1"
  | "contacts.detail@1"
  | "venues.list@1"
  | "venues.detail@1"
  | "inventory.missing@1"
  | "menus.detail@1"
  | "menus.list@1"
  | "recipes.list@1"
  | "recipes.detail@1"
  | "recipes.scaled@1"
  | "recipe.draft@1"
  | "advisory.result@1"
  | "prep.list@1"
  | "prep.detail@1"
  | "prep.preview@1"
  | "prep.weekly-board@1"
  | "tasks.mine@1"
  | "teams.list@1"
  | "teams.detail@1"
  | "stations.list@1"
  | "stations.detail@1"
  | "shifts.list@1"
  | "shifts.detail@1"
  | "availability.list@1";

export type ChatMessageBlockType = "component" | "error" | "status" | "text";

export type ChatTextBlockRecord = {
  data?: unknown;
  id?: string | null;
  meta?: Record<string, unknown> | null;
  text?: string | null;
  type: Exclude<ChatMessageBlockType, "component">;
};

export type ChatComponentBlockRecord = {
  actions?: unknown[];
  component: string;
  data?: unknown;
  id?: string | null;
  instanceId?: string | null;
  meta?: Record<string, unknown> | null;
  registryKey: ChatComponentRegistryKey | (string & {});
  schemaVersion: number;
  type: "component";
};

export type ChatMessageBlockRecord = ChatComponentBlockRecord | ChatTextBlockRecord;

export type ChatMessageRecord = {
  blocks: ChatMessageBlockRecord[];
  clientMessageId?: string | null;
  contentText?: string | null;
  conversationId?: string | null;
  createdAt?: string | null;
  errorCode?: string | null;
  errorMessage?: string | null;
  id: string;
  locale?: string | null;
  parentMessageId?: string | null;
  senderType: "assistant" | "system" | "tool" | "user";
  status?: string | null;
  suggestions: string[];
  updatedAt?: string | null;
};

export type ChatConversationRecord = {
  createdAt?: string | null;
  id: string;
  lastMessageAt?: string | null;
  messages: ChatMessageRecord[];
  scopeId?: string | null;
  scopeType?: string | null;
  status?: string | null;
  title?: string | null;
  updatedAt?: string | null;
  visibility?: string | null;
};

export type ChatConversationSummaryRecord = {
  createdAt?: string | null;
  id: string;
  lastMessageAt?: string | null;
  messageCount: number;
  preview?: string | null;
  title?: string | null;
};

export type ChatAssistantResponseRecord = {
  blocks: ChatMessageBlockRecord[];
  conversationId?: string | null;
  messageId?: string | null;
  suggestions: string[];
};

export type SendChatMessageInput = {
  clientMessageId?: string | null;
  content: string;
  conversationId?: string | null;
  locale?: string | null;
};

export type SendChatMessageResult = {
  assistantResponse: ChatAssistantResponseRecord;
  conversationId?: string | null;
  conversationLastMessageAt?: string | null;
  userMessage: ChatMessageRecord;
};

export type ChatToolMetadataRecord = {
  actionId?: string | null;
  component?: string | null;
  description?: string | null;
  entityType?: string | null;
  key?: string | null;
  mode?: "read" | "write" | null;
  permission?: string | null;
  requiresConfirmation?: boolean | null;
  schemaVersion?: number | null;
};

export type ChatConfirmationRecord = {
  expiresAt?: string | null;
  id?: string | null;
  status?: string | null;
  token?: string | null;
};

export type ExecuteChatComponentActionInput = {
  actionId: string;
  componentInstanceId: string;
  entity?: {
    id?: string | null;
    type?: string | null;
    version?: number | null;
  } | null;
  idempotencyKey?: string | null;
  input?: Record<string, unknown> | null;
};

export type ChatToolActionResponse = {
  assistantResponse?: ChatAssistantResponseRecord | null;
  blocks: ChatMessageBlockRecord[];
  confirmation?: ChatConfirmationRecord | null;
  conversationId?: string | null;
  conversationLastMessageAt?: string | null;
  tool?: ChatToolMetadataRecord | null;
};

import { router, type Href } from "expo-router";
import { useState } from "react";
import { View } from "react-native";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { ActionResultCard } from "@/components/patterns/action-result-card";
import { AlertCard } from "@/components/patterns/alert-card";
import {
  ClarificationCard,
  type ClarificationOption,
} from "@/components/patterns/clarification-card";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { ErrorRecoveryCard } from "@/components/patterns/error-recovery-card";
import { EventSummaryCard } from "@/components/patterns/event-summary-card";
import { MyTasksCard } from "@/components/patterns/my-tasks-card";
import { PrepSummaryCard } from "@/components/patterns/prep-summary-card";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import {
  cancelChatAction,
  confirmChatAction,
  coerceChatEventRecords,
  coerceChatPrepEntries,
  coerceChatTaskRecords,
} from "@/features/chat/api";
import { applyAssistantResponseToConversation, chatKeys } from "@/features/chat/hooks";
import type {
  ChatComponentBlockRecord,
  ChatConversationRecord,
  ChatComponentRegistryKey,
} from "@/features/chat/types";
import { commandCenterKeys } from "@/features/home/queryKeys";
import { prepKeys } from "@/features/prep/hooks";
import { taskKeys } from "@/features/tasks/hooks";
import { menuKeys } from "@/features/menus";
import { useWorkspace } from "@/features/workspace";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";

type ChatRemoteComponentProps = {
  block: ChatComponentBlockRecord;
  disabled?: boolean;
  onSendSuggestion?: (value: string) => void;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" ? (value as Record<string, unknown>) : null;
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

function readBoolean(value: unknown): boolean {
  return value === true;
}

function readOptions(value: unknown): ClarificationOption[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.reduce<ClarificationOption[]>((options, item) => {
      const record = asRecord(item);
      const id = readString(record?.id);
      const label = readString(record?.label);

      if (!record || !id || !label) {
        return options;
      }

      options.push({
        description: readString(record.description) ?? undefined,
        id,
        label,
        value: readString(record.value) ?? undefined,
      });

      return options;
    }, []);
}

function readStringItems(value: unknown) {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => {
      if (typeof item === "string" || typeof item === "number") {
        return String(item);
      }

      const record = asRecord(item);

      return readString(record?.name) ?? readString(record?.label) ?? null;
    })
    .filter((item): item is string => Boolean(item));
}

function RemoteCardFrame({
  children,
  description,
  title,
}: {
  children: React.ReactNode;
  description?: string | null;
  title?: string | null;
}) {
  return (
    <BaseCard padding="md" radius="lg" variant="elevated">
      <CardHeader
        padding="none"
        subtitle={description ?? undefined}
        title={title ?? undefined}
      />
      <CardContent padding="none" topDivider>
        {children}
      </CardContent>
    </BaseCard>
  );
}

function ClarificationOptionsRenderer({
  block,
  disabled = false,
  onSendSuggestion,
}: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const options = readOptions(record?.options);
  const selectionMode =
    readString(record?.selection_mode) === "single" ? "single" : "immediate";
  const [selected, setSelected] = useState<string | undefined>();

  return (
    <ClarificationCard
      description={readString(record?.description) ?? undefined}
      disabled={disabled}
      onSelect={(option) => {
        setSelected(option.value ?? option.id);

        if (selectionMode === "immediate") {
          onSendSuggestion?.(option.value ?? option.label);
        }
      }}
      onSubmit={
        selectionMode === "single"
          ? (option) => {
              if (option) {
                onSendSuggestion?.(option.value ?? option.label);
              }
            }
          : undefined
      }
      options={options}
      selected={selected}
      selectionMode={selectionMode}
      title={readString(record?.title) ?? undefined}
    />
  );
}

function EventsListRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const events = coerceChatEventRecords(record?.events);
  const title = readString(record?.title);
  const description = readString(record?.description);
  const { theme } = useAppTheme();

  return (
    <RemoteCardFrame description={description} title={title}>
      <View style={{ gap: theme.spacing[3] }}>
        {events.length ? (
          events.map((event) => (
            <EventSummaryCard
              compact
              event={event}
              key={event.id ?? `${event.name}-${event.startsAt}`}
            />
          ))
        ) : (
          <Text tone="secondary" variant="bodySmall">
            No hay eventos disponibles en este bloque.
          </Text>
        )}
      </View>
    </RemoteCardFrame>
  );
}

function EventsSummaryRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const events = coerceChatEventRecords(record?.event ? [record.event] : []);
  const event = events[0];

  if (!event) {
    return <UnsupportedComponentRenderer block={block} />;
  }

  return <EventSummaryCard event={event} />;
}

function PrepListRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const entries = coerceChatPrepEntries(record?.items);
  const title = readString(record?.title);
  const description = readString(record?.description);
  const { theme } = useAppTheme();

  return (
    <RemoteCardFrame description={description} title={title}>
      <View style={{ gap: theme.spacing[3] }}>
        {entries.length ? (
          entries.map((entry) => (
            <PrepSummaryCard
              compact
              key={entry.prepList.id ?? `${entry.prepList.name}-${entry.prepList.createdAt}`}
              prepList={entry.prepList}
              progress={entry.progress}
            />
          ))
        ) : (
          <Text tone="secondary" variant="bodySmall">
            No hay listas activas en este bloque.
          </Text>
        )}
      </View>
    </RemoteCardFrame>
  );
}

function PrepPreviewRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);

  return (
    <ActionPreviewCard
      changes={Array.isArray(record?.changes) ? (record?.changes as never[]) : []}
      description={readString(record?.description) ?? undefined}
      destructive={readBoolean(record?.destructive)}
      impact={readString(record?.impact) ?? undefined}
      metadata={Array.isArray(record?.metadata) ? (record?.metadata as never[]) : []}
      title={readString(record?.title) ?? undefined}
      type={readString(record?.type) ?? undefined}
    />
  );
}

function WeeklyBoardRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const items = readStringItems(record?.items);

  return (
    <AlertCard
      description={items.length ? items.join(" • ") : "No hay pasos semanales disponibles."}
      title={readString(record?.title) ?? "Tablero semanal de prep"}
      tone="info"
    />
  );
}

function ActionConfirmRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const confirmationToken = readString(record?.confirmation_token);
  const { t } = useTranslation("common");
  const { session } = useAuth();
  const { activeMembership, activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const [resolved, setResolved] = useState<"cancelled" | "confirmed" | null>(null);
  const [errorState, setErrorState] = useState<string | null>(null);
  const workspaceId = activeWorkspace?.id ?? null;
  const membershipId = activeMembership?.id ?? null;
  const mutation = useMutation({
    mutationFn: async (mode: "cancel" | "confirm") => {
      if (!session?.token || !workspaceId || !confirmationToken) {
        throw new Error("Missing confirmation context.");
      }

      return mode === "confirm"
        ? confirmChatAction(session.token, workspaceId, confirmationToken)
        : cancelChatAction(session.token, workspaceId, confirmationToken);
    },
    onSuccess: async (result, mode) => {
      setErrorState(null);
      setResolved(mode === "confirm" ? "confirmed" : "cancelled");

      if (workspaceId && result.assistantResponse) {
        queryClient.setQueryData<ChatConversationRecord | undefined>(
          chatKeys.workspace(workspaceId),
          (current) =>
            applyAssistantResponseToConversation(
              current,
              result.assistantResponse!,
              result.conversationId,
              result.conversationLastMessageAt
            )
        );
      }

      if (!workspaceId || mode !== "confirm") {
        return;
      }

      const toolKey = result.tool?.key ?? null;
      const invalidations: Promise<unknown>[] = [];

      if (toolKey === "tasks.update") {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: taskKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );

        if (membershipId) {
          invalidations.push(
            queryClient.invalidateQueries({ queryKey: taskKeys.mine(workspaceId, membershipId) })
          );
        }
      }

      if (toolKey === "prep_items.update") {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: prepKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey === "menus.create") {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: menuKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (invalidations.length > 0) {
        await Promise.all(invalidations);
      }
    },
    onError: (error) => {
      const message =
        error instanceof Error && error.message.trim()
          ? error.message
          : "No pude completar la confirmacion.";

      setErrorState(message);
    },
  });

  if (resolved) {
    return (
      <ActionResultCard
        description={
          resolved === "confirmed"
            ? t("chat.operations.flow.confirmedDescription")
            : t("chat.operations.flow.cancelledDescription")
        }
        status={resolved === "confirmed" ? "success" : "partial"}
        title={
          resolved === "confirmed"
            ? t("chat.operations.flow.confirmedTitle")
            : t("chat.operations.flow.cancelledTitle")
        }
      />
    );
  }

  return (
    <View style={{ gap: 12 }}>
      <ConfirmationCard
        description={readString(record?.description) ?? undefined}
        destructive={readBoolean(record?.destructive)}
        details={Array.isArray(record?.details) ? (record?.details as never[]) : []}
        disabled={!confirmationToken}
        loading={mutation.isPending}
        onCancel={() => mutation.mutate("cancel")}
        onConfirm={() => mutation.mutate("confirm")}
        title={readString(record?.title) ?? undefined}
      />
      {errorState ? (
        <ErrorRecoveryCard
          safeDetail={errorState}
          title={t("chat.operations.flow.confirmationFailedTitle")}
        />
      ) : null}
    </View>
  );
}

function ActionResultRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const status = readString(record?.status);

  return (
    <ActionResultCard
      description={readString(record?.description) ?? undefined}
      details={Array.isArray(record?.details) ? (record?.details as never[]) : []}
      status={status === "failure" || status === "partial" ? status : "success"}
      title={readString(record?.title) ?? undefined}
    />
  );
}

function TasksMineRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const tasks = coerceChatTaskRecords(record?.tasks);

  return (
    <MyTasksCard
      maxItems={4}
      onItemPress={(task) => {
        if (!task.id) {
          return;
        }

        router.push({
          pathname: routes.app.taskDetail,
          params: { taskId: task.id },
        } as Href);
      }}
      onViewAllPress={() => router.push(routes.app.myTasks)}
      tasks={tasks}
      title={readString(record?.title) ?? undefined}
    />
  );
}

function InventoryMissingRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const items = readStringItems(record?.items);

  return (
    <AlertCard
      description={
        items.length
          ? items.join(" • ")
          : "Todavía no hay ingredientes faltantes estructurados para este bloque."
      }
      title={readString(record?.title) ?? "Ingredientes faltantes"}
      tone="warning"
    />
  );
}

function ErrorRecoveryRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);

  return (
    <ErrorRecoveryCard
      description={readString(record?.description) ?? undefined}
      errorCode={readString(record?.error_code) ?? undefined}
      safeDetail={readString(record?.safe_detail) ?? undefined}
      title={readString(record?.title) ?? undefined}
    />
  );
}

function UnsupportedComponentRenderer({ block }: ChatRemoteComponentProps) {
  return (
    <ErrorRecoveryCard
      description="Este bloque llegó desde el backend con una clave no registrada en el cliente actual."
      errorCode={block.registryKey}
      safeDetail="Actualiza el cliente o verifica la versión del componente remoto."
      title="Componente remoto no compatible"
    />
  );
}

const remoteComponentRegistry: Record<
  ChatComponentRegistryKey,
  React.ComponentType<ChatRemoteComponentProps>
> = {
  "action.preview@1": PrepPreviewRenderer,
  "action.confirm@1": ActionConfirmRenderer,
  "action.result@1": ActionResultRenderer,
  "clarification.options@1": ClarificationOptionsRenderer,
  "error.recovery@1": ErrorRecoveryRenderer,
  "events.list@1": EventsListRenderer,
  "events.summary@1": EventsSummaryRenderer,
  "inventory.missing@1": InventoryMissingRenderer,
  "prep.list@1": PrepListRenderer,
  "prep.preview@1": PrepPreviewRenderer,
  "prep.weekly-board@1": WeeklyBoardRenderer,
  "tasks.mine@1": TasksMineRenderer,
};

export function ChatRemoteComponent({
  block,
  disabled = false,
  onSendSuggestion,
}: ChatRemoteComponentProps) {
  const Renderer =
    remoteComponentRegistry[block.registryKey as ChatComponentRegistryKey] ??
    UnsupportedComponentRenderer;

  return (
    <Renderer
      block={block}
      disabled={disabled}
      onSendSuggestion={onSendSuggestion}
    />
  );
}

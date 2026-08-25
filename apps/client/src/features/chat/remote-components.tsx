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
import { MenuSection } from "@/components/patterns/menu-section";
import { MenuSummaryCard } from "@/components/patterns/menu-summary-card";
import { PrepSummaryCard } from "@/components/patterns/prep-summary-card";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Button } from "@/components/primitives/button";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { Text } from "@/components/primitives/text";
import { TextField } from "@/components/primitives/text-field";
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
import { useRecipes } from "@/features/recipes";
import { useWorkspace } from "@/features/workspace";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";
import type { MenuRecord, MenuSectionRecord } from "@/features/menus";

type ChatRemoteComponentProps = {
  block: ChatComponentBlockRecord;
  disabled?: boolean;
  onSendSuggestion?: (value: string) => void;
};

type EditableMenuItem = {
  description?: string | null;
  metadata?: Record<string, unknown>;
  name: string;
  notes?: string | null;
  quantity_per_guest: number | null;
  quantity_suggestion: number | null;
  recipe_id: string | null;
  recipe_version_id: string | null;
  serving_unit: string | null;
  serving_unit_suggestion: string | null;
};

type EditableMenuSection = {
  items: EditableMenuItem[];
  name: string;
  type?: string | null;
};

type EditableMenu = {
  excluded_items?: string[];
  name: string;
  requested_guest_count?: number | null;
  sections: EditableMenuSection[];
  source?: Record<string, unknown>;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" ? (value as Record<string, unknown>) : null;
}

function coerceEditableMenu(value: unknown): EditableMenu | null {
  const record = asRecord(value);

  if (!record || typeof record.name !== "string" || !Array.isArray(record.sections)) {
    return null;
  }

  const sections = record.sections.reduce<EditableMenuSection[]>((result, sectionValue) => {
    const section = asRecord(sectionValue);

    if (!section || typeof section.name !== "string" || !Array.isArray(section.items)) {
      return result;
    }

    const items = section.items.reduce<EditableMenuItem[]>((itemsResult, itemValue) => {
      const item = asRecord(itemValue);

      if (!item || typeof item.name !== "string") {
        return itemsResult;
      }

      itemsResult.push({
        description: readString(item.description),
        metadata: asRecord(item.metadata) ?? undefined,
        name: item.name,
        notes: readString(item.notes),
        quantity_per_guest: typeof item.quantity_per_guest === "number" ? item.quantity_per_guest : null,
        quantity_suggestion:
          typeof item.quantity_suggestion === "number"
            ? item.quantity_suggestion
            : typeof asRecord(item.metadata)?.quantity_suggestion === "number"
              ? (asRecord(item.metadata)?.quantity_suggestion as number)
              : null,
        recipe_id: readString(item.recipe_id),
        recipe_version_id: readString(item.recipe_version_id),
        serving_unit: readString(item.serving_unit),
        serving_unit_suggestion:
          readString(item.serving_unit_suggestion)
          ?? readString(asRecord(item.metadata)?.serving_unit_suggestion),
      });
      return itemsResult;
    }, []);

    result.push({
      items,
      name: section.name,
      type: readString(section.type),
    });
    return result;
  }, []);

  return {
    excluded_items: Array.isArray(record.excluded_items)
      ? record.excluded_items.filter((item): item is string => typeof item === "string")
      : [],
    name: record.name,
    requested_guest_count:
      typeof record.requested_guest_count === "number" ? record.requested_guest_count : null,
    sections,
    source: asRecord(record.source) ?? undefined,
  };
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

function coerceMenu(value: unknown): MenuRecord | null {
  const record = asRecord(value);
  const id = readString(record?.id);
  const name = readString(record?.name);

  if (!record || !id || !name) {
    return null;
  }

  const sections: MenuSectionRecord[] = Array.isArray(record.sections)
    ? record.sections.reduce<MenuSectionRecord[]>((items, value) => {
        const section = asRecord(value);
        const sectionName = readString(section?.name);

        if (!section || !sectionName) {
          return items;
        }

        const sectionItems = Array.isArray(section.items)
          ? section.items.reduce<MenuSectionRecord["items"]>((menuItems, itemValue) => {
              const item = asRecord(itemValue);
              const itemName = readString(item?.name);

              if (itemName) {
                menuItems.push({
                  description: readString(item?.description),
                  id: readString(item?.id) ?? undefined,
                  name: itemName,
                  notes: readString(item?.notes),
                  position: typeof item?.position === "number" ? item.position : null,
                });
              }

              return menuItems;
            }, [])
          : [];

        items.push({
          id: readString(section?.id) ?? undefined,
          itemCount: sectionItems.length,
          items: sectionItems,
          name: sectionName,
          position: typeof section?.position === "number" ? section.position : null,
        });

        return items;
      }, [])
    : [];

  return {
    description: readString(record.description),
    id,
    itemCount: typeof record.item_count === "number" ? record.item_count : null,
    name,
    recipeCount: typeof record.recipe_count === "number" ? record.recipe_count : null,
    sectionCount: typeof record.section_count === "number" ? record.section_count : sections.length,
    sections,
    status:
      record.status === "draft" ||
      record.status === "active" ||
      record.status === "published" ||
      record.status === "archived"
        ? record.status
        : null,
  };
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

function MenusListRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const { t } = useTranslation("common");
  const menus = Array.isArray(record?.menus)
    ? record.menus.map(coerceMenu).filter((menu): menu is MenuRecord => Boolean(menu))
    : [];
  const { theme } = useAppTheme();

  return (
    <RemoteCardFrame title={readString(record?.title)}>
      <View style={{ gap: theme.spacing[3] }}>
        {menus.length ? (
          menus.map((menu) => <MenuSummaryCard compact key={menu.id} menu={menu} />)
        ) : (
          <Text tone="secondary" variant="bodySmall">
            {t("menus.empty.title")}
          </Text>
        )}
      </View>
    </RemoteCardFrame>
  );
}

function MenuDetailRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const menu = coerceMenu(record?.menu);
  const { theme } = useAppTheme();

  if (!menu) {
    return <UnsupportedComponentRenderer block={block} />;
  }

  return (
    <View style={{ gap: theme.spacing[3] }}>
      <MenuSummaryCard menu={menu} />
      {menu.sections?.map((section) => (
        <MenuSection key={section.id ?? section.name} section={section} />
      ))}
    </View>
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

function MenuConfirmationEditor({
  menu,
  onChange,
}: {
  menu: EditableMenu;
  onChange: (menu: EditableMenu) => void;
}) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const recipesQuery = useRecipes({ perPage: 100 });
  const recipeOptions = recipesQuery.recipes.map((recipe) => ({
    currentVersionId: recipe.currentVersionId ?? recipe.currentVersionRecord?.id ?? null,
    label: recipe.name,
    value: recipe.id,
  }));

  const updateItem = (sectionIndex: number, itemIndex: number, nextItem: EditableMenuItem) => {
    onChange({
      ...menu,
      sections: menu.sections.map((section, currentSectionIndex) =>
        currentSectionIndex === sectionIndex
          ? {
              ...section,
              items: section.items.map((item, currentItemIndex) =>
                currentItemIndex === itemIndex ? nextItem : item
              ),
            }
          : section
      ),
    });
  };

  return (
    <BaseCard padding="md" radius="lg" variant="muted">
      <View style={{ gap: theme.spacing[3] }}>
        <CardHeader
          padding="none"
          subtitle={t("chat.operations.menuConfirmation.description")}
          title={t("chat.operations.menuConfirmation.title")}
        />
        {menu.sections.map((section, sectionIndex) => (
          <View key={`${section.name}-${sectionIndex}`} style={{ gap: theme.spacing[3] }}>
            <Text tone="secondary" variant="overline">{section.name}</Text>
            {section.items.map((item, itemIndex) => {
              const suggestion = asRecord(item.metadata?.recipe_suggestion);
              const selectedRecipe = recipeOptions.find((recipe) => recipe.value === item.recipe_id);
              const itemOptions = suggestion?.recipe_id && suggestion.recipe_id !== item.recipe_id
                ? [
                    {
                      label: readString(suggestion.name) ?? t("chat.operations.menuConfirmation.suggestedRecipe"),
                      metadata: t("chat.operations.menuConfirmation.suggestedRecipe"),
                      value: String(suggestion.recipe_id),
                      currentVersionId: readString(suggestion.recipe_version_id),
                    },
                    ...recipeOptions,
                  ]
                : recipeOptions;
              const total = item.quantity_per_guest !== null && menu.requested_guest_count
                ? item.quantity_per_guest * menu.requested_guest_count
                : null;

              return (
                <View key={`${item.name}-${itemIndex}`} style={{ gap: theme.spacing[2] }}>
                  <Text variant="body">{item.name}</Text>
                  <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
                    <View style={{ flex: 1, minWidth: 150 }}>
                      <TextField
                        keyboardType="decimal-pad"
                        label={t("menus.form.fields.quantityPerGuest.label")}
                        onChangeText={(value) => updateItem(sectionIndex, itemIndex, {
                          ...item,
                          quantity_per_guest: value.trim() === "" ? null : Number(value),
                        })}
                        value={item.quantity_per_guest?.toString() ?? ""}
                      />
                    </View>
                    <View style={{ flex: 1, minWidth: 150 }}>
                      <TextField
                        label={t("menus.form.fields.servingUnit.label")}
                        onChangeText={(serving_unit) => updateItem(sectionIndex, itemIndex, { ...item, serving_unit })}
                        value={item.serving_unit ?? ""}
                      />
                    </View>
                  </View>
                  {menu.requested_guest_count && total !== null ? (
                    <Text tone="secondary" variant="caption">
                      {t("chat.operations.menuConfirmation.previewTotal", {
                        count: menu.requested_guest_count,
                        total,
                        unit: item.serving_unit ?? "",
                      })}
                    </Text>
                  ) : null}
                  {item.quantity_suggestion !== null || item.serving_unit_suggestion !== null ? (
                    <View style={{ gap: theme.spacing[1] }}>
                      <Text tone="secondary" variant="caption">
                        {t("chat.operations.menuConfirmation.aiSuggestion", {
                          quantity: item.quantity_suggestion?.toString() ?? "",
                          unit: item.serving_unit_suggestion ?? "",
                        })}
                      </Text>
                      <Button
                        label={t("chat.operations.menuConfirmation.applySuggestion")}
                        onPress={() => updateItem(sectionIndex, itemIndex, {
                          ...item,
                          quantity_per_guest: item.quantity_suggestion,
                          serving_unit: item.serving_unit_suggestion,
                        })}
                        size="sm"
                        variant="ghost"
                      />
                    </View>
                  ) : null}
                  <EntityPicker
                    entities={itemOptions}
                    label={t("menus.form.fields.recipe.label")}
                    onChange={(recipe_id) => {
                      const recipe = itemOptions.find((option) => option.value === recipe_id);
                      updateItem(sectionIndex, itemIndex, {
                        ...item,
                        recipe_id,
                        recipe_version_id: recipe?.currentVersionId ?? null,
                      });
                    }}
                    placeholder={t("menus.form.fields.recipe.placeholder")}
                    value={selectedRecipe?.value ?? item.recipe_id ?? undefined}
                  />
                  {item.recipe_id ? (
                    <Button
                      label={t("menus.actions.removeRecipe")}
                      onPress={() => updateItem(sectionIndex, itemIndex, {
                        ...item,
                        recipe_id: null,
                        recipe_version_id: null,
                      })}
                      size="sm"
                      variant="ghost"
                    />
                  ) : null}
                  <Button
                    label={t("chat.operations.menuConfirmation.createRecipe")}
                    onPress={() => router.push(routes.app.recipeCreate)}
                    size="sm"
                    variant="ghost"
                  />
                </View>
              );
            })}
          </View>
        ))}
      </View>
    </BaseCard>
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
  const initialEditableMenu = coerceEditableMenu(record?.editable_menu);
  const { t } = useTranslation("common");
  const { session } = useAuth();
  const { activeMembership, activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const [resolved, setResolved] = useState<"cancelled" | "confirmed" | null>(null);
  const [errorState, setErrorState] = useState<string | null>(null);
  const [editableMenu, setEditableMenu] = useState<EditableMenu | null>(initialEditableMenu);
  const workspaceId = activeWorkspace?.id ?? null;
  const membershipId = activeMembership?.id ?? null;
  const mutation = useMutation({
    mutationFn: async (mode: "cancel" | "confirm") => {
      if (!session?.token || !workspaceId || !confirmationToken) {
        throw new Error("Missing confirmation context.");
      }

      return mode === "confirm"
        ? confirmChatAction(session.token, workspaceId, confirmationToken, editableMenu)
        : cancelChatAction(session.token, workspaceId, confirmationToken);
    },
    onSuccess: async (result, mode) => {
      setErrorState(null);
      setResolved(mode === "confirm" ? "confirmed" : "cancelled");

      if (workspaceId && result.assistantResponse) {
        queryClient.setQueriesData<ChatConversationRecord>(
          { queryKey: chatKeys.workspace(workspaceId) },
          (current) =>
            current && current.id === result.conversationId
              ? applyAssistantResponseToConversation(
                  current,
                  result.assistantResponse!,
                  result.conversationId,
                  result.conversationLastMessageAt
                )
              : current
        );
      }

      if (!workspaceId || mode !== "confirm") {
        return;
      }

      const toolKey = result.tool?.key ?? null;
      const invalidations: Promise<unknown>[] = [
        queryClient.invalidateQueries({ queryKey: chatKeys.history(workspaceId) }),
      ];

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
      {editableMenu ? (
        <MenuConfirmationEditor menu={editableMenu} onChange={setEditableMenu} />
      ) : null}
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
  "menus.detail@1": MenuDetailRenderer,
  "menus.list@1": MenusListRenderer,
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

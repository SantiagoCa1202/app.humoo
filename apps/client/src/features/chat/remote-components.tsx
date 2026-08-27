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
  executeChatComponentAction,
} from "@/features/chat/api";
import { applyAssistantResponseToConversation, chatKeys } from "@/features/chat/hooks";
import type {
  ChatComponentBlockRecord,
  ChatConversationRecord,
  ChatComponentRegistryKey,
} from "@/features/chat/types";
import { commandCenterKeys } from "@/features/home/queryKeys";
import { eventKeys } from "@/features/events/hooks/useEvents";
import { directoryKeys } from "@/features/directory/hooks";
import { prepKeys } from "@/features/prep/hooks";
import { taskKeys } from "@/features/tasks/hooks";
import { teamStaffKeys } from "@/features/team-staff/hooks";
import { menuKeys } from "@/features/menus";
import { recipeKeys, useRecipes } from "@/features/recipes";
import { documentKeys } from "@/features/documents/hooks/useDocuments";
import { notificationKeys } from "@/features/notifications/hooks";
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
  const { t } = useTranslation("common");
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const clarificationId = readString(record?.clarification_id);
  const isEntityDisambiguation = block.registryKey === "entity.disambiguation@1";
  const isStructuredClarification =
    (block.schemaVersion === 2 || isEntityDisambiguation) && Boolean(block.instanceId) && Boolean(clarificationId);
  const workspaceId = activeWorkspace?.id ?? null;
  const selectionMode =
    readString(record?.selection_mode) === "single" ? "single" : "immediate";
  const [selected, setSelected] = useState<string | undefined>();
  const [customValue, setCustomValue] = useState("");
  const [errorState, setErrorState] = useState<string | null>(null);
  const [resolvedLabel, setResolvedLabel] = useState<string | null>(null);
  const [resolved, setResolved] = useState<"cancelled" | "resolved" | null>(null);
  const mutation = useMutation({
    mutationFn: async ({ actionId, input }: { actionId: "clarification.cancel" | "clarification.resolve" | "entity.disambiguation.resolve"; input: Record<string, unknown>; resolvedLabel?: string }) => {
      if (!session?.token || !workspaceId || !block.instanceId) {
        throw new Error("Missing clarification context.");
      }

      return executeChatComponentAction(session.token, workspaceId, {
        actionId,
        componentInstanceId: block.instanceId,
        input,
      });
    },
    onError: () => setErrorState(t("chat.blocks.clarification.resolveError")),
    onSuccess: async (result, variables) => {
      setErrorState(null);
      setResolved(variables.actionId === "clarification.cancel" ? "cancelled" : "resolved");
      setResolvedLabel(variables.resolvedLabel ?? null);

      if (workspaceId && result.assistantResponse) {
        queryClient.setQueriesData<ChatConversationRecord>(
          { queryKey: chatKeys.workspace(workspaceId) },
          (current) =>
            current && current.id === result.conversationId
              ? applyAssistantResponseToConversation(
                  current,
                  result.assistantResponse!,
                  result.conversationId,
                  result.conversationLastMessageAt,
                )
              : current,
        );
        await queryClient.invalidateQueries({ queryKey: chatKeys.history(workspaceId) });
      }
    },
  });

  if (resolved) {
    return (
      <ActionResultCard
        description={
          resolved === "resolved" && resolvedLabel
            ? t("chat.blocks.clarification.resolvedValue", { value: resolvedLabel })
            : t(`chat.blocks.clarification.${resolved}`)
        }
        status="success"
        title={t("chat.blocks.clarification.title")}
      />
    );
  }

  const submitStructuredClarification = (option: ClarificationOption) => {
    if (!clarificationId) {
      return;
    }

    const input: Record<string, unknown> = isEntityDisambiguation
      ? { clarification_id: clarificationId, candidate_id: option.id }
      : { clarification_id: clarificationId, selected_option_id: option.id };

    if (option.id === "custom") {
      const normalizedValue = Number(customValue.trim().replace(",", "."));
      if (!Number.isFinite(normalizedValue) || normalizedValue <= 0) {
        setErrorState(t("chat.blocks.clarification.invalidCustomValue"));
        return;
      }
      input.custom_value = normalizedValue;
    }

    mutation.mutate({
      actionId: isEntityDisambiguation ? "entity.disambiguation.resolve" : "clarification.resolve",
      input,
      resolvedLabel:
        option.id === "custom"
          ? `${customValue.trim()} ${readString(asRecord(record?.custom_input)?.unit) ?? ""}`.trim()
          : option.label,
    });
  };

  return (
    <View style={{ gap: 12 }}>
      <ClarificationCard
        description={readString(record?.description) ?? undefined}
        disabled={disabled || mutation.isPending}
        loading={mutation.isPending}
        onCancel={
          isStructuredClarification && clarificationId
            ? () => mutation.mutate({ actionId: "clarification.cancel", input: { clarification_id: clarificationId } })
            : undefined
        }
        onSelect={(option) => {
          setErrorState(null);
          setSelected(option.id);

          if (!isStructuredClarification && selectionMode === "immediate") {
            onSendSuggestion?.(option.value ?? option.label);
          }
        }}
        onSubmit={
          selectionMode === "single"
            ? (option) => {
                if (!option) {
                  return;
                }

                if (isStructuredClarification) {
                  submitStructuredClarification(option);
                  return;
                }

                onSendSuggestion?.(option.value ?? option.label);
              }
            : undefined
        }
        options={options}
        selected={selected}
        selectionMode={selectionMode}
        title={readString(record?.title) ?? undefined}
      >
        {isStructuredClarification && selected === "custom" ? (
          <TextField
            editable={!disabled && !mutation.isPending}
            keyboardType="decimal-pad"
            label={t("chat.blocks.clarification.customValue")}
            onChangeText={setCustomValue}
            value={customValue}
          />
        ) : null}
      </ClarificationCard>
      {errorState ? <Text tone="danger" variant="bodySmall">{errorState}</Text> : null}
    </View>
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

function RecipeListRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const recipes = Array.isArray(record?.recipes)
    ? record.recipes.map(asRecord).filter((recipe): recipe is Record<string, unknown> => Boolean(recipe))
    : [];
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <RemoteCardFrame title={readString(record?.title)}>
      <View style={{ gap: theme.spacing[3] }}>
        {recipes.length ? recipes.map((recipe, index) => (
          <View key={readString(recipe.id) ?? `${readString(recipe.name) ?? "recipe"}-${index}`} style={{ gap: theme.spacing[1] }}>
            <Text variant="body">{readString(recipe.name) ?? t("recipes.version.emptyValue")}</Text>
            <Text tone="secondary" variant="bodySmall">
              {[readString(recipe.category), readString(recipe.status)].filter(Boolean).join(" · ")}
            </Text>
          </View>
        )) : <Text tone="secondary" variant="bodySmall">{t("recipes.empty.title")}</Text>}
      </View>
    </RemoteCardFrame>
  );
}

function RecipeDetailRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const recipe = asRecord(record?.recipe);
  const version = asRecord(recipe?.current_version_record);
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  if (!recipe) {
    return <UnsupportedComponentRenderer block={block} />;
  }

  return (
    <RemoteCardFrame title={readString(record?.title) ?? readString(recipe.name)}>
      <View style={{ gap: theme.spacing[2] }}>
        <Text variant="body">{readString(recipe.name)}</Text>
        <Text tone="secondary" variant="bodySmall">
          {t("recipes.labels.ingredients")}: {Array.isArray(version?.ingredients) ? version.ingredients.length : 0}
          {" · "}{t("recipes.labels.steps")}: {Array.isArray(version?.steps) ? version.steps.length : 0}
        </Text>
        {readString(recipe.description) ? <Text tone="secondary" variant="bodySmall">{readString(recipe.description)}</Text> : null}
      </View>
    </RemoteCardFrame>
  );
}

function RecipeScaledRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const ingredients = Array.isArray(record?.scaled_ingredients)
    ? record.scaled_ingredients.map(asRecord).filter((item): item is Record<string, unknown> => Boolean(item))
    : [];
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <RemoteCardFrame title={readString(record?.title) ?? t("recipes.scaler.title")}>
      <View style={{ gap: theme.spacing[2] }}>
        <Text tone="secondary" variant="bodySmall">
          {t("recipes.scaler.scaleFactor")}: {typeof record?.scale_factor === "number" ? record.scale_factor.toFixed(3) : t("recipes.scaler.invalidScale")}
        </Text>
        {ingredients.map((item, index) => (
          <Text key={readString(item.id) ?? `${readString(item.ingredient_name) ?? "ingredient"}-${index}`} variant="bodySmall">
            {readString(item.ingredient_name)}: {String(item.quantity ?? "-")}
          </Text>
        ))}
      </View>
    </RemoteCardFrame>
  );
}

function AdvisoryResultRenderer({ block, disabled, onSendSuggestion }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const findings = readStringItems(record?.findings);
  const warnings = readStringItems(record?.warnings);
  const recommendations = Array.isArray(record?.recommendations)
    ? record.recommendations.map(asRecord).filter((item): item is Record<string, unknown> => Boolean(item))
    : [];
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <RemoteCardFrame title={t("chat.advisory.title")} description={readString(record?.summary) ?? undefined}>
      <View style={{ gap: theme.spacing[3] }}>
        {findings.length ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text variant="label">{t("chat.advisory.findings")}</Text>
            {findings.map((finding, index) => <Text key={`${finding}-${index}`} tone="secondary" variant="bodySmall">{finding}</Text>)}
          </View>
        ) : null}
        {recommendations.map((recommendation, index) => (
          <BaseCard key={`${readString(recommendation.target) ?? "recommendation"}-${index}`} padding="sm" radius="md" variant="muted">
            <View style={{ gap: theme.spacing[1] }}>
              <Text variant="body">{readString(recommendation.target) ?? t("chat.advisory.recommendation")}</Text>
              {readString(recommendation.reasoning) ? <Text tone="secondary" variant="bodySmall">{readString(recommendation.reasoning)}</Text> : null}
              <Text tone="secondary" variant="caption">
                {[recommendation.current_value !== null && recommendation.current_value !== undefined ? `${t("chat.advisory.current")}: ${String(recommendation.current_value)}` : null, recommendation.proposed_value !== null && recommendation.proposed_value !== undefined ? `${t("chat.advisory.proposed")}: ${String(recommendation.proposed_value)}` : null, readString(recommendation.unit), readString(recommendation.confidence) ? `${t("chat.advisory.confidence")}: ${readString(recommendation.confidence)}` : null].filter(Boolean).join(" · ")}
              </Text>
              {readStringItems(recommendation.evidence).map((evidence, evidenceIndex) => <Text key={`${evidence}-${evidenceIndex}`} tone="secondary" variant="caption">• {evidence}</Text>)}
              {onSendSuggestion && readString(recommendation.proposed_value) ? <Button disabled={disabled} label={t("chat.advisory.applyRecommendation")} onPress={() => onSendSuggestion(t("chat.advisory.applyPrompt"))} size="sm" variant="ghost" /> : null}
            </View>
          </BaseCard>
        ))}
        {warnings.length ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text variant="label">{t("chat.advisory.warnings")}</Text>
            {warnings.map((warning, index) => <Text key={`${warning}-${index}`} tone="secondary" variant="caption">{warning}</Text>)}
          </View>
        ) : null}
      </View>
    </RemoteCardFrame>
  );
}

function RecipeDraftRenderer({ block, disabled, onSendSuggestion }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const ingredients = Array.isArray(record?.ingredients)
    ? record.ingredients.map(asRecord).filter((item): item is Record<string, unknown> => Boolean(item))
    : [];
  const steps = readStringItems(record?.steps);
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <RemoteCardFrame title={readString(record?.name) ?? t("chat.advisory.recipeDraft")} description={readString(record?.description) ?? t("chat.advisory.aiProposal")}>
      <View style={{ gap: theme.spacing[3] }}>
        {record?.yield !== null && record?.yield !== undefined ? <Text tone="secondary" variant="bodySmall">{t("chat.advisory.yield")}: {String(record.yield)} {readString(record.yield_unit) ?? ""}</Text> : null}
        <View style={{ gap: theme.spacing[1] }}>
          <Text variant="label">{t("chat.advisory.ingredients")}</Text>
          {ingredients.map((ingredient, index) => <Text key={`${readString(ingredient.name) ?? "ingredient"}-${index}`} tone="secondary" variant="bodySmall">{[ingredient.quantity, readString(ingredient.unit), readString(ingredient.name), readString(ingredient.preparation_note)].filter((value) => value !== null && value !== undefined && value !== "").join(" ")}</Text>)}
        </View>
        <View style={{ gap: theme.spacing[1] }}>
          <Text variant="label">{t("chat.advisory.steps")}</Text>
          {steps.map((step, index) => <Text key={`${step}-${index}`} tone="secondary" variant="bodySmall">{index + 1}. {step}</Text>)}
        </View>
        {onSendSuggestion ? <Button disabled={disabled} label={t("chat.advisory.saveRecipe")} onPress={() => onSendSuggestion(t("chat.advisory.savePrompt"))} size="sm" variant="secondary" /> : null}
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

function directoryLabel(record: Record<string, unknown>): string {
  return (
    readString(record.name) ??
    readString(record.display_name) ??
    readString(record.full_name) ??
    [readString(record.first_name), readString(record.last_name)].filter(Boolean).join(" ") ??
    ""
  );
}

function DirectoryListRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const items = Array.isArray(record?.items)
    ? record.items.map(asRecord).filter((item): item is Record<string, unknown> => Boolean(item))
    : [];
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <RemoteCardFrame title={readString(record?.title)} description={readString(record?.description)}>
      <View style={{ gap: theme.spacing[3] }}>
        {items.length ? (
          items.map((item, index) => {
            const label = directoryLabel(item) || readString(item.id) || `#${index + 1}`;
            const secondary = [
              readString(item.company_name),
              readString(item.email),
              readString(item.city),
              readString(item.phone),
            ].filter(Boolean).join(" · ");

            return (
              <View key={readString(item.id) ?? `${label}-${index}`} style={{ gap: 2 }}>
                <Text variant="body">{label}</Text>
                {secondary ? <Text tone="secondary" variant="bodySmall">{secondary}</Text> : null}
              </View>
            );
          })
        ) : (
          <Text tone="secondary" variant="bodySmall">{readString(record?.empty) ?? t("chat.directory.empty")}</Text>
        )}
      </View>
    </RemoteCardFrame>
  );
}

function DirectoryDetailRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const entity = asRecord(record?.entity);
  const { theme } = useAppTheme();

  if (!entity) {
    return <UnsupportedComponentRenderer block={block} />;
  }

  const fields = [
    entity.email,
    entity.phone,
    entity.company_name,
    entity.city,
    entity.state,
    entity.status,
  ].map(readString).filter((value): value is string => Boolean(value));

  return (
    <RemoteCardFrame title={readString(record?.title)}>
      <View style={{ gap: theme.spacing[2] }}>
        <Text variant="body">{directoryLabel(entity)}</Text>
        {fields.map((field, index) => <Text key={`${field}-${index}`} tone="secondary" variant="bodySmall">{field}</Text>)}
      </View>
    </RemoteCardFrame>
  );
}

function ClientsListRenderer({ block }: ChatRemoteComponentProps) { return <DirectoryListRenderer block={block} />; }
function ClientsDetailRenderer({ block }: ChatRemoteComponentProps) { return <DirectoryDetailRenderer block={block} />; }
function ContactsListRenderer({ block }: ChatRemoteComponentProps) { return <DirectoryListRenderer block={block} />; }
function ContactsDetailRenderer({ block }: ChatRemoteComponentProps) { return <DirectoryDetailRenderer block={block} />; }
function VenuesListRenderer({ block }: ChatRemoteComponentProps) { return <DirectoryListRenderer block={block} />; }
function VenuesDetailRenderer({ block }: ChatRemoteComponentProps) { return <DirectoryDetailRenderer block={block} />; }

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
  const generation = asRecord(record?.generation);
  const warnings = Array.isArray(generation?.warnings)
    ? generation.warnings.map(asRecord).filter((warning): warning is Record<string, unknown> => Boolean(warning))
    : [];
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <View style={{ gap: theme.spacing[3] }}>
      <ActionPreviewCard
        changes={Array.isArray(record?.changes) ? (record?.changes as never[]) : []}
        description={readString(record?.description) ?? undefined}
        destructive={readBoolean(record?.destructive)}
        impact={readString(record?.impact) ?? undefined}
        metadata={Array.isArray(record?.metadata) ? (record?.metadata as never[]) : []}
        title={readString(record?.title) ?? undefined}
        type={readString(record?.type) ?? undefined}
      />
      {generation ? (
        <BaseCard padding="md" radius="lg" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            {readString(generation.summary) ? <Text tone="secondary" variant="bodySmall">{readString(generation.summary)}</Text> : null}
            {typeof generation.guest_count === "number" ? <Text tone="secondary" variant="bodySmall">{t("prep.chat.guestCount", { count: generation.guest_count })}</Text> : null}
            {Array.isArray(generation.items) ? <Text tone="secondary" variant="bodySmall">{t("prep.chat.generatedItems", { count: generation.items.length })}</Text> : null}
            {warnings.length ? (
              <View style={{ gap: theme.spacing[1] }}>
                <Text variant="overline">{t("prep.chat.warnings")}</Text>
                {warnings.map((warning, index) => (
                  <Text key={readString(warning.id) ?? `generation-warning-${index}`} tone="secondary" variant="bodySmall">
                    {[readString(warning.title), readString(warning.description)].filter(Boolean).join(" · ")}
                  </Text>
                ))}
              </View>
            ) : null}
          </View>
        </BaseCard>
      ) : null}
    </View>
  );
}

function PrepDetailRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const prepList = asRecord(record?.prep_list);
  const item = asRecord(record?.item);
  const generation = asRecord(record?.generation);
  const warnings = Array.isArray(generation?.warnings)
    ? generation.warnings.map(asRecord).filter((warning): warning is Record<string, unknown> => Boolean(warning))
    : [];
  const prepEntries = prepList
    ? coerceChatPrepEntries([{ prep_list: prepList, progress: prepList.progress }])
    : [];
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  if (!prepList && !item && !generation) {
    return <UnsupportedComponentRenderer block={block} />;
  }

  return (
    <RemoteCardFrame title={readString(record?.title)}>
      <View style={{ gap: theme.spacing[3] }}>
        {prepEntries.length ? (
          <PrepSummaryCard compact prepList={prepEntries[0].prepList} progress={prepEntries[0].progress} />
        ) : null}
        {item ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text variant="body">{readString(item.title) ?? t("prep.chat.unknownItem")}</Text>
            <Text tone="secondary" variant="bodySmall">
              {[item.quantity, readString(item.unit_label), readString(item.status)].filter((value) => value !== null && value !== undefined && value !== "").join(" · ")}
            </Text>
          </View>
        ) : null}
        {generation ? (
          <View style={{ gap: theme.spacing[1] }}>
            {readString(generation.summary) ? <Text tone="secondary" variant="bodySmall">{readString(generation.summary)}</Text> : null}
            {typeof generation.guest_count === "number" ? <Text tone="secondary" variant="bodySmall">{t("prep.chat.guestCount", { count: generation.guest_count })}</Text> : null}
            {typeof generation.items === "object" && Array.isArray(generation.items) ? <Text tone="secondary" variant="bodySmall">{t("prep.chat.generatedItems", { count: generation.items.length })}</Text> : null}
          </View>
        ) : null}
        {warnings.length ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text variant="label">{t("prep.chat.warnings")}</Text>
            {warnings.map((warning, index) => (
              <Text key={readString(warning.id) ?? `warning-${index}`} tone="secondary" variant="bodySmall">
                {[readString(warning.title), readString(warning.description)].filter(Boolean).join(" · ")}
              </Text>
            ))}
          </View>
        ) : null}
      </View>
    </RemoteCardFrame>
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

      if (toolKey === "tasks.update" || toolKey === "tasks.create" || toolKey === "tasks.delete") {
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

      if (toolKey === "prep_items.update" || toolKey?.startsWith("prep.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: prepKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey?.startsWith("menus.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: menuKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey?.startsWith("recipes.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: recipeKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: menuKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey?.startsWith("events.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: eventKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey?.startsWith("clients.")) {
        invalidations.push(queryClient.invalidateQueries({ queryKey: directoryKeys.clients(workspaceId) }));
      }

      if (toolKey?.startsWith("contacts.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: directoryKeys.contacts(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: directoryKeys.clients(workspaceId) })
        );
      }

      if (toolKey?.startsWith("venues.")) {
        invalidations.push(queryClient.invalidateQueries({ queryKey: directoryKeys.venues(workspaceId) }));
      }

      if (toolKey?.startsWith("teams.") || toolKey?.startsWith("stations.") || toolKey?.startsWith("shifts.") || toolKey?.startsWith("availability.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: teamStaffKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey?.startsWith("documents.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: documentKeys.workspace(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) })
        );
      }

      if (toolKey?.startsWith("notifications.") || toolKey?.startsWith("notification_preferences.")) {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: notificationKeys.list(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: notificationKeys.unreadCount(workspaceId) }),
          queryClient.invalidateQueries({ queryKey: notificationKeys.preferences(workspaceId) })
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

function TeamStaffListRenderer({ block }: ChatRemoteComponentProps) {
  const record = asRecord(block.data);
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const items = Array.isArray(record?.items) ? record.items : [];

  return (
    <RemoteCardFrame title={readString(record?.title)}>
      <View style={{ gap: theme.spacing[2] }}>
        {items.length ? items.map((value, index) => {
          const item = asRecord(value);
          const member = asRecord(item?.member);
          const label = readString(item?.name) ?? readString(member?.name) ?? readString(member?.display_name) ?? `${t("chat.teamStaff.record")} ${index + 1}`;
          const detail = readString(item?.role) ?? readString(item?.status) ?? readString(item?.starts_at);
          return (
            <BaseCard key={readString(item?.id) ?? `${label}-${index}`} padding="md">
              <View style={{ gap: theme.spacing[1] }}>
                <Text variant="label">{label}</Text>
                {detail ? <Text tone="secondary" variant="bodySmall">{detail}</Text> : null}
              </View>
            </BaseCard>
          );
        }) : <Text tone="secondary" variant="bodySmall">{t("chat.teamStaff.empty")}</Text>}
      </View>
    </RemoteCardFrame>
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
  "clarification.options@2": ClarificationOptionsRenderer,
  "entity.disambiguation@1": ClarificationOptionsRenderer,
  "error.recovery@1": ErrorRecoveryRenderer,
  "events.list@1": EventsListRenderer,
  "events.summary@1": EventsSummaryRenderer,
  "clients.list@1": ClientsListRenderer,
  "clients.detail@1": ClientsDetailRenderer,
  "contacts.list@1": ContactsListRenderer,
  "contacts.detail@1": ContactsDetailRenderer,
  "venues.list@1": VenuesListRenderer,
  "venues.detail@1": VenuesDetailRenderer,
  "inventory.missing@1": InventoryMissingRenderer,
  "menus.detail@1": MenuDetailRenderer,
  "menus.list@1": MenusListRenderer,
  "recipes.list@1": RecipeListRenderer,
  "recipes.detail@1": RecipeDetailRenderer,
  "recipes.scaled@1": RecipeScaledRenderer,
  "recipe.draft@1": RecipeDraftRenderer,
  "advisory.result@1": AdvisoryResultRenderer,
  "prep.list@1": PrepListRenderer,
  "prep.detail@1": PrepDetailRenderer,
  "prep.preview@1": PrepPreviewRenderer,
  "prep.weekly-board@1": WeeklyBoardRenderer,
  "tasks.mine@1": TasksMineRenderer,
  "teams.list@1": TeamStaffListRenderer,
  "teams.detail@1": TeamStaffListRenderer,
  "stations.list@1": TeamStaffListRenderer,
  "stations.detail@1": TeamStaffListRenderer,
  "shifts.list@1": TeamStaffListRenderer,
  "shifts.detail@1": TeamStaffListRenderer,
  "availability.list@1": TeamStaffListRenderer,
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

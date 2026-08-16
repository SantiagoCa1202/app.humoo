import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { Checkbox } from "@/components/primitives/checkbox";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { TextField } from "@/components/primitives/text-field";
import {
  getMenuDuplicateDefaultName,
  type MenuDisplayRecord,
  type MenuDuplicateOptions,
  type MenuEventOption,
  type MenuEventReference,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuDuplicateActionSupportedOptions = {
  includeItems?: boolean;
  includeRecipeLinks?: boolean;
  includeSections?: boolean;
  proposedName?: boolean;
  targetEvent?: boolean;
};

export type MenuDuplicateActionProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  eventOptions?: MenuEventOption[];
  includeItems?: boolean;
  includeRecipeLinks?: boolean;
  includeSections?: boolean;
  loading?: boolean;
  menu: Pick<MenuDisplayRecord, "event" | "itemCount" | "name" | "sectionCount">;
  onCancel?: () => void | Promise<void>;
  onChange?: (value: MenuDuplicateOptions) => void;
  onConfirm: (value: MenuDuplicateOptions) => void | Promise<void>;
  proposedName?: string | null;
  supportedOptions?: MenuDuplicateActionSupportedOptions;
  targetEvent?: MenuEventReference | null;
};

function getInitialOptions(
  menu: Pick<MenuDisplayRecord, "event" | "name">,
  t: (key: string, options?: Record<string, unknown>) => string,
  values: Pick<
    MenuDuplicateActionProps,
    "includeItems" | "includeRecipeLinks" | "includeSections" | "proposedName" | "targetEvent"
  >
): MenuDuplicateOptions {
  return {
    includeItems: values.includeItems ?? true,
    includeRecipeLinks: values.includeRecipeLinks ?? false,
    includeSections: values.includeSections ?? true,
    proposedName: values.proposedName ?? getMenuDuplicateDefaultName(menu, t),
    targetEvent: values.targetEvent ?? null,
    targetEventId: values.targetEvent?.id ?? null,
  };
}

export function MenuDuplicateAction({
  accessibilityLabel,
  compact = false,
  disabled = false,
  eventOptions,
  includeItems,
  includeRecipeLinks,
  includeSections,
  loading = false,
  menu,
  onCancel,
  onChange,
  onConfirm,
  proposedName,
  supportedOptions,
  targetEvent,
}: MenuDuplicateActionProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify({
    includeItems,
    includeRecipeLinks,
    includeSections,
    proposedName,
    targetEvent,
  });
  const initialOptions = useMemo(
    () =>
      getInitialOptions(menu, t, {
        includeItems,
        includeRecipeLinks,
        includeSections,
        proposedName,
        targetEvent,
      }),
    [initialSignature, menu, t]
  );
  const [options, setOptions] = useState<MenuDuplicateOptions>(initialOptions);

  useEffect(() => {
    setOptions(initialOptions);
  }, [initialOptions]);

  const updateOptions = (value: MenuDuplicateOptions) => {
    setOptions(value);
    onChange?.(value);
  };

  const canEditName = supportedOptions?.proposedName ?? true;
  const canSelectEvent = supportedOptions?.targetEvent ?? Boolean(eventOptions?.length);
  const canToggleSections = supportedOptions?.includeSections ?? includeSections !== undefined;
  const canToggleItems = supportedOptions?.includeItems ?? includeItems !== undefined;
  const canToggleRecipeLinks =
    supportedOptions?.includeRecipeLinks ?? includeRecipeLinks !== undefined;

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("menus.duplicate.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <View style={{ gap: theme.spacing[3] }}>
        {canEditName ? (
          <TextField
            editable={!disabled && !loading}
            label={t("menus.duplicate.fields.name.label")}
            onChangeText={(value) => updateOptions({ ...options, proposedName: value })}
            placeholder={t("menus.duplicate.fields.name.placeholder")}
            value={options.proposedName ?? ""}
          />
        ) : null}
        {canSelectEvent && eventOptions?.length ? (
          <EntityPicker
            disabled={disabled || loading}
            entities={eventOptions}
            label={t("menus.duplicate.fields.event.label")}
            onChange={(value) => {
              const selectedEvent = eventOptions.find((option) => option.value === value);

              updateOptions({
                ...options,
                targetEvent: selectedEvent
                  ? {
                      id: selectedEvent.value,
                      name: selectedEvent.label ?? selectedEvent.name ?? null,
                    }
                  : null,
                targetEventId: value,
              });
            }}
            placeholder={t("menus.duplicate.fields.event.placeholder")}
            value={options.targetEventId ?? undefined}
          />
        ) : null}
        {canToggleSections ? (
          <Checkbox
            checked={Boolean(options.includeSections)}
            disabled={disabled || loading}
            label={t("menus.duplicate.options.includeSections")}
            onChange={(value) => updateOptions({ ...options, includeSections: value })}
          />
        ) : null}
        {canToggleItems ? (
          <Checkbox
            checked={Boolean(options.includeItems)}
            disabled={disabled || loading}
            label={t("menus.duplicate.options.includeItems")}
            onChange={(value) => updateOptions({ ...options, includeItems: value })}
          />
        ) : null}
        {canToggleRecipeLinks ? (
          <Checkbox
            checked={Boolean(options.includeRecipeLinks)}
            disabled={disabled || loading}
            label={t("menus.duplicate.options.includeRecipeLinks")}
            onChange={(value) => updateOptions({ ...options, includeRecipeLinks: value })}
          />
        ) : null}
      </View>
      <ActionPreviewCard
        action={t("menus.duplicate.preview.action")}
        description={t("menus.duplicate.preview.description")}
        impact={t("menus.duplicate.preview.impact")}
        metadata={[
          {
            label: t("menus.duplicate.fields.name.label"),
            value: options.proposedName ?? t("menus.duplicate.emptyValue"),
          },
          {
            label: t("menus.duplicate.fields.sections.label"),
            value: t(options.includeSections ? "menus.duplicate.enabled" : "menus.duplicate.disabled"),
          },
          {
            label: t("menus.duplicate.fields.items.label"),
            value: t(options.includeItems ? "menus.duplicate.enabled" : "menus.duplicate.disabled"),
          },
          {
            label: t("menus.duplicate.fields.recipeLinks.label"),
            value: t(
              options.includeRecipeLinks ? "menus.duplicate.enabled" : "menus.duplicate.disabled"
            ),
          },
          {
            label: t("menus.duplicate.fields.event.label"),
            value:
              options.targetEvent?.name?.trim() ??
              menu.event?.name?.trim() ??
              t("menus.eventLink.emptyTitle"),
          },
        ]}
        title={t("menus.duplicate.title")}
        type={t("menus.duplicate.badge")}
      />
      <ConfirmationCard
        accessibilityLabel={t("menus.duplicate.confirmationAccessibilityLabel")}
        cancelLabel={t("menus.actions.cancel")}
        confirmLabel={t("menus.duplicate.confirm")}
        description={t("menus.duplicate.confirmationDescription")}
        details={[
          {
            label: t("menus.duplicate.fields.name.label"),
            value: options.proposedName ?? t("menus.duplicate.emptyValue"),
          },
          ...(compact
            ? []
            : [
                {
                  label: t("menus.duplicate.fields.sections.label"),
                  value: t(
                    options.includeSections
                      ? "menus.duplicate.enabled"
                      : "menus.duplicate.disabled"
                  ),
                },
                {
                  label: t("menus.duplicate.fields.items.label"),
                  value: t(
                    options.includeItems ? "menus.duplicate.enabled" : "menus.duplicate.disabled"
                  ),
                },
              ]),
        ]}
        disabled={disabled}
        loading={loading}
        onCancel={onCancel}
        onConfirm={() => onConfirm(options)}
        title={t("menus.duplicate.confirmationTitle")}
      />
    </View>
  );
}

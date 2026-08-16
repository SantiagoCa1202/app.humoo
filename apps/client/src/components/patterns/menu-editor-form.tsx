import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { MenuSectionEditor } from "@/components/patterns/menu-section-editor";
import { Button } from "@/components/primitives/button";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createMenuEditorValues,
  createMenuSectionDraft,
  getMenuSectionKey,
  hasMenuEditorErrors,
  MENU_STATUS_VALUES,
  moveItemInArray,
  normalizeMenuEditorValues,
  normalizeMenuSectionsOrder,
  sortMenuSections,
  validateMenuEditorValues,
  type MenuEditorMode,
  type MenuEditorValidationErrors,
  type MenuEditorValues,
  type MenuEventOption,
  type MenuRecipeOption,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuEditorFormProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  eventOptions?: MenuEventOption[];
  initialValues?: Partial<MenuEditorValues>;
  mode?: MenuEditorMode;
  onCancel?: () => void;
  onSubmit: (value: MenuEditorValues) => void | Promise<void>;
  recipeOptions?: MenuRecipeOption[];
  submitting?: boolean;
  validationErrors?: MenuEditorValidationErrors;
};

function mergeValidationErrors(
  localErrors: MenuEditorValidationErrors,
  externalErrors?: MenuEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
    sections: {
      ...(localErrors.sections ?? {}),
      ...(externalErrors.sections ?? {}),
    },
  } satisfies MenuEditorValidationErrors;
}

export function MenuEditorForm({
  accessibilityLabel,
  compact = false,
  disabled = false,
  eventOptions,
  initialValues,
  mode = "create",
  onCancel,
  onSubmit,
  recipeOptions,
  submitting = false,
  validationErrors,
}: MenuEditorFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createMenuEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<MenuEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<MenuEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const sortedSections = sortMenuSections(values.sections);

  const submitLabel =
    mode === "edit" ? t("menus.actions.saveChanges") : t("menus.actions.create");

  const handleSubmit = async () => {
    const normalized = normalizeMenuEditorValues(values);
    const validation = validateMenuEditorValues(normalized, t);

    if (hasMenuEditorErrors(validation)) {
      setLocalErrors(validation);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("menus.editor.accessibilityLabel")}
      cancelLabel={t("menus.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={submitLabel}
      submitting={submitting}
      title={mode === "edit" ? t("menus.editor.editTitle") : t("menus.editor.createTitle")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          editable={!disabled}
          error={resolvedErrors.name}
          label={t("menus.form.fields.name.label")}
          onChangeText={(name) => setValues((currentValues) => ({ ...currentValues, name }))}
          placeholder={t("menus.form.fields.name.placeholder")}
          required
          value={values.name}
        />
        <TextArea
          autoGrow
          editable={!disabled}
          error={resolvedErrors.description}
          label={t("menus.form.fields.description.label")}
          onChangeText={(description) =>
            setValues((currentValues) => ({ ...currentValues, description }))
          }
          placeholder={t("menus.form.fields.description.placeholder")}
          value={values.description ?? ""}
        />
        <StatusSelect
          disabled={disabled}
          error={resolvedErrors.status}
          label={t("menus.form.fields.status.label")}
          namespace="menus"
          onChange={(status) =>
            setValues((currentValues) => ({
              ...currentValues,
              status: status as MenuEditorValues["status"],
            }))
          }
          options={MENU_STATUS_VALUES.map((status) => ({ value: status }))}
          value={values.status ?? undefined}
        />
        {eventOptions?.length ? (
          <EntityPicker
            disabled={disabled}
            entities={eventOptions}
            error={resolvedErrors.eventId}
            label={t("menus.form.fields.event.label")}
            onChange={(eventId) =>
              {
                const selectedEvent = eventOptions.find(
                  (eventOption) => eventOption.value === eventId
                );

                setValues((currentValues) => ({
                  ...currentValues,
                  event: selectedEvent
                    ? {
                        id: eventId,
                        name: selectedEvent.label ?? null,
                      }
                    : null,
                  eventId,
                }));
              }
            }
            placeholder={t("menus.form.fields.event.placeholder")}
            value={values.eventId ?? undefined}
          />
        ) : null}
        <View style={{ gap: theme.spacing[3] }}>
          {sortedSections.map((section, index) => {
            const sectionKey = getMenuSectionKey(section);

            return (
              <MenuSectionEditor
                accessibilityLabel={t("menus.sectionEditor.accessibilityLabel")}
                compact={compact}
                disabled={disabled}
                errors={resolvedErrors.sections?.[sectionKey]}
                key={sectionKey}
                onChange={(nextSection) =>
                  setValues((currentValues) => ({
                    ...currentValues,
                    sections: currentValues.sections.map((currentSection) =>
                      getMenuSectionKey(currentSection) === sectionKey ? nextSection : currentSection
                    ),
                  }))
                }
                onMoveDown={
                  index < sortedSections.length - 1
                    ? () =>
                        setValues((currentValues) => ({
                          ...currentValues,
                          sections: normalizeMenuSectionsOrder(
                            moveItemInArray(sortMenuSections(currentValues.sections), index, index + 1)
                          ),
                        }))
                    : undefined
                }
                onMoveUp={
                  index > 0
                    ? () =>
                        setValues((currentValues) => ({
                          ...currentValues,
                          sections: normalizeMenuSectionsOrder(
                            moveItemInArray(sortMenuSections(currentValues.sections), index, index - 1)
                          ),
                        }))
                    : undefined
                }
                onRemove={() =>
                  setValues((currentValues) => ({
                    ...currentValues,
                    sections: currentValues.sections.filter(
                      (currentSection) => getMenuSectionKey(currentSection) !== sectionKey
                    ),
                  }))
                }
                recipeOptions={recipeOptions}
                section={section}
              />
            );
          })}
          <Button
            disabled={disabled}
            label={t("menus.actions.addSection")}
            onPress={() =>
              setValues((currentValues) => ({
                ...currentValues,
                sections: normalizeMenuSectionsOrder([
                  ...sortMenuSections(currentValues.sections),
                  createMenuSectionDraft({
                    position: currentValues.sections.length + 1,
                  }),
                ]),
              }))
            }
            size="sm"
            variant="secondary"
          />
        </View>
      </View>
    </FormCard>
  );
}

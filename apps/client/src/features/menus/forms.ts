import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type {
  MenuItemRecord,
  MenuRecord,
  MenuSectionRecord,
  MenuStatus,
} from "@/features/menus/types";

export type MenuItemValidationErrors = Partial<
  Record<"description" | "name" | "notes" | "recipeId" | "quantityPerGuest" | "servingUnit", string>
>;

export type MenuSectionValidationErrors = {
  items?: Record<string, MenuItemValidationErrors>;
  name?: string;
};

export type MenuEditorValidationErrors = {
  description?: string;
  eventId?: string;
  form?: string;
  name?: string;
  sections?: Record<string, MenuSectionValidationErrors>;
  status?: string;
};

export type MenuEditorValues = Omit<MenuRecord, "sections"> & {
  sections: MenuSectionRecord[];
};

export type MenuEditorMode = "create" | "edit";

export const MENU_STATUS_VALUES = [
  "draft",
  "active",
  "archived",
] as const satisfies readonly MenuStatus[];

let draftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

export function createMenuDraftKey(prefix: "item" | "section" | "menu" = "menu") {
  draftCounter += 1;
  return `${prefix}-draft-${Date.now()}-${draftCounter}`;
}

export function getMenuSectionKey(section: Pick<MenuSectionRecord, "clientId" | "id" | "name">) {
  return section.id ?? section.clientId ?? section.name ?? createMenuDraftKey("section");
}

export function getMenuItemKey(item: Pick<MenuItemRecord, "clientId" | "id" | "name">) {
  return item.id ?? item.clientId ?? item.name ?? createMenuDraftKey("item");
}

export function sortMenuItems(items: MenuItemRecord[]) {
  return [...items].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return left.name.localeCompare(right.name);
  });
}

export function sortMenuSections(sections: MenuSectionRecord[]) {
  return [...sections].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return (left.name ?? "").localeCompare(right.name ?? "");
  });
}

export function normalizeMenuItemsOrder(items: MenuItemRecord[]) {
  return items.map((item, index) => ({
    ...item,
    position: index + 1,
  }));
}

export function normalizeMenuSectionsOrder(sections: MenuSectionRecord[]) {
  return sections.map((section, index) => ({
    ...section,
    items: normalizeMenuItemsOrder(sortMenuItems(section.items)),
    position: index + 1,
  }));
}

export function moveItemInArray<T>(items: T[], fromIndex: number, toIndex: number) {
  if (
    fromIndex < 0 ||
    toIndex < 0 ||
    fromIndex >= items.length ||
    toIndex >= items.length ||
    fromIndex === toIndex
  ) {
    return items;
  }

  const nextItems = [...items];
  const [item] = nextItems.splice(fromIndex, 1);
  nextItems.splice(toIndex, 0, item);
  return nextItems;
}

export function createMenuItemDraft(
  values?: Partial<MenuItemRecord>
): MenuItemRecord {
  return {
    clientId: values?.clientId ?? createMenuDraftKey("item"),
    description: values?.description ?? null,
    id: values?.id ?? null,
    name: values?.name ?? "",
    notes: values?.notes ?? null,
    position: values?.position ?? null,
    quantityPerGuest: values?.quantityPerGuest ?? null,
    servingUnit: values?.servingUnit ?? null,
    plannedQuantity: values?.plannedQuantity ?? null,
    eventPlannedQuantity: values?.eventPlannedQuantity ?? null,
    quantityLabel: values?.quantityLabel ?? null,
    recipe: values?.recipe ?? null,
    recipeId: values?.recipeId ?? values?.recipe?.id ?? null,
    recipeVersionId: values?.recipeVersionId ?? null,
  };
}

export function createMenuSectionDraft(
  values?: Partial<MenuSectionRecord>
): MenuSectionRecord {
  return {
    clientId: values?.clientId ?? createMenuDraftKey("section"),
    id: values?.id,
    itemCount: values?.itemCount ?? values?.items?.length ?? 0,
    items: normalizeMenuItemsOrder((values?.items ?? []).map((item) => createMenuItemDraft(item))),
    name: values?.name ?? "",
    position: values?.position ?? null,
    translationKey: values?.translationKey ?? null,
  };
}

export function createMenuEditorValues(
  values?: Partial<MenuEditorValues>
): MenuEditorValues {
  return {
    allergenCount: values?.allergenCount ?? null,
    createdAt: values?.createdAt ?? null,
    description: values?.description ?? null,
    event: values?.event ?? null,
    eventId: values?.eventId ?? values?.event?.id ?? null,
    guestCount: values?.guestCount ?? null,
    id: values?.id ?? createMenuDraftKey("menu"),
    itemCount: values?.itemCount ?? null,
    currentVersion:
      values?.currentVersion ??
      (typeof values?.currentVersionRecord?.versionNumber === "number"
        ? values.currentVersionRecord.versionNumber
        : null),
    currentVersionId: values?.currentVersionId ?? values?.currentVersionRecord?.id ?? null,
    currentVersionRecord: values?.currentVersionRecord ?? null,
    metadata: values?.metadata ?? null,
    name: values?.name ?? "",
    recipeCount: values?.recipeCount ?? null,
    sectionCount: values?.sectionCount ?? values?.sections?.length ?? 0,
    sections: normalizeMenuSectionsOrder(
      sortMenuSections((values?.sections ?? []).map((section) => createMenuSectionDraft(section)))
    ),
    status: values?.status ?? "draft",
    summary: values?.summary ?? null,
    tags: values?.tags ?? [],
    type: values?.type ?? null,
    unknownAllergenItemCount: values?.unknownAllergenItemCount ?? null,
    updatedAt: values?.updatedAt ?? null,
    version: values?.version ?? null,
    versions: values?.versions ?? null,
  };
}

export function normalizeMenuEditorValues(values: MenuEditorValues): MenuEditorValues {
  const sections = normalizeMenuSectionsOrder(
    values.sections.map((section) => ({
      ...section,
      itemCount: section.items.length,
      items: normalizeMenuItemsOrder(
        section.items.map((item) => ({
          ...item,
          description: trimOrNull(item.description),
          name: item.name.trim(),
          notes: trimOrNull(item.notes),
          quantityPerGuest: item.quantityPerGuest ?? null,
          servingUnit: trimOrNull(item.servingUnit),
          plannedQuantity: item.plannedQuantity ?? null,
          eventPlannedQuantity: item.eventPlannedQuantity ?? null,
          quantityLabel: trimOrNull(item.quantityLabel),
          recipeId: trimOrNull(item.recipeId),
          recipeVersionId: trimOrNull(item.recipeVersionId),
        }))
      ),
      name: section.name?.trim() ?? "",
    }))
  );

  return {
    ...values,
    description: trimOrNull(values.description),
    eventId: trimOrNull(values.eventId),
    itemCount: sections.reduce((total, section) => total + section.items.length, 0),
    metadata: values.metadata ?? null,
    name: values.name.trim(),
    recipeCount: sections.reduce(
      (total, section) =>
        total + section.items.filter((item) => Boolean(item.recipeId || item.recipe?.id)).length,
      0
    ),
    sectionCount: sections.length,
    sections,
    summary: trimOrNull(values.summary),
    type: trimOrNull(values.type),
  };
}

export function validateMenuEditorValues(
  values: MenuEditorValues,
  t: (key: string) => string
): MenuEditorValidationErrors {
  const errors: MenuEditorValidationErrors = {};

  if (!values.name.trim()) {
    errors.name = t("menus.form.errors.nameRequired");
  }

  const sectionErrors = values.sections.reduce<Record<string, MenuSectionValidationErrors>>(
    (result, section) => {
      const currentSectionErrors: MenuSectionValidationErrors = {};

      if (!section.name?.trim()) {
        currentSectionErrors.name = t("menus.form.errors.sectionNameRequired");
      }

      const itemErrors = section.items.reduce<Record<string, MenuItemValidationErrors>>(
        (itemsResult, item) => {
          if (!item.name.trim()) {
            itemsResult[getMenuItemKey(item)] = {
              name: t("menus.form.errors.itemNameRequired"),
            };
          }

          return itemsResult;
        },
        {}
      );

      if (Object.keys(itemErrors).length > 0) {
        currentSectionErrors.items = itemErrors;
      }

      if (currentSectionErrors.name || currentSectionErrors.items) {
        result[getMenuSectionKey(section)] = currentSectionErrors;
      }

      return result;
    },
    {}
  );

  if (Object.keys(sectionErrors).length > 0) {
    errors.sections = sectionErrors;
  }

  return errors;
}

export function hasMenuEditorErrors(errors?: MenuEditorValidationErrors | null) {
  if (!errors) {
    return false;
  }

  return Boolean(
    errors.form ||
      errors.name ||
      errors.description ||
      errors.status ||
      errors.eventId ||
      (errors.sections && Object.keys(errors.sections).length > 0)
  );
}

export type MenuRecipeOption = EntityPickerOption<string> & {
  currentVersionId?: string | null;
};
export type MenuEventOption = EntityPickerOption<string>;

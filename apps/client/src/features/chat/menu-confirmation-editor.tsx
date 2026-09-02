import { router } from "expo-router";
import { useMemo, useState } from "react";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { CardHeader } from "@/components/primitives/card-header";
import { Chip } from "@/components/primitives/chip";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { SearchInput } from "@/components/primitives/search-input";
import { Select } from "@/components/primitives/select";
import { Text } from "@/components/primitives/text";
import { TextField } from "@/components/primitives/text-field";
import { useRecipes } from "@/features/recipes";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EditableMenuItem = {
  description?: string | null;
  metadata?: Record<string, unknown>;
  name: string;
  notes?: string | null;
  approved_quantity: number | null;
  quantity_per_guest: number | null;
  quantity_suggestion: number | null;
  recipe_id: string | null;
  recipe_version_id: string | null;
  serving_unit: string | null;
  serving_unit_suggestion: string | null;
};

export type EditableMenuSection = {
  items: EditableMenuItem[];
  name: string;
  type?: string | null;
};

export type EditableMenu = {
  excluded_items?: string[];
  name: string;
  requested_guest_count?: number | null;
  sections: EditableMenuSection[];
  source?: Record<string, unknown>;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" ? (value as Record<string, unknown>) : null;
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

export function coerceEditableMenu(value: unknown): EditableMenu | null {
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
      const metadata = asRecord(item?.metadata);

      if (!item || typeof item.name !== "string") {
        return itemsResult;
      }

      const quantity =
        typeof item.approved_quantity === "number"
          ? item.approved_quantity
          : typeof item.quantity_per_guest === "number"
            ? item.quantity_per_guest
            : null;

      itemsResult.push({
        description: readString(item.description),
        metadata: metadata ?? undefined,
        name: item.name,
        notes: readString(item.notes),
        approved_quantity: quantity,
        quantity_per_guest: quantity,
        quantity_suggestion:
          typeof item.quantity_suggestion === "number"
            ? item.quantity_suggestion
            : typeof metadata?.quantity_suggestion === "number"
              ? metadata.quantity_suggestion
              : null,
        recipe_id: readString(item.recipe_id),
        recipe_version_id: readString(item.recipe_version_id),
        serving_unit: readString(item.serving_unit),
        serving_unit_suggestion:
          readString(item.serving_unit_suggestion) ?? readString(metadata?.serving_unit_suggestion),
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

type MenuItemStatus = "matched" | "missing_recipe" | "needs_review";
type MenuReviewFilter = "all" | "missing_recipe" | "needs_review" | "matched";
type MenuRecipeOption = EntityPickerOption<string> & { recipeVersionId?: string | null };

function recipeSuggestion(item: EditableMenuItem) {
  return asRecord(item.metadata?.recipe_suggestion);
}

function suggestionRecipeId(item: EditableMenuItem) {
  return readString(recipeSuggestion(item)?.recipe_id);
}

function suggestionVersionId(item: EditableMenuItem) {
  return readString(recipeSuggestion(item)?.recipe_version_id);
}

function suggestionName(item: EditableMenuItem) {
  return readString(recipeSuggestion(item)?.name);
}

function getItemStatus(item: EditableMenuItem): MenuItemStatus {
  const suggestedId = suggestionRecipeId(item);

  if (suggestedId && suggestedId !== item.recipe_id) {
    return "needs_review";
  }

  return item.recipe_id ? "matched" : "missing_recipe";
}

function itemHasPendingSuggestion(item: EditableMenuItem) {
  const suggestedId = suggestionRecipeId(item);
  const suggestionStatus = readString(recipeSuggestion(item)?.status);

  return Boolean(
    suggestedId &&
      suggestedId !== item.recipe_id &&
      suggestionStatus !== "ambiguous" &&
      suggestionStatus !== "conflict" &&
      suggestionStatus !== "rejected"
  );
}

function MenuDraftSummary({
  counts,
}: {
  counts: { linked: number; missing: number; needsReview: number; sections: number };
}) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const metrics = [
    [t("chat.operations.menuConfirmation.summary.linkedRecipes"), counts.linked],
    [t("chat.operations.menuConfirmation.summary.missingRecipes"), counts.missing],
    [t("chat.operations.menuConfirmation.summary.needsReview"), counts.needsReview],
    [t("chat.operations.menuConfirmation.summary.sections"), counts.sections],
  ] as const;

  return (
    <View style={{ gap: theme.spacing[3] }}>
      <CardHeader
        padding="none"
        subtitle={t("chat.operations.menuConfirmation.description")}
        title={t("chat.operations.menuConfirmation.title")}
        trailing={<Badge label={t("chat.operations.menuConfirmation.draft")} size="sm" variant="neutral" />}
      />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        {metrics.map(([label, value]) => (
          <View
            key={label}
            style={{
              backgroundColor: theme.colors.background.surface,
              borderColor: theme.colors.border.default,
              borderRadius: theme.radius.md,
              borderWidth: 1,
              flex: 1,
              gap: theme.spacing[1],
              minWidth: 100,
              padding: theme.spacing[2],
            }}
          >
            <Text tone="secondary" variant="caption">{label}</Text>
            <Text selectable variant="bodySmall" style={{ fontSize: 18, fontVariant: ["tabular-nums"], fontWeight: "700" }}>{value}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

function MenuSectionTabs({
  sections,
  selectedIndex,
  onSelect,
}: {
  sections: EditableMenuSection[];
  selectedIndex: number;
  onSelect: (index: number) => void;
}) {
  return (
    <View accessibilityRole="tablist" style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
      {sections.map((section, index) => (
        <Chip
          key={`${section.name}-${index}`}
          accessibilityRole="tab"
          accessibilityState={{ selected: selectedIndex === index }}
          label={`${section.name} (${section.items.length})`}
          onPress={() => onSelect(index)}
          selected={selectedIndex === index}
          size="sm"
        />
      ))}
    </View>
  );
}

function MenuReviewToolbar({
  filter,
  missingCount,
  pendingSuggestionCount,
  search,
  onAcceptSuggestions,
  onCreateRecipe,
  onFilterChange,
  onSearchChange,
}: {
  filter: MenuReviewFilter;
  missingCount: number;
  pendingSuggestionCount: number;
  search: string;
  onAcceptSuggestions: () => void;
  onCreateRecipe: () => void;
  onFilterChange: (filter: MenuReviewFilter) => void;
  onSearchChange: (search: string) => void;
}) {
  const { t } = useTranslation("common");
  const filters: { key: MenuReviewFilter; label: string }[] = [
    { key: "all", label: t("chat.operations.menuConfirmation.filters.all") },
    { key: "missing_recipe", label: t("chat.operations.menuConfirmation.filters.missing") },
    { key: "needs_review", label: t("chat.operations.menuConfirmation.filters.needsReview") },
    { key: "matched", label: t("chat.operations.menuConfirmation.filters.matched") },
  ];

  return (
    <View style={{ gap: 8 }}>
      <SearchInput
        accessibilityLabel={t("chat.operations.menuConfirmation.search")}
        onChangeText={onSearchChange}
        placeholder={t("chat.operations.menuConfirmation.search")}
        value={search}
      />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
        {filters.map((option) => (
          <Chip
            key={option.key}
            label={option.label}
            onPress={() => onFilterChange(option.key)}
            selected={filter === option.key}
            size="sm"
          />
        ))}
      </View>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
        {pendingSuggestionCount > 0 ? (
          <Button
            label={t("chat.operations.menuConfirmation.acceptSuggestions")}
            onPress={onAcceptSuggestions}
            size="sm"
            variant="secondary"
          />
        ) : null}
        {missingCount > 0 ? (
          <Button
            label={t("chat.operations.menuConfirmation.createRecipe")}
            onPress={onCreateRecipe}
            size="sm"
            variant="ghost"
          />
        ) : null}
      </View>
    </View>
  );
}

function MenuItemStatusBadge({ status }: { status: MenuItemStatus }) {
  const { t } = useTranslation("common");
  const statusConfig: Record<MenuItemStatus, { label: string; variant: "success" | "warning" | "danger" }> = {
    matched: { label: t("chat.operations.menuConfirmation.status.matched"), variant: "success" },
    missing_recipe: { label: t("chat.operations.menuConfirmation.status.missingRecipe"), variant: "danger" },
    needs_review: { label: t("chat.operations.menuConfirmation.status.needsReview"), variant: "warning" },
  };
  const config = statusConfig[status];

  return <Badge label={config.label} size="sm" variant={config.variant} />;
}

function MenuItemInlineEditor({
  item,
  onCancel,
  onSave,
}: {
  item: EditableMenuItem;
  onCancel: () => void;
  onSave: (item: EditableMenuItem) => void;
}) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [notes, setNotes] = useState(item.notes ?? "");

  return (
    <View
      style={{
        backgroundColor: theme.colors.background.subtle,
        borderColor: theme.colors.border.default,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        gap: theme.spacing[2],
        padding: theme.spacing[3],
      }}
    >
      <Text variant="label">{t("chat.operations.menuConfirmation.advancedFields")}</Text>
      <TextField
        accessibilityLabel={t("chat.operations.menuConfirmation.notes")}
        label={t("chat.operations.menuConfirmation.notes")}
        multiline
        onChangeText={setNotes}
        value={notes}
      />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        <Button label={t("chat.operations.menuConfirmation.cancel")} onPress={onCancel} size="sm" variant="ghost" />
        <Button
          label={t("chat.operations.menuConfirmation.saveChanges")}
          onPress={() => onSave({ ...item, notes: notes.trim() || null })}
          size="sm"
          variant="secondary"
        />
      </View>
    </View>
  );
}

function MenuConfirmationItemRow({
  expanded,
  item,
  itemOptions,
  recipeLoading,
  requestedGuestCount,
  servingUnitOptions,
  onChange,
  onCreateRecipe,
  onExpand,
}: {
  expanded: boolean;
  item: EditableMenuItem;
  itemOptions: MenuRecipeOption[];
  recipeLoading: boolean;
  requestedGuestCount?: number | null;
  servingUnitOptions: { label: string; value: string }[];
  onChange: (item: EditableMenuItem) => void;
  onCreateRecipe: () => void;
  onExpand: () => void;
}) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getItemStatus(item);
  const quantityValue = item.approved_quantity?.toString() ?? "";
  const updateQuantity = (value: string) => {
    const trimmed = value.trim();
    const parsed = trimmed === "" ? null : Number(trimmed);
    const quantity = parsed === null || Number.isFinite(parsed) ? parsed : null;
    onChange({ ...item, approved_quantity: quantity, quantity_per_guest: quantity });
  };
  const previewTotal = item.approved_quantity !== null && requestedGuestCount
    ? item.approved_quantity * requestedGuestCount
    : null;

  return (
    <View
      style={{
        backgroundColor: theme.colors.background.surface,
        borderColor: status === "needs_review" ? theme.colors.status.warning : theme.colors.border.default,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        gap: theme.spacing[2],
        padding: theme.spacing[3],
      }}
    >
      <View style={{ alignItems: "center", flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 150 }}>
          <Text selectable variant="label">{item.name}</Text>
          {item.description ? <Text tone="secondary" variant="caption">{item.description}</Text> : null}
        </View>
        <MenuItemStatusBadge status={status} />
        <Button
          accessibilityLabel={expanded ? t("chat.operations.menuConfirmation.collapse") : t("chat.operations.menuConfirmation.expand")}
          label={expanded ? t("chat.operations.menuConfirmation.collapse") : t("chat.operations.menuConfirmation.expand")}
          onPress={onExpand}
          size="sm"
          variant="ghost"
        />
      </View>

      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        <View style={{ flex: 1, minWidth: 105 }}>
          <TextField
            accessibilityLabel={t("chat.operations.menuConfirmation.quantityPerGuest")}
            keyboardType="decimal-pad"
            label={t("chat.operations.menuConfirmation.quantityPerGuest")}
            onChangeText={updateQuantity}
            value={quantityValue}
          />
        </View>
        <View style={{ flex: 1, minWidth: 105 }}>
          {servingUnitOptions.length ? (
            <Select
              accessibilityLabel={t("chat.operations.menuConfirmation.servingUnit")}
              label={t("chat.operations.menuConfirmation.servingUnit")}
              onChange={(serving_unit) => onChange({ ...item, serving_unit })}
              options={servingUnitOptions}
              placeholder={t("menus.form.fields.servingUnit.placeholder")}
              value={item.serving_unit ?? undefined}
            />
          ) : (
            <TextField
              accessibilityLabel={t("chat.operations.menuConfirmation.servingUnit")}
              label={t("chat.operations.menuConfirmation.servingUnit")}
              onChangeText={(serving_unit) => onChange({ ...item, serving_unit: serving_unit.trim() || null })}
              value={item.serving_unit ?? ""}
            />
          )}
        </View>
        <View style={{ flex: 2, minWidth: 180 }}>
          <Text tone="secondary" variant="caption">{t("chat.operations.menuConfirmation.recipe")}</Text>
          <EntityPicker
            accessibilityLabel={t("chat.operations.menuConfirmation.recipe")}
            disabled={recipeLoading && itemOptions.length === 0}
            entities={itemOptions}
            onChange={(recipe_id) => {
              const recipe = itemOptions.find((option) => option.value === recipe_id);
              onChange({ ...item, recipe_id, recipe_version_id: recipe?.recipeVersionId ?? null });
            }}
            placeholder={t("menus.form.fields.recipe.placeholder")}
            value={item.recipe_id ?? undefined}
          />
          {recipeLoading ? <Text tone="muted" variant="caption">{t("chat.operations.menuConfirmation.recipeLoading")}</Text> : null}
          {status === "missing_recipe" ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[1] }}>
              <Button label={t("chat.operations.menuConfirmation.createRecipe")} onPress={onCreateRecipe} size="sm" variant="ghost" />
            </View>
          ) : null}
          {item.recipe_id ? (
            <Button
              label={t("menus.actions.removeRecipe")}
              onPress={() => onChange({ ...item, recipe_id: null, recipe_version_id: null })}
              size="sm"
              variant="ghost"
            />
          ) : null}
        </View>
      </View>

      {previewTotal !== null ? (
        <Text tone="secondary" variant="caption">
          {t("chat.operations.menuConfirmation.previewTotal", {
            count: requestedGuestCount,
            total: previewTotal,
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
            onPress={() => onChange({
              ...item,
              approved_quantity: item.quantity_suggestion,
              quantity_per_guest: item.quantity_suggestion,
              serving_unit: item.serving_unit_suggestion,
            })}
            size="sm"
            variant="ghost"
          />
        </View>
      ) : null}

      {expanded ? (
        <MenuItemInlineEditor
          item={item}
          onCancel={onExpand}
          onSave={(nextItem) => {
            onChange(nextItem);
            onExpand();
          }}
        />
      ) : null}
    </View>
  );
}

function MenuSmartRecommendations({
  menu,
  onCreateRecipe,
  onReviewMatch,
  onStandardizeUnits,
}: {
  menu: EditableMenu;
  onCreateRecipe: (sectionIndex: number, itemIndex: number) => void;
  onReviewMatch: (sectionIndex: number, itemIndex: number) => void;
  onStandardizeUnits: () => void;
}) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const recommendations = menu.sections.flatMap((section, sectionIndex) =>
    section.items.flatMap((item, itemIndex) => {
      const result: { kind: "match" | "missing"; sectionIndex: number; itemIndex: number; item: EditableMenuItem }[] = [];

      if (itemHasPendingSuggestion(item)) {
        result.push({ kind: "match", sectionIndex, itemIndex, item });
      } else if (!item.recipe_id) {
        result.push({ kind: "missing", sectionIndex, itemIndex, item });
      }

      return result;
    })
  );
  const unitRecommendationCount = menu.sections.reduce(
    (count, section) => count + section.items.filter((item) => item.serving_unit_suggestion && item.serving_unit !== item.serving_unit_suggestion).length,
    0
  );

  if (!recommendations.length && unitRecommendationCount === 0) {
    return null;
  }

  return (
    <View style={{ gap: theme.spacing[2] }}>
      <Text variant="label">{t("chat.operations.menuConfirmation.smartRecommendations")}</Text>
      {recommendations.map((recommendation) => (
        <View
          key={`${recommendation.kind}-${recommendation.sectionIndex}-${recommendation.itemIndex}`}
          style={{
            backgroundColor: theme.colors.background.subtle,
            borderColor: theme.colors.border.default,
            borderRadius: theme.radius.md,
            borderWidth: 1,
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[2],
            justifyContent: "space-between",
            padding: theme.spacing[3],
          }}
        >
          <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 150 }}>
            <Text variant="bodySmall">
              {recommendation.kind === "match"
                ? t("chat.operations.menuConfirmation.recommendations.autoMatch", { item: recommendation.item.name, recipe: suggestionName(recommendation.item) ?? t("chat.operations.menuConfirmation.suggestedRecipe") })
                : t("chat.operations.menuConfirmation.recommendations.missingRecipe", { item: recommendation.item.name })}
            </Text>
            {recommendation.kind === "match" ? <MenuItemStatusBadge status="needs_review" /> : <MenuItemStatusBadge status="missing_recipe" />}
          </View>
          <Button
            label={recommendation.kind === "match" ? t("chat.operations.menuConfirmation.reviewMatch") : t("chat.operations.menuConfirmation.createRecipe")}
            onPress={() => recommendation.kind === "match"
              ? onReviewMatch(recommendation.sectionIndex, recommendation.itemIndex)
              : onCreateRecipe(recommendation.sectionIndex, recommendation.itemIndex)}
            size="sm"
            variant="ghost"
          />
        </View>
      ))}
      {unitRecommendationCount > 0 ? (
        <View style={{ gap: theme.spacing[1] }}>
          <Text variant="bodySmall">{t("chat.operations.menuConfirmation.recommendations.standardizeUnits", { count: unitRecommendationCount })}</Text>
          <Button label={t("chat.operations.menuConfirmation.applyToItems", { count: unitRecommendationCount })} onPress={onStandardizeUnits} size="sm" variant="ghost" />
        </View>
      ) : null}
    </View>
  );
}

export function MenuConfirmationEditor({
  menu,
  onChange,
}: {
  menu: EditableMenu;
  onChange: (menu: EditableMenu) => void;
}) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const recipesQuery = useRecipes({ perPage: 100 });
  const [originalDraft] = useState(menu);
  const [workingDraft, setWorkingDraft] = useState(menu);
  const [activeSectionIndex, setActiveSectionIndex] = useState(0);
  const [expandedItemKey, setExpandedItemKey] = useState<string | null>(null);
  const [filter, setFilter] = useState<MenuReviewFilter>("all");
  const [search, setSearch] = useState("");

  const recipeOptions = useMemo<MenuRecipeOption[]>(
    () => recipesQuery.recipes.map((recipe) => ({
      label: recipe.currentVersionRecord?.version
        ? `${recipe.name} v${recipe.currentVersionRecord.version}`
        : recipe.name,
      recipeVersionId: recipe.currentVersionId ?? recipe.currentVersionRecord?.id ?? null,
      value: recipe.id,
    })),
    [recipesQuery.recipes]
  );
  const servingUnitOptions = useMemo(() => {
    const values = new Map<string, string>();
    recipesQuery.catalog?.units.forEach((unit) => {
      const value = unit.key ?? unit.symbol ?? unit.name;
      if (value) values.set(value, unit.name && unit.name !== value ? `${unit.name} (${value})` : value);
    });
    workingDraft.sections.forEach((section) => section.items.forEach((item) => {
      [item.serving_unit, item.serving_unit_suggestion].forEach((value) => {
        if (value && !values.has(value)) values.set(value, value);
      });
    }));
    return Array.from(values, ([value, label]) => ({ label, value }));
  }, [recipesQuery.catalog?.units, workingDraft.sections]);
  const allItems = workingDraft.sections.flatMap((section) => section.items);
  const counts = {
    linked: allItems.filter((item) => Boolean(item.recipe_id)).length,
    missing: allItems.filter((item) => !item.recipe_id).length,
    needsReview: allItems.filter((item) => getItemStatus(item) === "needs_review").length,
    sections: workingDraft.sections.length,
  };
  const pendingSuggestionCount = allItems.filter(itemHasPendingSuggestion).length;
  const dirtyChanges = JSON.stringify(originalDraft) !== JSON.stringify(workingDraft);
  const activeSection = workingDraft.sections[activeSectionIndex];
  const normalizedSearch = search.trim().toLowerCase();

  const commitDraft = (nextDraft: EditableMenu) => {
    setWorkingDraft(nextDraft);
    onChange(nextDraft);
  };
  const updateItem = (sectionIndex: number, itemIndex: number, nextItem: EditableMenuItem) => {
    commitDraft({
      ...workingDraft,
      sections: workingDraft.sections.map((section, currentSectionIndex) =>
        currentSectionIndex === sectionIndex
          ? { ...section, items: section.items.map((item, currentItemIndex) => currentItemIndex === itemIndex ? nextItem : item) }
          : section
      ),
    });
  };
  const createRecipe = () => router.push(routes.app.recipeCreate);
  const getItemOptions = (item: EditableMenuItem): MenuRecipeOption[] => {
    const options = [...recipeOptions];
    const suggestedId = suggestionRecipeId(item);
    const currentId = item.recipe_id;

    if (suggestedId && !options.some((option) => option.value === suggestedId)) {
      options.unshift({
        label: suggestionName(item) ?? t("chat.operations.menuConfirmation.suggestedRecipe"),
        recipeVersionId: suggestionVersionId(item),
        value: suggestedId,
      });
    }
    if (currentId && !options.some((option) => option.value === currentId)) {
      options.unshift({ label: t("chat.operations.menuConfirmation.selectedRecipe"), recipeVersionId: item.recipe_version_id, value: currentId });
    }
    return options;
  };
  const visibleItems = (activeSection?.items ?? []).map((item, itemIndex) => ({ item, itemIndex })).filter(({ item }) => {
    const status = getItemStatus(item);
    const matchesFilter = filter === "all"
      || (filter === "missing_recipe" ? !item.recipe_id : status === filter);
    if (!matchesFilter) return false;
    if (!normalizedSearch) return true;
    const suggested = suggestionName(item) ?? "";
    const recipe = recipeOptions.find((option) => option.value === item.recipe_id)?.label ?? "";
    return `${item.name} ${recipe} ${suggested}`.toLowerCase().includes(normalizedSearch);
  });

  return (
    <BaseCard padding="md" radius="lg" variant="muted">
      <View style={{ gap: theme.spacing[3] }}>
        <MenuDraftSummary counts={counts} />
        {workingDraft.sections.length ? (
          <MenuSectionTabs
            onSelect={(index) => {
              setActiveSectionIndex(index);
              setExpandedItemKey(null);
            }}
            sections={workingDraft.sections}
            selectedIndex={Math.min(activeSectionIndex, workingDraft.sections.length - 1)}
          />
        ) : (
          <Text tone="muted" variant="bodySmall">{t("chat.operations.menuConfirmation.emptySections")}</Text>
        )}
        <MenuReviewToolbar
          filter={filter}
          missingCount={counts.missing}
          onAcceptSuggestions={() => {
            const nextSections = workingDraft.sections.map((section) => ({
              ...section,
              items: section.items.map((item) => {
                if (!itemHasPendingSuggestion(item)) return item;
                const recipeId = suggestionRecipeId(item);
                if (!recipeId) return item;
                return { ...item, recipe_id: recipeId, recipe_version_id: suggestionVersionId(item) };
              }),
            }));
            commitDraft({ ...workingDraft, sections: nextSections });
          }}
          onCreateRecipe={createRecipe}
          onFilterChange={setFilter}
          onSearchChange={setSearch}
          pendingSuggestionCount={pendingSuggestionCount}
          search={search}
        />
        {activeSection && visibleItems.length ? (
          <View style={{ gap: theme.spacing[2] }}>
            {visibleItems.map(({ item, itemIndex }) => {
              const itemKey = `${activeSectionIndex}:${itemIndex}`;
              return (
                <MenuConfirmationItemRow
                  key={itemKey}
                  expanded={expandedItemKey === itemKey}
                  item={item}
                  itemOptions={getItemOptions(item)}
                  onChange={(nextItem) => updateItem(activeSectionIndex, itemIndex, nextItem)}
                  onCreateRecipe={createRecipe}
                  onExpand={() => setExpandedItemKey(expandedItemKey === itemKey ? null : itemKey)}
                  recipeLoading={recipesQuery.isPending}
                  requestedGuestCount={workingDraft.requested_guest_count}
                  servingUnitOptions={servingUnitOptions}
                />
              );
            })}
          </View>
        ) : (
          <Text tone="muted" variant="bodySmall">
            {activeSection ? t("chat.operations.menuConfirmation.noMatchingItems") : t("chat.operations.menuConfirmation.emptySections")}
          </Text>
        )}
        {recipesQuery.isError ? <Text tone="danger" variant="caption">{t("chat.operations.menuConfirmation.recipeLoadFailed")}</Text> : null}
        <MenuSmartRecommendations
          menu={workingDraft}
          onCreateRecipe={createRecipe}
          onReviewMatch={(sectionIndex, itemIndex) => {
            setActiveSectionIndex(sectionIndex);
            setExpandedItemKey(`${sectionIndex}:${itemIndex}`);
          }}
          onStandardizeUnits={() => {
            commitDraft({
              ...workingDraft,
              sections: workingDraft.sections.map((section) => ({
                ...section,
                items: section.items.map((item) => item.serving_unit_suggestion && item.serving_unit !== item.serving_unit_suggestion
                  ? { ...item, serving_unit: item.serving_unit_suggestion }
                  : item),
              })),
            });
          }}
        />
        {dirtyChanges ? <Text tone="secondary" variant="caption">{t("chat.operations.menuConfirmation.draftChangesPending")}</Text> : null}
      </View>
    </BaseCard>
  );
}

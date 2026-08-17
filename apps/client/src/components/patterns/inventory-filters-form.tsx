import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/primitives/button";
import { Checkbox } from "@/components/primitives/checkbox";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { MultiSelect } from "@/components/primitives/multi-select";
import { SearchInput } from "@/components/primitives/search-input";
import {
  createEmptyInventoryFilters,
  INVENTORY_FILTER_STATUS_VALUES,
  normalizeInventoryFilters,
  type InventoryFilters,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

type FilterCellProps = {
  children: React.ReactNode;
  fullWidth?: boolean;
};

function FilterCell({ children, fullWidth = false }: FilterCellProps) {
  return (
    <View
      style={{
        flexBasis: fullWidth ? "100%" : 220,
        flexGrow: 1,
        minWidth: fullWidth ? "100%" : 220,
      }}
    >
      {children}
    </View>
  );
}

export type InventoryFiltersFormProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  filters?: InventoryFilters;
  locationOptions?: EntityPickerOption<string>[];
  onChange: (filters: InventoryFilters) => void;
  onReset?: () => void;
  supplierOptions?: EntityPickerOption<string>[];
};

export function InventoryFiltersForm({
  accessibilityLabel,
  compact = false,
  disabled = false,
  filters,
  locationOptions,
  onChange,
  onReset,
  supplierOptions,
}: InventoryFiltersFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedFilters = normalizeInventoryFilters(filters);
  const updateFilters = (nextFilters: Partial<InventoryFilters>) => {
    onChange({
      ...resolvedFilters,
      ...nextFilters,
    });
  };

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("inventory.filters.accessibilityLabel")}
      style={{ gap: compact ? theme.spacing[3] : theme.spacing[4] }}
    >
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
        <FilterCell fullWidth>
          <SearchInput
            accessibilityLabel={t("inventory.filters.search.accessibilityLabel")}
            editable={!disabled}
            onChangeText={(search) => updateFilters({ search })}
            placeholder={t("inventory.filters.search.placeholder")}
            value={resolvedFilters.search}
          />
        </FilterCell>
        <FilterCell>
          <MultiSelect
            accessibilityLabel={t("inventory.filters.statuses.accessibilityLabel")}
            disabled={disabled}
            label={t("inventory.filters.statuses.label")}
            onChange={(statuses) => updateFilters({ statuses })}
            options={INVENTORY_FILTER_STATUS_VALUES.map((status) => ({
              label: t(`inventory.status.${status}`),
              value: status,
            }))}
            placeholder={t("inventory.filters.statuses.placeholder")}
            values={resolvedFilters.statuses}
          />
        </FilterCell>
        {locationOptions?.length ? (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("inventory.filters.location.accessibilityLabel")}
              disabled={disabled}
              entities={locationOptions}
              label={t("inventory.filters.location.label")}
              onChange={(locationId) => updateFilters({ locationId })}
              placeholder={t("inventory.filters.location.placeholder")}
              value={resolvedFilters.locationId ?? undefined}
            />
          </FilterCell>
        ) : null}
        {supplierOptions?.length ? (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("inventory.filters.supplier.accessibilityLabel")}
              disabled={disabled}
              entities={supplierOptions}
              label={t("inventory.filters.supplier.label")}
              onChange={(supplierId) => updateFilters({ supplierId })}
              placeholder={t("inventory.filters.supplier.placeholder")}
              value={resolvedFilters.supplierId ?? undefined}
            />
          </FilterCell>
        ) : null}
        <FilterCell>
          <Checkbox
            accessibilityLabel={t("inventory.filters.lowStock.accessibilityLabel")}
            checked={resolvedFilters.lowStockOnly}
            disabled={disabled}
            label={t("inventory.filters.lowStock.label")}
            onChange={(lowStockOnly) => updateFilters({ lowStockOnly })}
          />
        </FilterCell>
        <FilterCell>
          <Checkbox
            accessibilityLabel={t("inventory.filters.outOfStock.accessibilityLabel")}
            checked={resolvedFilters.outOfStockOnly}
            disabled={disabled}
            label={t("inventory.filters.outOfStock.label")}
            onChange={(outOfStockOnly) => updateFilters({ outOfStockOnly })}
          />
        </FilterCell>
        <FilterCell>
          <Checkbox
            accessibilityLabel={t("inventory.filters.activeOnly.accessibilityLabel")}
            checked={resolvedFilters.activeOnly}
            disabled={disabled}
            label={t("inventory.filters.activeOnly.label")}
            onChange={(activeOnly) => updateFilters({ activeOnly })}
          />
        </FilterCell>
      </View>
      <View style={{ flexDirection: "row", justifyContent: "flex-end" }}>
        <Button
          accessibilityLabel={t("inventory.filters.actions.clear")}
          disabled={disabled}
          label={t("inventory.filters.actions.clear")}
          onPress={() => {
            onReset?.();
            onChange(createEmptyInventoryFilters());
          }}
          variant="ghost"
        />
      </View>
    </View>
  );
}

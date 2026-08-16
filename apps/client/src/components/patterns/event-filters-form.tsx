import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { MultiSelect } from "@/components/primitives/multi-select";
import { SearchInput } from "@/components/primitives/search-input";
import { Select } from "@/components/primitives/select";
import { Button } from "@/components/primitives/button";
import { UserPicker, type UserPickerOption } from "@/components/primitives/user-picker";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  createEmptyEventFilters,
  resolveTranslatedOptionLabel,
  type EventFilters,
  type TranslatedSelectOption,
  EVENT_STATUS_VALUES,
} from "@/features/events/forms";

export type EventFiltersFormProps = {
  accessibilityLabel?: string;
  clientOptions?: EntityPickerOption<string>[];
  compact?: boolean;
  disabled?: boolean;
  filters?: EventFilters;
  memberOptions?: UserPickerOption<string>[];
  onChange: (filters: EventFilters) => void;
  onReset?: () => void;
  serviceTypeOptions?: TranslatedSelectOption[];
  timeZone?: string;
  venueOptions?: EntityPickerOption<string>[];
};

function FilterCell({
  children,
  fullWidth = false,
}: {
  children: React.ReactNode;
  fullWidth?: boolean;
}) {
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

export function EventFiltersForm({
  accessibilityLabel,
  clientOptions,
  compact = false,
  disabled = false,
  filters,
  memberOptions,
  onChange,
  onReset,
  serviceTypeOptions,
  timeZone,
  venueOptions,
}: EventFiltersFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedFilters = { ...createEmptyEventFilters(), ...filters };
  const resolvedTimeZone =
    timeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC";
  const serviceOptions = (serviceTypeOptions ?? []).map((option) => ({
    disabled: option.disabled,
    label: resolveTranslatedOptionLabel(option, t),
    value: option.value,
  }));
  const updateFilters = (nextFilters: Partial<EventFilters>) => {
    onChange({
      ...resolvedFilters,
      ...nextFilters,
    });
  };

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("events.filters.accessibilityLabel")}
      style={{ gap: compact ? theme.spacing[3] : theme.spacing[4] }}
    >
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
        <FilterCell fullWidth>
          <SearchInput
            accessibilityLabel={t("events.filters.search.accessibilityLabel")}
            editable={!disabled}
            onChangeText={(search) => updateFilters({ search })}
            placeholder={t("events.filters.search.placeholder")}
            value={resolvedFilters.search}
          />
        </FilterCell>
        <FilterCell>
          <MultiSelect
            accessibilityLabel={t("events.filters.statuses.accessibilityLabel")}
            disabled={disabled}
            label={t("events.filters.statuses.label")}
            onChange={(statuses) => updateFilters({ statuses })}
            options={EVENT_STATUS_VALUES.map((value) => ({
              label: t(`events.status.${value}`),
              value,
            }))}
            placeholder={t("events.filters.statuses.placeholder")}
            values={resolvedFilters.statuses ?? []}
          />
        </FilterCell>
        <FilterCell>
          <DateTimeField
            accessibilityLabel={t("events.filters.dateFrom.accessibilityLabel")}
            editable={!disabled}
            label={t("events.filters.dateFrom.label")}
            onChange={(dateFrom) => updateFilters({ dateFrom })}
            timeZone={resolvedTimeZone}
            value={resolvedFilters.dateFrom}
          />
        </FilterCell>
        <FilterCell>
          <DateTimeField
            accessibilityLabel={t("events.filters.dateTo.accessibilityLabel")}
            editable={!disabled}
            label={t("events.filters.dateTo.label")}
            onChange={(dateTo) => updateFilters({ dateTo })}
            timeZone={resolvedTimeZone}
            value={resolvedFilters.dateTo}
          />
        </FilterCell>
        {!venueOptions?.length ? null : (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("events.filters.venue.accessibilityLabel")}
              disabled={disabled}
              entities={venueOptions}
              label={t("events.filters.venue.label")}
              onChange={(venueId) => updateFilters({ venueId })}
              placeholder={t("events.filters.venue.placeholder")}
              value={resolvedFilters.venueId ?? undefined}
            />
          </FilterCell>
        )}
        {!clientOptions?.length ? null : (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("events.filters.client.accessibilityLabel")}
              disabled={disabled}
              entities={clientOptions}
              label={t("events.filters.client.label")}
              onChange={(clientId) => updateFilters({ clientId })}
              placeholder={t("events.filters.client.placeholder")}
              value={resolvedFilters.clientId ?? undefined}
            />
          </FilterCell>
        )}
        {!memberOptions?.length ? null : (
          <FilterCell>
            <UserPicker
              accessibilityLabel={t("events.filters.assignedMember.accessibilityLabel")}
              disabled={disabled}
              label={t("events.filters.assignedMember.label")}
              onChange={(memberId) => updateFilters({ memberId })}
              placeholder={t("events.filters.assignedMember.placeholder")}
              users={memberOptions}
              value={resolvedFilters.memberId ?? undefined}
            />
          </FilterCell>
        )}
        {!serviceOptions.length ? null : (
          <FilterCell>
            <Select
              accessibilityLabel={t("events.filters.serviceType.accessibilityLabel")}
              disabled={disabled}
              label={t("events.filters.serviceType.label")}
              onChange={(serviceType) => updateFilters({ serviceType })}
              options={serviceOptions}
              placeholder={t("events.filters.serviceType.placeholder")}
              value={resolvedFilters.serviceType ?? undefined}
            />
          </FilterCell>
        )}
      </View>
      <View style={{ flexDirection: "row", justifyContent: "flex-end" }}>
        <Button
          accessibilityLabel={t("events.filters.actions.clear")}
          disabled={disabled}
          label={t("events.filters.actions.clear")}
          onPress={() => {
            onReset?.();
            onChange(createEmptyEventFilters());
          }}
          variant="ghost"
        />
      </View>
    </View>
  );
}

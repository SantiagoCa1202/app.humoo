import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/primitives/button";
import { Checkbox } from "@/components/primitives/checkbox";
import { DatePicker } from "@/components/primitives/date-picker";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { MultiSelect } from "@/components/primitives/multi-select";
import { SearchInput } from "@/components/primitives/search-input";
import { UserPicker } from "@/components/primitives/user-picker";
import {
  createEmptyTaskFilters,
  getTaskPriorityMetadata,
  normalizeTaskFilters,
  TASK_PRIORITY_VALUES,
  TASK_STATUS_VALUES,
  type TaskAssignmentOption,
  type TaskEntityOption,
  type TaskFilters,
} from "@/features/tasks";
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

export type TaskFiltersFormProps = {
  accessibilityLabel?: string;
  assigneeOptions?: TaskAssignmentOption[];
  compact?: boolean;
  disabled?: boolean;
  eventOptions?: TaskEntityOption[];
  filters?: TaskFilters;
  onChange: (filters: TaskFilters) => void;
  onReset?: () => void;
  stationOptions?: TaskEntityOption[];
  teamOptions?: TaskEntityOption[];
  timeZone?: string;
};

export function TaskFiltersForm({
  accessibilityLabel,
  assigneeOptions,
  compact = false,
  disabled = false,
  eventOptions,
  filters,
  onChange,
  onReset,
  stationOptions,
  teamOptions,
  timeZone,
}: TaskFiltersFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedFilters = normalizeTaskFilters(filters);
  const resolvedTimeZone =
    timeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC";
  const updateFilters = (nextFilters: Partial<TaskFilters>) => {
    onChange({
      ...resolvedFilters,
      ...nextFilters,
    });
  };

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("tasks.filters.accessibilityLabel")}
      style={{ gap: compact ? theme.spacing[3] : theme.spacing[4] }}
    >
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
        <FilterCell fullWidth>
          <SearchInput
            accessibilityLabel={t("tasks.filters.search.accessibilityLabel")}
            editable={!disabled}
            onChangeText={(search) => updateFilters({ search })}
            placeholder={t("tasks.filters.search.placeholder")}
            value={resolvedFilters.search}
          />
        </FilterCell>
        <FilterCell>
          <MultiSelect
            accessibilityLabel={t("tasks.filters.statuses.accessibilityLabel")}
            disabled={disabled}
            label={t("tasks.filters.statuses.label")}
            onChange={(statuses) => updateFilters({ statuses })}
            options={TASK_STATUS_VALUES.map((status) => ({
              label: t(`tasks.status.${status}`),
              value: status,
            }))}
            placeholder={t("tasks.filters.statuses.placeholder")}
            values={resolvedFilters.statuses}
          />
        </FilterCell>
        <FilterCell>
          <MultiSelect
            accessibilityLabel={t("tasks.filters.priorities.accessibilityLabel")}
            disabled={disabled}
            label={t("tasks.filters.priorities.label")}
            onChange={(priorities) => updateFilters({ priorities })}
            options={TASK_PRIORITY_VALUES.map((priority) => ({
              label: t(getTaskPriorityMetadata(priority)?.translationKey ?? `tasks.priority.${priority}`),
              value: priority,
            }))}
            placeholder={t("tasks.filters.priorities.placeholder")}
            values={resolvedFilters.priorities}
          />
        </FilterCell>
        {assigneeOptions?.length ? (
          <FilterCell>
            <UserPicker
              accessibilityLabel={t("tasks.filters.assignee.accessibilityLabel")}
              disabled={disabled}
              label={t("tasks.filters.assignee.label")}
              onChange={(assigneeId) => updateFilters({ assigneeIds: [assigneeId] })}
              placeholder={t("tasks.filters.assignee.placeholder")}
              users={assigneeOptions}
              value={resolvedFilters.assigneeIds[0] ?? undefined}
            />
          </FilterCell>
        ) : null}
        {eventOptions?.length ? (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("tasks.filters.event.accessibilityLabel")}
              disabled={disabled}
              entities={eventOptions}
              label={t("tasks.filters.event.label")}
              onChange={(eventId) => updateFilters({ eventId })}
              placeholder={t("tasks.filters.event.placeholder")}
              value={resolvedFilters.eventId ?? undefined}
            />
          </FilterCell>
        ) : null}
        {teamOptions?.length ? (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("tasks.filters.team.accessibilityLabel")}
              disabled={disabled}
              entities={teamOptions}
              label={t("tasks.filters.team.label")}
              onChange={(teamId) => updateFilters({ teamId })}
              placeholder={t("tasks.filters.team.placeholder")}
              value={resolvedFilters.teamId ?? undefined}
            />
          </FilterCell>
        ) : null}
        {stationOptions?.length ? (
          <FilterCell>
            <EntityPicker
              accessibilityLabel={t("tasks.filters.station.accessibilityLabel")}
              disabled={disabled}
              entities={stationOptions}
              label={t("tasks.filters.station.label")}
              onChange={(stationId) => updateFilters({ stationId })}
              placeholder={t("tasks.filters.station.placeholder")}
              value={resolvedFilters.stationId ?? undefined}
            />
          </FilterCell>
        ) : null}
        <FilterCell>
          <DatePicker
            accessibilityLabel={t("tasks.filters.dueFrom.accessibilityLabel")}
            disabled={disabled}
            label={t("tasks.filters.dueFrom.label")}
            onChange={(dueFrom) => updateFilters({ dueFrom })}
            timeZone={resolvedTimeZone}
            value={resolvedFilters.dueFrom}
          />
        </FilterCell>
        <FilterCell>
          <DatePicker
            accessibilityLabel={t("tasks.filters.dueTo.accessibilityLabel")}
            disabled={disabled}
            label={t("tasks.filters.dueTo.label")}
            onChange={(dueTo) => updateFilters({ dueTo })}
            timeZone={resolvedTimeZone}
            value={resolvedFilters.dueTo}
          />
        </FilterCell>
        <FilterCell>
          <Checkbox
            accessibilityLabel={t("tasks.filters.overdue.accessibilityLabel")}
            checked={resolvedFilters.overdue}
            disabled={disabled}
            label={t("tasks.filters.overdue.label")}
            onChange={(overdue) => updateFilters({ overdue })}
          />
        </FilterCell>
        <FilterCell>
          <Checkbox
            accessibilityLabel={t("tasks.filters.unassigned.accessibilityLabel")}
            checked={resolvedFilters.unassigned}
            disabled={disabled}
            label={t("tasks.filters.unassigned.label")}
            onChange={(unassigned) => updateFilters({ unassigned })}
          />
        </FilterCell>
      </View>
      <View style={{ flexDirection: "row", justifyContent: "flex-end" }}>
        <Button
          accessibilityLabel={t("tasks.filters.actions.clear")}
          disabled={disabled}
          label={t("tasks.filters.actions.clear")}
          onPress={() => {
            onReset?.();
            onChange(createEmptyTaskFilters());
          }}
          variant="ghost"
        />
      </View>
    </View>
  );
}

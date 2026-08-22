import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppShell } from "@/components/patterns/AppShell";
import { ListItemCard } from "@/components/patterns/ListItemCard";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { TextField } from "@/components/primitives/TextField";
import { useAuditLogs } from "@/features/audit";
import type { AuditLogFilters } from "@/features/audit/types";
import { useAppTheme } from "@/theme/ThemeProvider";

function formatDate(value: string | null, language: string): string {
  if (!value) {
    return "-";
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat(language, { dateStyle: "medium", timeStyle: "short" }).format(date);
}

export default function AuditLogScreen() {
  const { i18n, t } = useTranslation("app");
  const { theme } = useAppTheme();
  const [draftFilters, setDraftFilters] = useState<AuditLogFilters>({});
  const [filters, setFilters] = useState<AuditLogFilters>({});
  const query = useAuditLogs(filters);
  const logs = useMemo(() => query.data?.pages.flatMap((page) => page.data) ?? [], [query.data]);

  return (
    <AppShell title={t("auditTitle")} subtitle={t("auditSubtitle")}>
      <View style={{ gap: theme.spacing[4] }}>
        <SectionCard description={t("auditFiltersDescription")} title={t("auditFiltersTitle")}>
          <View style={{ gap: theme.spacing[3] }}>
            <TextField
              autoCapitalize="none"
              label={t("auditActionFilter")}
              onChangeText={(value) => setDraftFilters((current) => ({ ...current, action: value }))}
              placeholder={t("auditActionPlaceholder")}
              value={draftFilters.action ?? ""}
            />
            <TextField
              autoCapitalize="none"
              label={t("auditEntityTypeFilter")}
              onChangeText={(value) => setDraftFilters((current) => ({ ...current, entityType: value }))}
              placeholder={t("auditEntityTypePlaceholder")}
              value={draftFilters.entityType ?? ""}
            />
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              <AppButton
                label={t("auditApplyFilters")}
                onPress={() => setFilters({ ...draftFilters })}
              />
              <AppButton
                label={t("auditRefresh")}
                onPress={() => void query.refetch()}
                variant="outline"
              />
            </View>
          </View>
        </SectionCard>
        <SectionCard description={t("auditDescription")} title={t("auditLogTitle")}>
          {query.isLoading ? <StateBlock title={t("auditLoading")} tone="loading" /> : null}
          {query.error ? (
            <StateBlock
              actionLabel={t("auditRetry")}
              onAction={() => void query.refetch()}
              title={t("auditError")}
              tone="error"
            />
          ) : null}
          {!query.isLoading && !query.error && logs.length === 0 ? (
            <StateBlock title={t("auditEmpty")} tone="empty" />
          ) : null}
          {logs.map((log) => (
            <ListItemCard
              key={log.id}
              meta={[
                t("auditActor", { actor: log.actor?.name ?? log.actor?.email ?? t("auditSystem") }),
                t("auditDate", { date: formatDate(log.createdAt, i18n.language) }),
              ]}
              title={log.action}
            >
              <AppText muted variant="caption">
                {log.entityType ?? t("auditUnknownEntity")} {log.entityId ? `#${log.entityId}` : ""}
                {log.source ? ` · ${log.source}` : ""}
              </AppText>
            </ListItemCard>
          ))}
          {query.hasNextPage ? (
            <AppButton
              disabled={query.isFetchingNextPage}
              label={t("auditLoadMore")}
              loading={query.isFetchingNextPage}
              onPress={() => void query.fetchNextPage()}
              variant="outline"
            />
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}

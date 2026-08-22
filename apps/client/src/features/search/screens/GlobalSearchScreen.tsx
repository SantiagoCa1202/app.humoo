import { Feather } from "@expo/vector-icons";
import { router } from "expo-router";
import { useMemo, useState } from "react";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppShell } from "@/components/patterns/AppShell";
import { ListItemCard } from "@/components/patterns/ListItemCard";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { AppText } from "@/components/primitives/AppText";
import { SearchInput } from "@/components/primitives/search-input";
import { useWorkspace } from "@/features/workspace";
import {
  getAvailableCommands,
  navigateToSearchResult,
} from "@/features/search/commands";
import { useGlobalSearch } from "@/features/search/hooks";
import type { GlobalSearchResult, GlobalSearchResultType } from "@/features/search/types";
import { useAppTheme } from "@/theme/ThemeProvider";

const entityLabels: Record<GlobalSearchResultType, string> = {
  document: "searchEntityDocument",
  event: "searchEntityEvent",
  menu: "searchEntityMenu",
  prep: "searchEntityPrep",
  recipe: "searchEntityRecipe",
  staff: "searchEntityStaff",
  station: "searchEntityStation",
  task: "searchEntityTask",
  team: "searchEntityTeam",
};

const entityIcons: Record<GlobalSearchResultType, keyof typeof Feather.glyphMap> = {
  document: "file-text",
  event: "calendar",
  menu: "book-open",
  prep: "clipboard",
  recipe: "coffee",
  staff: "user",
  station: "grid",
  task: "check-square",
  team: "users",
};

function groupResults(results: GlobalSearchResult[]) {
  return results.reduce<Partial<Record<GlobalSearchResultType, GlobalSearchResult[]>>>(
    (groups, result) => {
      groups[result.type] = [...(groups[result.type] ?? []), result];
      return groups;
    },
    {}
  );
}

export function GlobalSearchScreen() {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const { hasPermission } = useWorkspace();
  const [input, setInput] = useState("");
  const searchQuery = useGlobalSearch(input);
  const normalizedInput = input.trim();
  const commands = useMemo(
    () => getAvailableCommands(hasPermission, (key) => t(key), normalizedInput),
    [hasPermission, normalizedInput, t]
  );
  const groups = useMemo(
    () => groupResults(searchQuery.data?.results ?? []),
    [searchQuery.data?.results]
  );

  const renderResult = (result: GlobalSearchResult) => (
    <ListItemCard
      key={`${result.type}-${result.id}`}
      accessibilityLabel={result.title}
      onPress={() =>
        navigateToSearchResult(result, (href) => {
          router.push(href as Parameters<typeof router.push>[0]);
        })
      }
      subtitle={result.subtitle ?? undefined}
      title={result.title}
      aside={
        <Feather
          color={theme.colors.text.secondary}
          name={entityIcons[result.type]}
          size={18}
        />
      }
    />
  );

  return (
    <AppShell subtitle={t("globalSearchSubtitle")} title={t("globalSearchTitle")}>
      <View style={{ gap: theme.spacing[5] }}>
        <SearchInput
          accessibilityLabel={t("globalSearchInputAccessibility")}
          autoFocus
          onChangeText={setInput}
          placeholder={t("globalSearchPlaceholder")}
          value={input}
        />

        <SectionCard
          description={t("globalSearchCommandsDescription")}
          title={t("globalSearchCommandsTitle")}
        >
          <View style={{ gap: theme.spacing[2] }}>
            {commands.length ? (
              commands.map((command) => (
                <Pressable
                  key={command.key}
                  accessibilityLabel={t(command.labelKey)}
                  accessibilityRole="button"
                  onPress={() => router.push(command.route)}
                  style={({ pressed }) => ({
                    alignItems: "center",
                    backgroundColor: pressed
                      ? theme.colors.background.pressed
                      : theme.colors.background.subtle,
                    borderRadius: theme.radius.md,
                    flexDirection: "row",
                    gap: theme.spacing[3],
                    padding: theme.spacing[3],
                  })}
                >
                  <Feather color={theme.colors.brand.primary} name={command.icon} size={18} />
                  <AppText variant="bodyMedium">{t(command.labelKey)}</AppText>
                </Pressable>
              ))
            ) : (
              <StateBlock compact title={t("globalSearchNoCommands")} tone="empty" />
            )}
          </View>
        </SectionCard>

        {normalizedInput.length < 2 ? (
          <StateBlock
            compact
            description={t("globalSearchMinChars")}
            title={t("globalSearchRecentCommands")}
            tone="info"
          />
        ) : searchQuery.isPending ? (
          <StateBlock compact title={t("globalSearchLoading")} tone="loading" />
        ) : searchQuery.isError ? (
          <StateBlock
            actionLabel={t("globalSearchRetry")}
            compact
            description={searchQuery.error.message || t("globalSearchError")}
            onAction={() => {
              void searchQuery.refetch();
            }}
            title={t("globalSearchError")}
            tone="error"
          />
        ) : searchQuery.data?.results.length ? (
          <View style={{ gap: theme.spacing[4] }}>
            <AppText variant="h3">{t("globalSearchResultsTitle")}</AppText>
            {Object.entries(groups).map(([type, results]) => (
              <SectionCard key={type} title={t(entityLabels[type as GlobalSearchResultType])}>
                <View style={{ gap: theme.spacing[2] }}>{results?.map(renderResult)}</View>
              </SectionCard>
            ))}
          </View>
        ) : (
          <StateBlock compact title={t("globalSearchNoResults")} tone="empty" />
        )}
      </View>
    </AppShell>
  );
}

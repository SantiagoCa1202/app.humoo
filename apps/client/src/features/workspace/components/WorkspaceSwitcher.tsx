import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import { useWorkspace } from "@/features/workspace";
import { useAppTheme } from "@/theme/ThemeProvider";

export function WorkspaceSwitcher() {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const { activeWorkspace, setActiveWorkspace, status, workspaces } = useWorkspace();

  if (!activeWorkspace || workspaces.length === 0) {
    return null;
  }

  return (
    <View style={{ gap: theme.spacing[2] }}>
      <Text tone="secondary" variant="overline">
        {t("switchWorkspace")}
      </Text>
      <Text selectable tone="secondary" variant="caption">
        {t("workspaceCurrentLabel", {
          workspace: activeWorkspace.name,
        })}
      </Text>
      {workspaces.length > 1 ? (
        <View style={{ gap: theme.spacing[2] }}>
          {workspaces.map((workspace) => {
            const isActive = workspace.workspace.id === activeWorkspace.id;

            return (
              <Button
                accessibilityLabel={t("workspaceSwitchAccessibilityLabel", {
                  workspace: workspace.workspace.name,
                })}
                disabled={isActive}
                key={workspace.workspace.id}
                label={
                  isActive
                    ? t("workspaceCurrentShort", {
                        workspace: workspace.workspace.name,
                      })
                    : workspace.workspace.name
                }
                loading={status === "loading" && !isActive}
                onPress={() => setActiveWorkspace(workspace.workspace.id)}
                size="sm"
                variant={isActive ? "primary" : "secondary"}
              />
            );
          })}
        </View>
      ) : null}
    </View>
  );
}

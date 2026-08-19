import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AuthLayout } from "@/components/patterns/AuthLayout";
import { ListItemCard } from "@/components/patterns/ListItemCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { AppButton } from "@/components/primitives/AppButton";
import { TextField } from "@/components/primitives/TextField";
import type { WorkspaceAccess } from "@/features/workspace";
import { spacing } from "@/theme";

type WorkspaceSelectScreenProps = {
  activeWorkspaceId: string | null;
  errorMessage?: string | null;
  onAcceptInvitation: (token: string) => Promise<void>;
  onCreateWorkspace: () => void;
  onRefresh: () => Promise<void>;
  onSelectWorkspace: (workspaceId: string) => Promise<void>;
  onSignOut: () => Promise<void>;
  workspaces: WorkspaceAccess[];
};

export function WorkspaceSelectScreen({
  activeWorkspaceId,
  errorMessage,
  onAcceptInvitation,
  onCreateWorkspace,
  onRefresh,
  onSelectWorkspace,
  onSignOut,
  workspaces,
}: WorkspaceSelectScreenProps) {
  const { t } = useTranslation("app");
  const [invitationToken, setInvitationToken] = useState("");
  const [pendingWorkspaceId, setPendingWorkspaceId] = useState<string | null>(null);
  const [isAcceptingInvitation, setIsAcceptingInvitation] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);

  return (
    <AuthLayout
      description={t("workspaceSelectBody")}
      title={t("workspaceSelectTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        {errorMessage ? <AlertMessage tone="error" message={errorMessage} /> : null}
        {workspaces.length === 0 ? (
          <StateBlock
            actionLabel={t("workspaceCreateAction")}
            description={t("workspaceSelectEmptyBody")}
            onAction={onCreateWorkspace}
            title={t("workspaceSelectEmptyTitle")}
            tone="empty"
          />
        ) : (
          workspaces.map((workspace) => {
            const isActive = workspace.workspace.id === activeWorkspaceId;

            return (
              <ListItemCard
                key={workspace.workspace.id}
                meta={[
                  `${t("workspaceFieldTimezone")}: ${workspace.workspace.timezone}`,
                  `${t("workspaceFieldCurrency")}: ${workspace.workspace.currency}`,
                  `${t("memberCurrentRole")}: ${workspace.role?.name ?? t("workspaceRoleUnassigned")}`,
                ]}
                title={workspace.workspace.name}
              >
                <AppButton
                  label={
                    isActive
                      ? t("workspaceCurrent")
                      : t("workspaceSelectAction")
                  }
                  loading={pendingWorkspaceId === workspace.workspace.id}
                  onPress={async () => {
                    try {
                      setPendingWorkspaceId(workspace.workspace.id);
                      await onSelectWorkspace(workspace.workspace.id);
                    } catch {
                      // The provider surfaces the API error through shared state.
                    } finally {
                      setPendingWorkspaceId(null);
                    }
                  }}
                  variant={isActive ? "secondary" : "primary"}
                />
              </ListItemCard>
            );
          })
        )}
        <TextField
          autoCapitalize="none"
          label={t("workspaceInvitationToken")}
          onChangeText={setInvitationToken}
          value={invitationToken}
        />
        <AppButton
          disabled={!invitationToken.trim()}
          label={t("acceptWorkspaceInvitation")}
          loading={isAcceptingInvitation}
          onPress={async () => {
            try {
              setIsAcceptingInvitation(true);
              await onAcceptInvitation(invitationToken);
            } catch {
              // The provider surfaces the API error through shared state.
            } finally {
              setIsAcceptingInvitation(false);
            }
          }}
        />
        <AppButton
          label={t("workspaceCreateAnother")}
          onPress={onCreateWorkspace}
          variant="secondary"
        />
        <AppButton
          label={t("refreshWorkspaceAccess")}
          loading={isRefreshing}
          onPress={async () => {
            try {
              setIsRefreshing(true);
              await onRefresh();
            } catch {
              // The provider surfaces the API error through shared state.
            } finally {
              setIsRefreshing(false);
            }
          }}
          variant="secondary"
        />
        <AppButton
          label={t("signOut")}
          onPress={onSignOut}
          variant="secondary"
        />
      </View>
    </AuthLayout>
  );
}

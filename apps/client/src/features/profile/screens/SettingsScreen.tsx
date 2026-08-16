import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { apiRequest } from "@/api/client";
import { useAuth } from "@/auth/useAuth";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { LanguageSelector } from "@/components/patterns/LanguageSelector";
import { ThemeToggle } from "@/components/patterns/ThemeToggle";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { TextField } from "@/components/primitives/TextField";
import { isApiConfigured, runtimeConfig } from "@/config/runtime";
import {
  createWorkspaceInvitation,
  listAuthSessions,
  listWorkspaceInvitations,
  listWorkspaceMembers,
  listWorkspaceRoles,
  revokeAuthSession,
  updateWorkspaceMember,
  type WorkspaceRole,
} from "@/features/workspace";
import { useAppTheme } from "@/theme/ThemeProvider";

type HealthPayload = {
  data: {
    status: string;
    app: string;
    environment: string;
    php: string;
    database: {
      driver: string;
      connected: boolean;
    };
  };
  meta: {
    request_id: string;
  };
};

export default function SettingsScreen() {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteRoleId, setInviteRoleId] = useState<string | null>(null);
  const [inviteSuccess, setInviteSuccess] = useState<string | null>(null);
  const [invitePreview, setInvitePreview] = useState<{
    token: string | null;
    url: string | null;
  } | null>(null);
  const isApiSession = session?.mode === "api" && Boolean(session.token);
  const authToken = session?.token ?? null;
  const workspaceId = session?.currentWorkspace?.id ?? null;
  const canViewMembers = session?.permissions.includes("members.view") ?? false;
  const canInviteMembers =
    session?.permissions.includes("members.invite") ||
    session?.permissions.includes("members.manage") ||
    false;
  const canManageMembers = session?.permissions.includes("members.manage") ?? false;
  const healthQuery = useQuery({
    queryKey: ["api-health"],
    queryFn: () => apiRequest<HealthPayload>("/api/v1/health"),
    enabled: isApiConfigured,
    retry: 1,
  });
  const sessionsQuery = useQuery({
    queryKey: ["auth-sessions", authToken],
    queryFn: () => listAuthSessions(authToken!),
    enabled: isApiSession,
  });
  const rolesQuery = useQuery({
    queryKey: ["workspace-roles", workspaceId],
    queryFn: () => listWorkspaceRoles(authToken!, workspaceId!),
    enabled: Boolean(isApiSession && workspaceId && canViewMembers),
  });
  const membersQuery = useQuery({
    queryKey: ["workspace-members", workspaceId],
    queryFn: () => listWorkspaceMembers(authToken!, workspaceId!),
    enabled: Boolean(isApiSession && workspaceId && canViewMembers),
  });
  const invitationsQuery = useQuery({
    queryKey: ["workspace-invitations", workspaceId],
    queryFn: () => listWorkspaceInvitations(authToken!, workspaceId!),
    enabled: Boolean(isApiSession && workspaceId && canViewMembers),
  });
  const revokeSessionMutation = useMutation({
    mutationFn: (sessionId: string) => revokeAuthSession(authToken!, sessionId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["auth-sessions"] });
    },
  });
  const inviteMutation = useMutation({
    mutationFn: () =>
      createWorkspaceInvitation(authToken!, workspaceId!, {
        email: inviteEmail,
        roleId: inviteRoleId,
      }),
    onSuccess: async (result) => {
      setInviteEmail("");
      setInviteSuccess(t("invitationSent"));
      setInvitePreview({
        token: result.invitationTokenPreview,
        url: result.acceptUrlPreview,
      });
      await queryClient.invalidateQueries({
        queryKey: ["workspace-invitations", workspaceId],
      });
    },
  });
  const memberMutation = useMutation({
    mutationFn: (input: {
      memberId: string;
      roleId?: string | null;
      status?: string;
    }) =>
      updateWorkspaceMember(authToken!, workspaceId!, input.memberId, {
        roleId: input.roleId,
        status: input.status,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["workspace-members", workspaceId],
      });
    },
  });

  useEffect(() => {
    if (inviteRoleId || !rolesQuery.data?.length) {
      return;
    }

    const preferredRole =
      rolesQuery.data.find((role) => role.key !== "owner") ?? rolesQuery.data[0];

    setInviteRoleId(preferredRole?.id ?? null);
  }, [inviteRoleId, rolesQuery.data]);

  return (
    <AppShell
      title={t("settingsTitle")}
      subtitle={t("settingsSubtitle")}
    >
      <View style={{ gap: 18 }}>
        <Card style={{ gap: 16 }}>
          <LanguageSelector />
          <ThemeToggle />
        </Card>
        <Card style={{ gap: 8 }}>
          <AppText variant="overline">{t("runtimeTitle")}</AppText>
          <AppText muted>API URL: {runtimeConfig.apiUrl || "not configured"}</AppText>
          <AppText muted>
            Local fallback: {runtimeConfig.enableLocalAuthFallback ? "enabled" : "disabled"}
          </AppText>
          <AppText muted>Environment: {runtimeConfig.appEnv}</AppText>
          {isApiConfigured ? (
            <>
              <AppText muted>
                API health:{" "}
                {healthQuery.isLoading
                  ? "checking"
                  : healthQuery.data?.data.status ?? "unavailable"}
              </AppText>
              <AppText muted>
                API database:{" "}
                {healthQuery.data?.data.database.connected ? "connected" : "not connected"}
              </AppText>
              <AppText muted>
                API PHP: {healthQuery.data?.data.php ?? "unknown"}
              </AppText>
            </>
          ) : null}
        </Card>
        {isApiSession ? (
          <Card style={{ gap: 12 }}>
            <AppText variant="overline">{t("sessionsTitle")}</AppText>
            <AppText muted>{t("sessionsBody")}</AppText>
            {sessionsQuery.isLoading ? (
              <AppText muted>{t("sessionsLoading")}</AppText>
            ) : null}
            {sessionsQuery.error ? (
              <AlertMessage
                tone="error"
                message={
                  sessionsQuery.error instanceof Error
                    ? sessionsQuery.error.message
                    : t("sessionsLoadError")
                }
              />
            ) : null}
            {sessionsQuery.data?.length ? (
              sessionsQuery.data.map((item) => (
                <View
                  key={item.id}
                  style={{
                    borderTopColor: theme.colors.border,
                    borderTopWidth: 1,
                    gap: 8,
                    paddingTop: 12,
                  }}
                >
                  <AppText variant="subtitle">
                    {(item.device?.name ?? item.device?.platform ?? t("sessionUnknown")).trim()}
                  </AppText>
                  <AppText muted>
                    {t("sessionLastSeen")}: {item.lastSeenAt ?? "n/a"}
                  </AppText>
                  <AppText muted>
                    {t("sessionWorkspace")}: {item.workspaceName ?? t("workspacePending")}
                  </AppText>
                  <AppText muted>
                    IP: {item.device?.lastIp ?? "n/a"}
                  </AppText>
                  {item.isCurrent ? (
                    <AlertMessage message={t("sessionCurrent")} />
                  ) : (
                    <AppButton
                      label={t("sessionRevoke")}
                      loading={revokeSessionMutation.isPending}
                      onPress={() => revokeSessionMutation.mutate(item.id)}
                      variant="secondary"
                    />
                  )}
                </View>
              ))
            ) : !sessionsQuery.isLoading ? (
              <AppText muted>{t("sessionsEmpty")}</AppText>
            ) : null}
          </Card>
        ) : null}
        {isApiSession && workspaceId ? (
          <Card style={{ gap: 14 }}>
            <AppText variant="overline">{t("membersAdminTitle")}</AppText>
            <AppText muted>{t("membersAdminBody")}</AppText>
            {!canViewMembers ? (
              <AlertMessage message={t("membersAdminNoPermission")} />
            ) : (
              <>
                {canInviteMembers ? (
                  <View style={{ gap: 12 }}>
                    <TextField
                      autoCapitalize="none"
                      keyboardType="email-address"
                      label={t("inviteEmail")}
                      onChangeText={(value) => {
                        setInviteSuccess(null);
                        setInvitePreview(null);
                        setInviteEmail(value);
                      }}
                      value={inviteEmail}
                    />
                    {rolesQuery.data?.length ? (
                      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 10 }}>
                        {rolesQuery.data.map((role) => (
                          <SelectionChip
                            key={role.id}
                            active={inviteRoleId === role.id}
                            label={role.name}
                            onPress={() => setInviteRoleId(role.id)}
                          />
                        ))}
                      </View>
                    ) : null}
                    <AppButton
                      disabled={!inviteEmail.trim() || !inviteRoleId}
                      label={t("sendInvitation")}
                      loading={inviteMutation.isPending}
                      onPress={() => inviteMutation.mutate()}
                    />
                    {inviteMutation.error ? (
                      <AlertMessage
                        tone="error"
                        message={
                          inviteMutation.error instanceof Error
                            ? inviteMutation.error.message
                            : t("membersAdminActionError")
                        }
                      />
                    ) : null}
                    {inviteSuccess ? (
                      <AlertMessage tone="success" message={inviteSuccess} />
                    ) : null}
                    {invitePreview?.token ? (
                      <AlertMessage
                        message={t("invitationTokenPreview", {
                          token: invitePreview.token,
                        })}
                      />
                    ) : null}
                    {invitePreview?.url ? (
                      <AlertMessage
                        message={t("invitationUrlPreview", {
                          url: invitePreview.url,
                        })}
                      />
                    ) : null}
                  </View>
                ) : null}
                <View style={{ gap: 12 }}>
                  <AppText variant="subtitle">{t("membersListTitle")}</AppText>
                  {membersQuery.isLoading ? (
                    <AppText muted>{t("membersLoading")}</AppText>
                  ) : null}
                  {membersQuery.error ? (
                    <AlertMessage
                      tone="error"
                      message={
                        membersQuery.error instanceof Error
                          ? membersQuery.error.message
                          : t("membersLoadError")
                      }
                    />
                  ) : null}
                  {membersQuery.data?.length ? (
                    membersQuery.data.map((member) => (
                      <View
                        key={member.id}
                        style={{
                          borderTopColor: theme.colors.border,
                          borderTopWidth: 1,
                          gap: 10,
                          paddingTop: 12,
                        }}
                      >
                        <AppText variant="subtitle">
                          {member.user?.name ?? member.user?.email ?? member.userId}
                        </AppText>
                        <AppText muted>{member.user?.email ?? "n/a"}</AppText>
                        <AppText muted>
                          {t("memberCurrentRole")}: {member.role?.name ?? "Unassigned"}
                        </AppText>
                        <AppText muted>
                          {t("memberCurrentStatus")}: {member.status}
                        </AppText>
                        {canManageMembers && member.userId !== session?.user.id ? (
                          <>
                            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 10 }}>
                              {rolesQuery.data?.map((role) => (
                                <SelectionChip
                                  key={`${member.id}-${role.id}`}
                                  active={member.roleId === role.id}
                                  label={role.name}
                                  onPress={() =>
                                    memberMutation.mutate({
                                      memberId: member.id,
                                      roleId: role.id,
                                    })
                                  }
                                />
                              ))}
                            </View>
                            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 10 }}>
                              {["active", "suspended", "removed"].map((status) => (
                                <SelectionChip
                                  key={`${member.id}-${status}`}
                                  active={member.status === status}
                                  label={status}
                                  onPress={() =>
                                    memberMutation.mutate({
                                      memberId: member.id,
                                      status,
                                    })
                                  }
                                />
                              ))}
                            </View>
                          </>
                        ) : null}
                      </View>
                    ))
                  ) : !membersQuery.isLoading ? (
                    <AppText muted>{t("membersEmpty")}</AppText>
                  ) : null}
                </View>
                <View style={{ gap: 12 }}>
                  <AppText variant="subtitle">{t("invitationsListTitle")}</AppText>
                  {invitationsQuery.isLoading ? (
                    <AppText muted>{t("invitationsLoading")}</AppText>
                  ) : null}
                  {invitationsQuery.error ? (
                    <AlertMessage
                      tone="error"
                      message={
                        invitationsQuery.error instanceof Error
                          ? invitationsQuery.error.message
                          : t("invitationsLoadError")
                      }
                    />
                  ) : null}
                  {invitationsQuery.data?.length ? (
                    invitationsQuery.data.map((invitation) => (
                      <View
                        key={invitation.id}
                        style={{
                          borderTopColor: theme.colors.border,
                          borderTopWidth: 1,
                          gap: 6,
                          paddingTop: 12,
                        }}
                      >
                        <AppText variant="subtitle">{invitation.email}</AppText>
                        <AppText muted>
                          {t("memberCurrentRole")}: {invitation.role?.name ?? "Unassigned"}
                        </AppText>
                        <AppText muted>
                          {t("invitationExpiresAt")}: {invitation.expiresAt}
                        </AppText>
                      </View>
                    ))
                  ) : !invitationsQuery.isLoading ? (
                    <AppText muted>{t("invitationsEmpty")}</AppText>
                  ) : null}
                </View>
              </>
            )}
          </Card>
        ) : null}
      </View>
    </AppShell>
  );
}

function SelectionChip({
  active,
  label,
  onPress,
}: {
  active: boolean;
  label: string;
  onPress: () => void;
}) {
  const { theme } = useAppTheme();

  return (
    <Pressable
      onPress={onPress}
      style={{
        backgroundColor: active ? theme.colors.primary : theme.colors.surfaceMuted,
        borderColor: active ? theme.colors.primary : theme.colors.border,
        borderRadius: theme.radius.pill,
        borderWidth: 1,
        paddingHorizontal: 14,
        paddingVertical: 8,
      }}
    >
      <AppText
        style={{
          color: active ? theme.colors.primaryContrast : theme.colors.text,
        }}
        variant="caption"
      >
        {label}
      </AppText>
    </Pressable>
  );
}

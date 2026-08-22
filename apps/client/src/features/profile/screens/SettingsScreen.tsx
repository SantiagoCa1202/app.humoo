import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import { Switch, View } from "react-native";
import { useTranslation } from "react-i18next";

import { apiRequest } from "@/api/client";
import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { FormSection } from "@/components/patterns/FormSection";
import { LanguageSelector } from "@/components/patterns/LanguageSelector";
import { ListItemCard } from "@/components/patterns/ListItemCard";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { ThemeToggle } from "@/components/patterns/ThemeToggle";
import { AppButton } from "@/components/primitives/AppButton";
import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { OptionPicker } from "@/components/primitives/OptionPicker";
import { AppText } from "@/components/primitives/AppText";
import { TextField } from "@/components/primitives/TextField";
import { isApiConfigured, runtimeConfig } from "@/config/runtime";
import { routes } from "@/navigation/routes";
import {
  useNotificationPreferences,
  useUpdateNotificationPreference,
} from "@/features/notifications/hooks";
import { spacing } from "@/theme";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  cancelWorkspaceInvitation,
  createWorkspaceInvitation,
  listAuthSessions,
  listWorkspaceInvitations,
  listWorkspaceMembers,
  listWorkspaceRoles,
  removeWorkspaceMember,
  revokeAuthSession,
  updateCurrentWorkspace,
  updateWorkspaceMember,
  useWorkspace,
} from "@/features/workspace";

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
  const router = useRouter();
  const { theme } = useAppTheme();
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace, hasPermission, refreshWorkspaces } = useWorkspace();
  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteRoleId, setInviteRoleId] = useState<string | null>(null);
  const [inviteSuccess, setInviteSuccess] = useState<string | null>(null);
  const [workspaceSuccess, setWorkspaceSuccess] = useState<string | null>(null);
  const [invitePreview, setInvitePreview] = useState<{
    token: string | null;
    url: string | null;
  } | null>(null);
  const [workspaceName, setWorkspaceName] = useState("");
  const [workspaceTimezone, setWorkspaceTimezone] = useState("");
  const [workspaceCurrency, setWorkspaceCurrency] = useState("");
  const [workspaceLocale, setWorkspaceLocale] = useState<"en" | "es">("en");
  const isApiSession = session?.mode === "api" && Boolean(session.token);
  const authToken = session?.token ?? null;
  const workspaceId = activeWorkspace?.id ?? null;
  const canViewMembers = hasPermission("members.view");
  const canInviteMembers =
    hasPermission("members.invite") || hasPermission("members.manage");
  const canManageMembers = hasPermission("members.manage");
  const notificationPreferencesQuery = useNotificationPreferences();
  const updateNotificationPreferenceMutation = useUpdateNotificationPreference();

  useEffect(() => {
    if (!activeWorkspace) {
      return;
    }

    setWorkspaceName(activeWorkspace.name);
    setWorkspaceTimezone(activeWorkspace.timezone);
    setWorkspaceCurrency(activeWorkspace.currency);
    setWorkspaceLocale(activeWorkspace.defaultLocale === "es" ? "es" : "en");
  }, [activeWorkspace]);

  const healthQuery = useQuery({
    queryKey: ["api-health"],
    queryFn: () => apiRequest<HealthPayload>("/health"),
    enabled: isApiConfigured,
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
  const workspaceMutation = useMutation({
    mutationFn: () =>
      updateCurrentWorkspace(authToken!, workspaceId!, {
        name: workspaceName,
        timezone: workspaceTimezone,
        currency: workspaceCurrency,
        defaultLocale: workspaceLocale,
      }),
    onSuccess: async () => {
      setWorkspaceSuccess(t("workspaceSettingsSaved"));
      await refreshWorkspaces();
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
  const removeMemberMutation = useMutation({
    mutationFn: (memberId: string) =>
      removeWorkspaceMember(authToken!, workspaceId!, memberId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["workspace-members", workspaceId],
      });
    },
  });
  const cancelInvitationMutation = useMutation({
    mutationFn: (invitationId: string) =>
      cancelWorkspaceInvitation(authToken!, workspaceId!, invitationId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["workspace-invitations", workspaceId],
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
      <View style={{ gap: spacing[4] }}>
        <Card style={{ gap: spacing[4] }}>
          <LanguageSelector />
          <ThemeToggle />
        </Card>
        <SectionCard
          description={t("settingsHubDescription")}
          title={t("settingsHubTitle")}
        >
          <AppButton
            label={t("settingsAccount")}
            onPress={() => router.push(routes.app.profile)}
            variant="outline"
          />
          {hasPermission("billing.view") ? (
            <AppButton
              label={t("settingsBilling")}
              onPress={() => router.push(routes.app.billing)}
              variant="outline"
            />
          ) : null}
          {hasPermission("audit.view") ? (
            <AppButton
              label={t("settingsAudit")}
              onPress={() => router.push(routes.app.audit)}
              variant="outline"
            />
          ) : null}
        </SectionCard>
        {isApiSession && workspaceId ? (
          <SectionCard
            description={t("notificationsPreferencesDescription")}
            title={t("notificationsPreferencesTitle")}
          >
            {notificationPreferencesQuery.isLoading ? (
              <StateBlock title={t("notificationsPreferencesLoading")} tone="loading" />
            ) : null}
            {notificationPreferencesQuery.error ? (
              <StateBlock
                actionLabel={t("notificationsRetry")}
                onAction={() => void notificationPreferencesQuery.refetch()}
                title={t("notificationsPreferencesError")}
                tone="error"
              />
            ) : null}
            {notificationPreferencesQuery.data?.map((preference) => (
              <View
                key={preference.eventKey}
                style={{
                  alignItems: "center",
                  borderBottomColor: theme.colors.border.default,
                  borderBottomWidth: 1,
                  flexDirection: "row",
                  gap: spacing[3],
                  paddingVertical: spacing[3],
                }}
              >
                <View style={{ flex: 1, gap: spacing[1] }}>
                  <AppText variant="bodyMedium">
                    {t(
                      preference.eventKey === "task.assigned"
                        ? "notificationsPreferenceTaskAssigned"
                        : "notificationsPreferencePrepAssigned",
                    )}
                  </AppText>
                  <AppText muted variant="caption">
                    {t("notificationsInAppOnly")}
                  </AppText>
                </View>
                <Switch
                  accessibilityLabel={t("notificationsPreferenceToggle")}
                  disabled={updateNotificationPreferenceMutation.isPending}
                  onValueChange={(inApp) =>
                    updateNotificationPreferenceMutation.mutate({
                      enabled: inApp,
                      eventKey: preference.eventKey,
                      inApp,
                      minimumPriority: preference.minimumPriority,
                    })
                  }
                  value={preference.enabled && preference.inApp}
                />
              </View>
            ))}
          </SectionCard>
        ) : null}
        <Card style={{ gap: spacing[2] }}>
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
          <SectionCard
            description={t("sessionsBody")}
            title={t("sessionsTitle")}
          >
            {sessionsQuery.isLoading ? (
              <StateBlock title={t("sessionsLoading")} tone="loading" />
            ) : null}
            {sessionsQuery.error ? (
              <StateBlock
                description={
                  sessionsQuery.error instanceof Error
                    ? sessionsQuery.error.message
                    : undefined
                }
                title={t("sessionsLoadError")}
                tone="error"
              />
            ) : null}
            {sessionsQuery.data?.length ? (
              sessionsQuery.data.map((item) => (
                <ListItemCard
                  key={item.id}
                  meta={[
                    `${t("sessionLastSeen")}: ${item.lastSeenAt ?? "n/a"}`,
                    `${t("sessionWorkspace")}: ${
                      item.workspaceName ?? t("workspacePending")
                    }`,
                    `IP: ${item.device?.lastIp ?? "n/a"}`,
                  ]}
                  title={(
                    item.device?.name ??
                    item.device?.platform ??
                    t("sessionUnknown")
                  ).trim()}
                >
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
                </ListItemCard>
              ))
            ) : !sessionsQuery.isLoading ? (
              <StateBlock title={t("sessionsEmpty")} tone="empty" />
            ) : null}
          </SectionCard>
        ) : null}
        {isApiSession && workspaceId ? (
          <SectionCard
            description={t("membersAdminBody")}
            title={t("membersAdminTitle")}
          >
            {!canViewMembers ? (
              <StateBlock
                description={t("membersAdminNoPermission")}
                title={t("membersAdminTitle")}
                tone="info"
              />
            ) : (
              <>
                {canManageMembers ? (
                  <FormSection title={t("workspaceSettingsTitle")}>
                    <TextField
                      label={t("workspaceFieldName")}
                      onChangeText={(value) => {
                        setWorkspaceSuccess(null);
                        setWorkspaceName(value);
                      }}
                      value={workspaceName}
                    />
                    <TextField
                      autoCapitalize="none"
                      label={t("workspaceFieldTimezone")}
                      onChangeText={(value) => {
                        setWorkspaceSuccess(null);
                        setWorkspaceTimezone(value);
                      }}
                      value={workspaceTimezone}
                    />
                    <TextField
                      autoCapitalize="characters"
                      label={t("workspaceFieldCurrency")}
                      onChangeText={(value) => {
                        setWorkspaceSuccess(null);
                        setWorkspaceCurrency(value);
                      }}
                      value={workspaceCurrency}
                    />
                    <OptionPicker
                      label={t("workspaceFieldDefaultLocale")}
                      onChange={(value) => {
                        setWorkspaceSuccess(null);
                        setWorkspaceLocale(value);
                      }}
                      options={[
                        {
                          value: "en",
                          label: t("workspaceLocale.en"),
                        },
                        {
                          value: "es",
                          label: t("workspaceLocale.es"),
                        },
                      ]}
                      selected={workspaceLocale}
                    />
                    {workspaceMutation.error ? (
                      <AlertMessage
                        tone="error"
                        message={
                          workspaceMutation.error instanceof Error
                            ? workspaceMutation.error.message
                            : t("workspaceSettingsError")
                        }
                      />
                    ) : null}
                    {workspaceSuccess ? (
                      <AlertMessage tone="success" message={workspaceSuccess} />
                    ) : null}
                    <AppButton
                      label={t("workspaceSaveSettings")}
                      loading={workspaceMutation.isPending}
                      onPress={() => workspaceMutation.mutate()}
                    />
                  </FormSection>
                ) : null}
                {canInviteMembers ? (
                  <FormSection title={t("sendInvitation")}>
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
                      <OptionPicker
                        label={t("memberCurrentRole")}
                        onChange={setInviteRoleId}
                        options={rolesQuery.data.map((role) => ({
                          value: role.id,
                          label: role.name,
                        }))}
                        selected={inviteRoleId}
                      />
                    ) : null}
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
                    <AppButton
                      disabled={!inviteEmail.trim() || !inviteRoleId}
                      label={t("sendInvitation")}
                      loading={inviteMutation.isPending}
                      onPress={() => inviteMutation.mutate()}
                    />
                  </FormSection>
                ) : null}
                <FormSection title={t("membersListTitle")}>
                  {membersQuery.isLoading ? (
                    <StateBlock title={t("membersLoading")} tone="loading" />
                  ) : null}
                  {membersQuery.error ? (
                    <StateBlock
                      description={
                        membersQuery.error instanceof Error
                          ? membersQuery.error.message
                          : undefined
                      }
                      title={t("membersLoadError")}
                      tone="error"
                    />
                  ) : null}
                  {membersQuery.data?.length ? (
                    membersQuery.data.map((member) => (
                      <ListItemCard
                        key={member.id}
                        meta={[
                          member.user?.email ?? "n/a",
                          `${t("memberCurrentRole")}: ${
                            member.role?.name ?? t("workspaceRoleUnassigned")
                          }`,
                          `${t("memberCurrentStatus")}: ${translateMembershipStatus(
                            t,
                            member.status
                          )}`,
                        ]}
                        title={member.user?.name ?? member.user?.email ?? member.userId}
                      >
                        {canManageMembers && member.userId !== session?.user.id ? (
                          <>
                            <OptionPicker
                              label={t("memberCurrentRole")}
                              onChange={(roleId) =>
                                memberMutation.mutate({
                                  memberId: member.id,
                                  roleId,
                                })
                              }
                              options={
                                rolesQuery.data?.map((role) => ({
                                  value: role.id,
                                  label: role.name,
                                })) ?? []
                              }
                              selected={member.roleId}
                            />
                            <View
                              style={{
                                flexDirection: "row",
                                flexWrap: "wrap",
                                gap: spacing[2],
                              }}
                            >
                              {["active", "suspended", "removed"].map((status) => (
                                <ChoiceChip
                                  active={member.status === status}
                                  key={`${member.id}-${status}`}
                                  label={translateMembershipStatus(t, status)}
                                  onPress={() =>
                                    memberMutation.mutate({
                                      memberId: member.id,
                                      status,
                                    })
                                  }
                                />
                              ))}
                            </View>
                            <AppButton
                              label={t("removeMember")}
                              loading={removeMemberMutation.isPending}
                              onPress={() => removeMemberMutation.mutate(member.id)}
                              variant="destructiveSoft"
                            />
                          </>
                        ) : null}
                      </ListItemCard>
                    ))
                  ) : !membersQuery.isLoading ? (
                    <StateBlock title={t("membersEmpty")} tone="empty" />
                  ) : null}
                </FormSection>
                <FormSection title={t("invitationsListTitle")}>
                  {invitationsQuery.isLoading ? (
                    <StateBlock title={t("invitationsLoading")} tone="loading" />
                  ) : null}
                  {invitationsQuery.error ? (
                    <StateBlock
                      description={
                        invitationsQuery.error instanceof Error
                          ? invitationsQuery.error.message
                          : undefined
                      }
                      title={t("invitationsLoadError")}
                      tone="error"
                    />
                  ) : null}
                  {invitationsQuery.data?.length ? (
                    invitationsQuery.data.map((invitation) => (
                      <ListItemCard
                        key={invitation.id}
                        meta={[
                          `${t("memberCurrentRole")}: ${
                            invitation.role?.name ?? t("workspaceRoleUnassigned")
                          }`,
                          `${t("invitationExpiresAt")}: ${invitation.expiresAt}`,
                        ]}
                        title={invitation.email}
                      >
                        {invitation.isExpired ? (
                          <AlertMessage
                            tone="error"
                            message={t("invitationExpired")}
                          />
                        ) : canInviteMembers ? (
                          <AppButton
                            label={t("cancelInvitation")}
                            loading={cancelInvitationMutation.isPending}
                            onPress={() =>
                              cancelInvitationMutation.mutate(invitation.id)
                            }
                            variant="secondary"
                          />
                        ) : null}
                      </ListItemCard>
                    ))
                  ) : !invitationsQuery.isLoading ? (
                    <StateBlock title={t("invitationsEmpty")} tone="empty" />
                  ) : null}
                </FormSection>
              </>
            )}
          </SectionCard>
        ) : null}
      </View>
    </AppShell>
  );
}

function translateMembershipStatus(
  t: ReturnType<typeof useTranslation>["t"],
  status: string
) {
  if (status === "active") {
    return t("workspaceMemberStatus.active");
  }

  if (status === "suspended") {
    return t("workspaceMemberStatus.suspended");
  }

  if (status === "removed") {
    return t("workspaceMemberStatus.removed");
  }

  if (status === "pending") {
    return t("workspaceMemberStatus.pending");
  }

  return status;
}

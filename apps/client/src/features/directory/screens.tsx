import { router, useLocalSearchParams, type Href } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ApiError, isApiError } from "@/api/types";
import { AppShell } from "@/components/patterns/AppShell";
import { ClientCard } from "@/components/patterns/client-card";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { ContactCard } from "@/components/patterns/contact-card";
import { DetailCard } from "@/components/patterns/detail-card";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorRecoveryCard } from "@/components/patterns/error-recovery-card";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { SectionCard } from "@/components/patterns/SectionCard";
import { VenueCard } from "@/components/patterns/venue-card";
import { Button } from "@/components/primitives/button";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { SearchInput } from "@/components/primitives/search-input";
import { StatusSelect } from "@/components/primitives/status-select";
import { Text } from "@/components/primitives/text";
import {
  clientToCardValue,
  contactToCardValue,
  formatAddressLines,
  formatClientLocation,
  formatVenueLocation,
  venueToCardValue,
} from "@/features/directory/adapters";
import {
  ClientForm,
  ClientFormErrors,
  ContactForm,
  ContactFormErrors,
  VenueForm,
  VenueFormErrors,
} from "@/features/directory/forms";
import {
  useClient,
  useClientPickerOptions,
  useClients,
  useContacts,
  useCreateClient,
  useCreateContact,
  useCreateVenue,
  useDeleteClient,
  useDeleteContact,
  useDeleteVenue,
  useContact,
  useUpdateClient,
  useUpdateContact,
  useUpdateVenue,
  useVenue,
  useVenues,
} from "@/features/directory/hooks";
import type { ClientRecord, DirectoryStatus, VenueRecord } from "@/features/directory/types";
import { useWorkspace } from "@/features/workspace";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";

const STATUS_OPTIONS = [
  { value: "active" as const },
  { value: "inactive" as const },
];

export function DirectoryScreen() {
  const { t } = useTranslation("app");

  const sections = [
    {
      description: t("directory.hub.clientsDescription"),
      title: t("directory.hub.clientsTitle"),
      route: routes.app.directoryClients,
    },
    {
      description: t("directory.hub.contactsDescription"),
      title: t("directory.hub.contactsTitle"),
      route: routes.app.directoryContacts,
    },
    {
      description: t("directory.hub.venuesDescription"),
      title: t("directory.hub.venuesTitle"),
      route: routes.app.directoryVenues,
    },
  ];

  return (
    <AppShell
      subtitle={t("directory.hub.subtitle")}
      title={t("directory.hub.title")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          description={t("directory.hub.description")}
          title={t("directory.hub.sectionsTitle")}
        >
          <View style={{ gap: spacing[3] }}>
            {sections.map((section) => (
              <View key={section.title} style={{ gap: spacing[1] }}>
                <Text variant="bodyMedium">{section.title}</Text>
                <Text tone="muted" variant="bodySmall">
                  {section.description}
                </Text>
                <Button
                  label={t("directory.hub.openSection")}
                  onPress={() => router.push(section.route)}
                  size="sm"
                />
              </View>
            ))}
          </View>
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function ClientsListScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<DirectoryStatus | "">("");
  const canView = hasPermission("clients.view");
  const canCreate = hasPermission("clients.create");
  const clientsQuery = useClients({ search, status });

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:directory.clients.list.subtitle")}
        title={t("app:directory.clients.list.title")}
      >
        <ForbiddenState
          description={t("app:directory.shared.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:directory.clients.forbidden.title")}
        />
      </AppShell>
    );
  }

  const clients = clientsQuery.data?.data ?? [];

  return (
    <AppShell
      subtitle={t("app:directory.clients.list.subtitle")}
      title={t("app:directory.clients.list.title")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          action={
            canCreate ? (
              <Button
                label={t("app:directory.clients.actions.create")}
                onPress={() => router.push(routes.app.directoryClientCreate)}
                size="sm"
              />
            ) : null
          }
          description={t("app:directory.clients.list.filtersDescription")}
          title={t("app:directory.shared.filtersTitle")}
        >
          <View style={{ gap: spacing[3] }}>
            <SearchInput
              accessibilityLabel={t("app:directory.clients.search.accessibilityLabel")}
              onChangeText={setSearch}
              placeholder={t("app:directory.clients.search.placeholder")}
              value={search}
            />
            <StatusSelect
              accessibilityLabel={t("app:directory.shared.statusFilter.accessibilityLabel")}
              label={t("app:directory.shared.statusFilter.label")}
              namespace="workspaceMembers"
              onChange={(value) => setStatus(value as DirectoryStatus)}
              options={STATUS_OPTIONS}
              value={status || undefined}
            />
            {status ? (
              <Button
                label={t("common:clearSearch")}
                onPress={() => setStatus("")}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        </SectionCard>

        <SectionCard
          description={t("app:directory.clients.list.resultsDescription")}
          title={t("app:directory.clients.list.resultsTitle", { count: clients.length })}
        >
          {clientsQuery.isLoading ? (
            <LoadingState title={t("app:directory.clients.loading")} />
          ) : null}
          {clientsQuery.isError ? (
            <ErrorState
              detail={clientsQuery.error instanceof Error ? clientsQuery.error.message : undefined}
              onRetry={async () => {
                await clientsQuery.refetch();
              }}
              title={t("app:directory.clients.errorTitle")}
            />
          ) : null}
          {!clientsQuery.isLoading && !clientsQuery.isError && clients.length === 0 ? (
            <EmptyState
              actionLabel={canCreate ? t("app:directory.clients.actions.create") : undefined}
              onAction={
                canCreate ? () => router.push(routes.app.directoryClientCreate) : undefined
              }
              title={t("app:directory.clients.emptyTitle")}
            />
          ) : null}
          {!clientsQuery.isLoading && !clientsQuery.isError && clients.length > 0 ? (
            <View style={{ gap: spacing[3] }}>
              {clients.map((client) => (
                <ClientCard
                  client={clientToCardValue(client)}
                  key={client.id}
                  onPress={() =>
                    router.push({
                      pathname: routes.app.directoryClientDetail,
                      params: { id: client.id },
                    } as Href)
                  }
                />
              ))}
            </View>
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function ClientDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const clientId = resolveRouteParam(useLocalSearchParams<{ id?: string }>().id);
  const canView = hasPermission("clients.view");
  const canEdit = hasPermission("clients.edit");
  const canDelete = hasPermission("clients.delete");
  const canManageContacts = hasPermission("contacts.create") || hasPermission("contacts.edit");
  const clientQuery = useClient(clientId);
  const deleteMutation = useDeleteClient(clientId ?? "");
  const [deleteError, setDeleteError] = useState<ApiError | null>(null);
  const [showDeleteConfirmation, setShowDeleteConfirmation] = useState(false);

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:directory.clients.detail.subtitle")}
        title={t("app:directory.clients.detail.title")}
      >
        <ForbiddenState
          description={t("app:directory.shared.forbiddenDescription")}
          onBack={() => router.replace(routes.app.directoryClients)}
          title={t("app:directory.clients.forbidden.title")}
        />
      </AppShell>
    );
  }

  if (!clientId) {
    return (
      <AppShell
        subtitle={t("app:directory.clients.detail.subtitle")}
        title={t("app:directory.clients.detail.title")}
      >
        <ErrorState
          title={t("app:directory.shared.missingIdentifierTitle")}
          detail={t("app:directory.shared.missingIdentifierDescription")}
        />
      </AppShell>
    );
  }

  const client = clientQuery.data?.data ?? null;

  const handleDelete = async () => {
    try {
      setDeleteError(null);
      await deleteMutation.mutateAsync();
      router.replace(routes.app.directoryClients);
    } catch (error) {
      if (isApiError(error)) {
        setDeleteError(error);
      }
    }
  };

  return (
    <AppShell
      subtitle={client?.companyName ?? t("app:directory.clients.detail.subtitle")}
      title={client?.name ?? t("app:directory.clients.detail.title")}
    >
      <View style={{ gap: spacing[4] }}>
        {clientQuery.isLoading ? <LoadingState title={t("app:directory.clients.loading")} /> : null}
        {clientQuery.isError ? (
          <ErrorState
            detail={clientQuery.error instanceof Error ? clientQuery.error.message : undefined}
            onRetry={async () => {
              await clientQuery.refetch();
            }}
            title={t("app:directory.clients.errorTitle")}
          />
        ) : null}
        {client ? (
          <>
            <SectionCard
              action={
                <View style={{ flexDirection: "row", gap: spacing[2] }}>
                  {canEdit ? (
                    <Button
                      label={t("app:directory.shared.actions.edit")}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.directoryClientEdit,
                          params: { id: client.id },
                        } as Href)
                      }
                      size="sm"
                      variant="secondary"
                    />
                  ) : null}
                  {canManageContacts ? (
                    <Button
                      label={t("app:directory.contacts.actions.create")}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.directoryContactCreate,
                          params: {
                            clientId: client.id,
                            returnClientId: client.id,
                          },
                        } as Href)
                      }
                      size="sm"
                      variant="ghost"
                    />
                  ) : null}
                </View>
              }
              description={t("app:directory.clients.detail.summaryDescription")}
              title={t("app:directory.clients.detail.summaryTitle")}
            >
              <ClientCard client={clientToCardValue(client)} />
            </SectionCard>

            <DetailCard
              rows={buildClientRows(client, t)}
              subtitle={t("app:directory.clients.detail.metadataSubtitle")}
              title={t("app:directory.clients.detail.metadataTitle")}
            />

            <SectionCard
              description={t("app:directory.contacts.list.relatedDescription")}
              title={t("app:directory.contacts.list.relatedTitle", {
                count: client.contacts.length,
              })}
            >
              {client.contacts.length === 0 ? (
                <EmptyState
                  actionLabel={
                    canManageContacts
                      ? t("app:directory.contacts.actions.create")
                      : undefined
                  }
                  onAction={
                    canManageContacts
                      ? () =>
                          router.push({
                            pathname: routes.app.directoryContactCreate,
                            params: {
                              clientId: client.id,
                              returnClientId: client.id,
                            },
                          } as Href)
                      : undefined
                  }
                  title={t("app:directory.contacts.emptyTitle")}
                />
              ) : (
                <View style={{ gap: spacing[3] }}>
                  {client.contacts.map((contact) => (
                    <ContactCard
                      compact
                      contact={{
                        email: contact.email,
                        id: contact.id,
                        name: contact.displayName?.trim() || contact.fullName,
                        organization: client.companyName ?? client.name,
                        phone: contact.phone,
                        role: contact.contactType,
                        title: contact.jobTitle,
                      }}
                      key={contact.id}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.directoryContactDetail,
                          params: {
                            id: contact.id,
                            returnClientId: client.id,
                          },
                        } as Href)
                      }
                    />
                  ))}
                </View>
              )}
            </SectionCard>

            {canDelete ? (
              <SectionCard
                description={t("app:directory.clients.delete.description")}
                title={t("app:directory.clients.delete.title")}
              >
                {!showDeleteConfirmation ? (
                  <Button
                    label={t("app:directory.shared.actions.delete")}
                    onPress={() => setShowDeleteConfirmation(true)}
                    variant="destructive"
                  />
                ) : (
                  <ConfirmationCard
                    confirmLabel={t("app:directory.shared.actions.delete")}
                    description={t("app:directory.clients.delete.confirmationDescription")}
                    destructive
                    loading={deleteMutation.isPending}
                    onCancel={() => setShowDeleteConfirmation(false)}
                    onConfirm={handleDelete}
                    title={t("app:directory.clients.delete.confirmationTitle")}
                  />
                )}
                {deleteError ? (
                  <ErrorRecoveryCard
                    description={deleteError.message}
                    onRetry={handleDelete}
                    safeDetail={describeClientDeleteConflict(deleteError, t)}
                    title={t("app:directory.shared.conflictTitle")}
                  />
                ) : null}
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function ClientCreateScreen() {
  return <ClientUpsertScreen mode="create" />;
}

export function ClientEditScreen() {
  return <ClientUpsertScreen mode="edit" />;
}

type ClientUpsertMode = "create" | "edit";

function ClientUpsertScreen({ mode }: { mode: ClientUpsertMode }) {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const clientId = resolveRouteParam(useLocalSearchParams<{ id?: string }>().id);
  const canCreate = hasPermission("clients.create");
  const canEdit = hasPermission("clients.edit");
  const clientQuery = useClient(mode === "edit" ? clientId : null);
  const createMutation = useCreateClient();
  const updateMutation = useUpdateClient(clientId ?? "");
  const [formErrors, setFormErrors] = useState<ClientFormErrors>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  const allowed = mode === "create" ? canCreate : canEdit;

  if (!allowed) {
    return (
      <AppShell
        subtitle={t("directory.clients.form.subtitle")}
        title={mode === "create" ? t("directory.clients.create.title") : t("directory.clients.edit.title")}
      >
        <ForbiddenState
          description={t("directory.shared.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("directory.clients.forbidden.title")}
        />
      </AppShell>
    );
  }

  const client = clientQuery.data?.data ?? null;

  const initialValues = useMemo(
    () =>
      client
        ? {
            addressLine1: client.addressLine1 ?? "",
            addressLine2: client.addressLine2 ?? "",
            city: client.city ?? "",
            companyName: client.companyName ?? "",
            countryCode: client.countryCode ?? "",
            email: client.email ?? "",
            name: client.name,
            notes: client.notes ?? "",
            phone: client.phone ?? "",
            postalCode: client.postalCode ?? "",
            state: client.state ?? "",
            status: client.status ?? "active",
            taxId: client.taxId ?? "",
            website: client.website ?? "",
          }
        : undefined,
    [client]
  );

  const handleSubmit = async (values: Parameters<typeof createMutation.mutateAsync>[0]) => {
    try {
      setFormErrors({});
      setSubmitError(null);

      const savedClient =
        mode === "create"
          ? await createMutation.mutateAsync(values)
          : await updateMutation.mutateAsync(values);

      router.replace({
        pathname: routes.app.directoryClientDetail,
        params: { id: savedClient.id },
      } as Href);
    } catch (error) {
      handleClientFormError(error, setFormErrors, setSubmitError);
    }
  };

  return (
    <AppShell
      subtitle={t("directory.clients.form.subtitle")}
      title={
        mode === "create" ? t("directory.clients.create.title") : t("directory.clients.edit.title")
      }
    >
      <View style={{ gap: spacing[4] }}>
        {mode === "edit" && clientQuery.isLoading ? (
          <LoadingState title={t("directory.clients.loading")} />
        ) : null}
        {mode === "edit" && clientQuery.isError ? (
          <ErrorState
            detail={clientQuery.error instanceof Error ? clientQuery.error.message : undefined}
            onRetry={async () => {
              await clientQuery.refetch();
            }}
            title={t("directory.clients.errorTitle")}
          />
        ) : null}
        {(mode === "create" || client) && (
          <ClientForm
            errorMessage={submitError}
            initialValues={initialValues}
            loading={createMutation.isPending || updateMutation.isPending}
            onCancel={() => router.back()}
            onSubmit={handleSubmit}
            submitLabel={
              mode === "create"
                ? t("directory.clients.actions.create")
                : t("directory.shared.actions.save")
            }
            subtitle={t("directory.clients.form.description")}
            title={
              mode === "create"
                ? t("directory.clients.create.cardTitle")
                : t("directory.clients.edit.cardTitle")
            }
            validationErrors={formErrors}
          />
        )}
      </View>
    </AppShell>
  );
}

export function ContactsListScreen() {
  const { t } = useTranslation(["app", "common"]);
  const params = useLocalSearchParams<{ clientId?: string }>();
  const initialClientId = resolveRouteParam(params.clientId) ?? "";
  const { hasPermission } = useWorkspace();
  const [search, setSearch] = useState("");
  const [clientId, setClientId] = useState(initialClientId);
  const canView = hasPermission("contacts.view");
  const canCreate = hasPermission("contacts.create");
  const contactsQuery = useContacts({
    clientId: clientId || undefined,
    search,
  });
  const clientOptions = useClientPickerOptions();

  useEffect(() => {
    setClientId(initialClientId);
  }, [initialClientId]);

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:directory.contacts.list.subtitle")}
        title={t("app:directory.contacts.list.title")}
      >
        <ForbiddenState
          description={t("app:directory.shared.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:directory.contacts.forbidden.title")}
        />
      </AppShell>
    );
  }

  const contacts = contactsQuery.data?.data ?? [];

  return (
    <AppShell
      subtitle={t("app:directory.contacts.list.subtitle")}
      title={t("app:directory.contacts.list.title")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          action={
            canCreate ? (
              <Button
                label={t("app:directory.contacts.actions.create")}
                onPress={() =>
                  router.push({
                    pathname: routes.app.directoryContactCreate,
                    params: clientId ? { clientId } : undefined,
                  } as Href)
                }
                size="sm"
              />
            ) : null
          }
          description={t("app:directory.contacts.list.filtersDescription")}
          title={t("app:directory.shared.filtersTitle")}
        >
          <View style={{ gap: spacing[3] }}>
            <SearchInput
              accessibilityLabel={t("app:directory.contacts.search.accessibilityLabel")}
              onChangeText={setSearch}
              placeholder={t("app:directory.contacts.search.placeholder")}
              value={search}
            />
            <EntityPicker
              accessibilityLabel={t("app:directory.contacts.form.fields.client.accessibilityLabel")}
              entities={clientOptions}
              label={t("app:directory.contacts.form.fields.client.label")}
              onChange={setClientId}
              placeholder={t("app:directory.contacts.form.fields.client.placeholder")}
              value={clientId || undefined}
            />
            {clientId ? (
              <Button
                label={t("common:clearSearch")}
                onPress={() => setClientId("")}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        </SectionCard>

        <SectionCard
          description={t("app:directory.contacts.list.resultsDescription")}
          title={t("app:directory.contacts.list.resultsTitle", { count: contacts.length })}
        >
          {contactsQuery.isLoading ? (
            <LoadingState title={t("app:directory.contacts.loading")} />
          ) : null}
          {contactsQuery.isError ? (
            <ErrorState
              detail={contactsQuery.error instanceof Error ? contactsQuery.error.message : undefined}
              onRetry={async () => {
                await contactsQuery.refetch();
              }}
              title={t("app:directory.contacts.errorTitle")}
            />
          ) : null}
          {!contactsQuery.isLoading && !contactsQuery.isError && contacts.length === 0 ? (
            <EmptyState
              actionLabel={canCreate ? t("app:directory.contacts.actions.create") : undefined}
              onAction={
                canCreate
                  ? () =>
                      router.push({
                        pathname: routes.app.directoryContactCreate,
                        params: clientId ? { clientId } : undefined,
                      } as Href)
                  : undefined
              }
              title={t("app:directory.contacts.emptyTitle")}
            />
          ) : null}
          {!contactsQuery.isLoading && !contactsQuery.isError && contacts.length > 0 ? (
            <View style={{ gap: spacing[3] }}>
              {contacts.map((contact) => (
                <ContactCard
                  contact={contactToCardValue(contact)}
                  key={contact.id}
                  onPress={() =>
                    router.push({
                      pathname: routes.app.directoryContactDetail,
                      params: {
                        id: contact.id,
                        ...(clientId ? { returnClientId: clientId } : {}),
                      },
                    } as Href)
                  }
                />
              ))}
            </View>
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function ContactDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const params = useLocalSearchParams<{ id?: string; returnClientId?: string }>();
  const contactId = resolveRouteParam(params.id);
  const returnClientId = resolveRouteParam(params.returnClientId);
  const { hasPermission } = useWorkspace();
  const canView = hasPermission("contacts.view");
  const canEdit = hasPermission("contacts.edit");
  const canDelete = hasPermission("contacts.delete");
  const contactQuery = useContact(contactId);
  const deleteMutation = useDeleteContact(contactId ?? "");
  const [deleteError, setDeleteError] = useState<ApiError | null>(null);
  const [showDeleteConfirmation, setShowDeleteConfirmation] = useState(false);

  const navigateAfterDelete = async () => {
    try {
      setDeleteError(null);
      await deleteMutation.mutateAsync();
      router.replace(
        returnClientId
          ? ({
              pathname: routes.app.directoryClientDetail,
              params: { id: returnClientId },
            } as Href)
          : routes.app.directoryContacts
      );
    } catch (error) {
      if (isApiError(error)) {
        setDeleteError(error);
      }
    }
  };

  if (!canView) {
    return (
      <AppShell
        subtitle={t("directory.contacts.detail.subtitle")}
        title={t("directory.contacts.detail.title")}
      >
        <ForbiddenState
          description={t("directory.shared.forbiddenDescription")}
          onBack={() => router.replace(routes.app.directoryContacts)}
          title={t("directory.contacts.forbidden.title")}
        />
      </AppShell>
    );
  }

  if (!contactId) {
    return (
      <AppShell
        subtitle={t("directory.contacts.detail.subtitle")}
        title={t("directory.contacts.detail.title")}
      >
        <ErrorState
          detail={t("directory.shared.missingIdentifierDescription")}
          title={t("directory.shared.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  const contact = contactQuery.data?.data ?? null;

  return (
    <AppShell
      subtitle={contact?.client?.name ?? t("directory.contacts.detail.subtitle")}
      title={contact?.displayName ?? contact?.fullName ?? t("directory.contacts.detail.title")}
    >
      <View style={{ gap: spacing[4] }}>
        {contactQuery.isLoading ? <LoadingState title={t("directory.contacts.loading")} /> : null}
        {contactQuery.isError ? (
          <ErrorState
            detail={contactQuery.error instanceof Error ? contactQuery.error.message : undefined}
            onRetry={async () => {
              await contactQuery.refetch();
            }}
            title={t("directory.contacts.errorTitle")}
          />
        ) : null}
        {contact ? (
          <>
            <SectionCard
              action={
                canEdit ? (
                  <Button
                    label={t("directory.shared.actions.edit")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.directoryContactEdit,
                        params: {
                          id: contact.id,
                          ...(returnClientId ? { returnClientId } : {}),
                        },
                      } as Href)
                    }
                    size="sm"
                    variant="secondary"
                  />
                ) : null
              }
              description={t("directory.contacts.detail.summaryDescription")}
              title={t("directory.contacts.detail.summaryTitle")}
            >
              <ContactCard contact={contactToCardValue(contact)} />
            </SectionCard>
            <DetailCard
              rows={buildContactRows(contact, t)}
              subtitle={t("directory.contacts.detail.metadataSubtitle")}
              title={t("directory.contacts.detail.metadataTitle")}
            />
            {canDelete ? (
              <SectionCard
                description={t("directory.contacts.delete.description")}
                title={t("directory.contacts.delete.title")}
              >
                {!showDeleteConfirmation ? (
                  <Button
                    label={t("directory.shared.actions.delete")}
                    onPress={() => setShowDeleteConfirmation(true)}
                    variant="destructive"
                  />
                ) : (
                  <ConfirmationCard
                    confirmLabel={t("directory.shared.actions.delete")}
                    description={t("directory.contacts.delete.confirmationDescription")}
                    destructive
                    loading={deleteMutation.isPending}
                    onCancel={() => setShowDeleteConfirmation(false)}
                    onConfirm={navigateAfterDelete}
                    title={t("directory.contacts.delete.confirmationTitle")}
                  />
                )}
                {deleteError ? (
                  <ErrorRecoveryCard
                    description={deleteError.message}
                    onRetry={navigateAfterDelete}
                    safeDetail={describeContactDeleteConflict(deleteError, t)}
                    title={t("directory.shared.conflictTitle")}
                  />
                ) : null}
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function ContactCreateScreen() {
  return <ContactUpsertScreen mode="create" />;
}

export function ContactEditScreen() {
  return <ContactUpsertScreen mode="edit" />;
}

type ContactUpsertMode = "create" | "edit";

function ContactUpsertScreen({ mode }: { mode: ContactUpsertMode }) {
  const { t } = useTranslation("app");
  const params = useLocalSearchParams<{
    clientId?: string;
    id?: string;
    returnClientId?: string;
  }>();
  const contactId = resolveRouteParam(params.id);
  const presetClientId = resolveRouteParam(params.clientId) ?? "";
  const returnClientId = resolveRouteParam(params.returnClientId);
  const { hasPermission } = useWorkspace();
  const canCreate = hasPermission("contacts.create");
  const canEdit = hasPermission("contacts.edit");
  const canDelete = hasPermission("contacts.delete");
  const contactQuery = useContact(mode === "edit" ? contactId : null);
  const createMutation = useCreateContact();
  const updateMutation = useUpdateContact(contactId ?? "");
  const deleteMutation = useDeleteContact(contactId ?? "");
  const clientOptions = useClientPickerOptions();
  const [formErrors, setFormErrors] = useState<ContactFormErrors>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [deleteError, setDeleteError] = useState<ApiError | null>(null);
  const [showDeleteConfirmation, setShowDeleteConfirmation] = useState(false);

  const allowed = mode === "create" ? canCreate : canEdit;

  if (!allowed) {
    return (
      <AppShell
        subtitle={t("directory.contacts.form.subtitle")}
        title={mode === "create" ? t("directory.contacts.create.title") : t("directory.contacts.edit.title")}
      >
        <ForbiddenState
          description={t("directory.shared.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("directory.contacts.forbidden.title")}
        />
      </AppShell>
    );
  }

  const contact = contactQuery.data?.data ?? null;

  const initialValues = useMemo(
    () =>
      contact
        ? {
            clientId: contact.clientId ?? "",
            contactType: contact.contactType ?? "",
            displayName: contact.displayName ?? "",
            email: contact.email ?? "",
            firstName: contact.firstName,
            isPrimary: contact.isPrimary,
            jobTitle: contact.jobTitle ?? "",
            lastName: contact.lastName ?? "",
            notes: contact.notes ?? "",
            phone: contact.phone ?? "",
          }
        : {
            clientId: presetClientId,
          },
    [contact, presetClientId]
  );

    const navigateAfterContactSave = (clientIdValue?: string | null) => {
      if (returnClientId) {
      router.replace({
        pathname: routes.app.directoryClientDetail,
        params: { id: returnClientId },
      } as Href);
      return;
    }

    router.replace({
      pathname: routes.app.directoryContacts,
      params: clientIdValue ? { clientId: clientIdValue } : undefined,
    } as Href);
  };

  const handleSubmit = async (values: Parameters<typeof createMutation.mutateAsync>[0]) => {
    try {
      setFormErrors({});
      setSubmitError(null);

      const savedContact =
        mode === "create"
          ? await createMutation.mutateAsync(values)
          : await updateMutation.mutateAsync(values);

      navigateAfterContactSave(savedContact.clientId);
    } catch (error) {
      handleContactFormError(error, setFormErrors, setSubmitError);
    }
  };

  const handleDelete = async () => {
    try {
      setDeleteError(null);
      await deleteMutation.mutateAsync();
      navigateAfterContactSave(contact?.clientId ?? presetClientId);
    } catch (error) {
      if (isApiError(error)) {
        setDeleteError(error);
      }
    }
  };

  return (
    <AppShell
      subtitle={t("directory.contacts.form.subtitle")}
      title={
        mode === "create"
          ? t("directory.contacts.create.title")
          : t("directory.contacts.edit.title")
      }
    >
      <View style={{ gap: spacing[4] }}>
        {mode === "edit" && contactQuery.isLoading ? (
          <LoadingState title={t("directory.contacts.loading")} />
        ) : null}
        {mode === "edit" && contactQuery.isError ? (
          <ErrorState
            detail={contactQuery.error instanceof Error ? contactQuery.error.message : undefined}
            onRetry={async () => {
              await contactQuery.refetch();
            }}
            title={t("directory.contacts.errorTitle")}
          />
        ) : null}
        {(mode === "create" || contact) && (
          <ContactForm
            clientOptions={clientOptions}
            errorMessage={submitError}
            initialValues={initialValues}
            loading={createMutation.isPending || updateMutation.isPending}
            onCancel={() => router.back()}
            onSubmit={handleSubmit}
            submitLabel={
              mode === "create"
                ? t("directory.contacts.actions.create")
                : t("directory.shared.actions.save")
            }
            subtitle={t("directory.contacts.form.description")}
            title={
              mode === "create"
                ? t("directory.contacts.create.cardTitle")
                : t("directory.contacts.edit.cardTitle")
            }
            validationErrors={formErrors}
          />
        )}
        {mode === "edit" && canDelete && contact ? (
          <SectionCard
            description={t("directory.contacts.delete.description")}
            title={t("directory.contacts.delete.title")}
          >
            {!showDeleteConfirmation ? (
              <Button
                label={t("directory.shared.actions.delete")}
                onPress={() => setShowDeleteConfirmation(true)}
                variant="destructive"
              />
            ) : (
              <ConfirmationCard
                confirmLabel={t("directory.shared.actions.delete")}
                description={t("directory.contacts.delete.confirmationDescription")}
                destructive
                loading={deleteMutation.isPending}
                onCancel={() => setShowDeleteConfirmation(false)}
                onConfirm={handleDelete}
                title={t("directory.contacts.delete.confirmationTitle")}
              />
            )}
            {deleteError ? (
              <ErrorRecoveryCard
                description={deleteError.message}
                onRetry={handleDelete}
                safeDetail={describeContactDeleteConflict(deleteError, t)}
                title={t("directory.shared.conflictTitle")}
              />
            ) : null}
          </SectionCard>
        ) : null}
      </View>
    </AppShell>
  );
}

export function VenuesListScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<DirectoryStatus | "">("");
  const canView = hasPermission("venues.view");
  const canCreate = hasPermission("venues.create");
  const venuesQuery = useVenues({ search, status });

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:directory.venues.list.subtitle")}
        title={t("app:directory.venues.list.title")}
      >
        <ForbiddenState
          description={t("app:directory.shared.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:directory.venues.forbidden.title")}
        />
      </AppShell>
    );
  }

  const venues = venuesQuery.data?.data ?? [];

  return (
    <AppShell
      subtitle={t("app:directory.venues.list.subtitle")}
      title={t("app:directory.venues.list.title")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          action={
            canCreate ? (
              <Button
                label={t("app:directory.venues.actions.create")}
                onPress={() => router.push(routes.app.directoryVenueCreate)}
                size="sm"
              />
            ) : null
          }
          description={t("app:directory.venues.list.filtersDescription")}
          title={t("app:directory.shared.filtersTitle")}
        >
          <View style={{ gap: spacing[3] }}>
            <SearchInput
              accessibilityLabel={t("app:directory.venues.search.accessibilityLabel")}
              onChangeText={setSearch}
              placeholder={t("app:directory.venues.search.placeholder")}
              value={search}
            />
            <StatusSelect
              accessibilityLabel={t("app:directory.shared.statusFilter.accessibilityLabel")}
              label={t("app:directory.shared.statusFilter.label")}
              namespace="workspaceMembers"
              onChange={(value) => setStatus(value as DirectoryStatus)}
              options={STATUS_OPTIONS}
              value={status || undefined}
            />
            {status ? (
              <Button
                label={t("common:clearSearch")}
                onPress={() => setStatus("")}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        </SectionCard>

        <SectionCard
          description={t("app:directory.venues.list.resultsDescription")}
          title={t("app:directory.venues.list.resultsTitle", { count: venues.length })}
        >
          {venuesQuery.isLoading ? <LoadingState title={t("app:directory.venues.loading")} /> : null}
          {venuesQuery.isError ? (
            <ErrorState
              detail={venuesQuery.error instanceof Error ? venuesQuery.error.message : undefined}
              onRetry={async () => {
                await venuesQuery.refetch();
              }}
              title={t("app:directory.venues.errorTitle")}
            />
          ) : null}
          {!venuesQuery.isLoading && !venuesQuery.isError && venues.length === 0 ? (
            <EmptyState
              actionLabel={canCreate ? t("app:directory.venues.actions.create") : undefined}
              onAction={
                canCreate ? () => router.push(routes.app.directoryVenueCreate) : undefined
              }
              title={t("app:directory.venues.emptyTitle")}
            />
          ) : null}
          {!venuesQuery.isLoading && !venuesQuery.isError && venues.length > 0 ? (
            <View style={{ gap: spacing[3] }}>
              {venues.map((venue) => (
                <VenueCard
                  key={venue.id}
                  onPress={() =>
                    router.push({
                      pathname: routes.app.directoryVenueDetail,
                      params: { id: venue.id },
                    } as Href)
                  }
                  venue={venueToCardValue(venue)}
                />
              ))}
            </View>
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function VenueDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const venueId = resolveRouteParam(useLocalSearchParams<{ id?: string }>().id);
  const canView = hasPermission("venues.view");
  const canEdit = hasPermission("venues.edit");
  const canDelete = hasPermission("venues.delete");
  const venueQuery = useVenue(venueId);
  const deleteMutation = useDeleteVenue(venueId ?? "");
  const [deleteError, setDeleteError] = useState<ApiError | null>(null);
  const [showDeleteConfirmation, setShowDeleteConfirmation] = useState(false);

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:directory.venues.detail.subtitle")}
        title={t("app:directory.venues.detail.title")}
      >
        <ForbiddenState
          description={t("app:directory.shared.forbiddenDescription")}
          onBack={() => router.replace(routes.app.directoryVenues)}
          title={t("app:directory.venues.forbidden.title")}
        />
      </AppShell>
    );
  }

  if (!venueId) {
    return (
      <AppShell
        subtitle={t("app:directory.venues.detail.subtitle")}
        title={t("app:directory.venues.detail.title")}
      >
        <ErrorState
          title={t("app:directory.shared.missingIdentifierTitle")}
          detail={t("app:directory.shared.missingIdentifierDescription")}
        />
      </AppShell>
    );
  }

  const venue = venueQuery.data?.data ?? null;

  const handleDelete = async () => {
    try {
      setDeleteError(null);
      await deleteMutation.mutateAsync();
      router.replace(routes.app.directoryVenues);
    } catch (error) {
      if (isApiError(error)) {
        setDeleteError(error);
      }
    }
  };

  return (
    <AppShell
      subtitle={venue ? formatVenueLocation(venue) : t("app:directory.venues.detail.subtitle")}
      title={venue?.name ?? t("app:directory.venues.detail.title")}
    >
      <View style={{ gap: spacing[4] }}>
        {venueQuery.isLoading ? <LoadingState title={t("app:directory.venues.loading")} /> : null}
        {venueQuery.isError ? (
          <ErrorState
            detail={venueQuery.error instanceof Error ? venueQuery.error.message : undefined}
            onRetry={async () => {
              await venueQuery.refetch();
            }}
            title={t("app:directory.venues.errorTitle")}
          />
        ) : null}
        {venue ? (
          <>
            <SectionCard
              action={
                canEdit ? (
                  <Button
                    label={t("app:directory.shared.actions.edit")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.directoryVenueEdit,
                        params: { id: venue.id },
                      } as Href)
                    }
                    size="sm"
                    variant="secondary"
                  />
                ) : null
              }
              description={t("app:directory.venues.detail.summaryDescription")}
              title={t("app:directory.venues.detail.summaryTitle")}
            >
              <VenueCard venue={venueToCardValue(venue)} />
            </SectionCard>

            <DetailCard
              rows={buildVenueRows(venue, t)}
              subtitle={t("app:directory.venues.detail.metadataSubtitle")}
              title={t("app:directory.venues.detail.metadataTitle")}
            />

            {canDelete ? (
              <SectionCard
                description={t("app:directory.venues.delete.description")}
                title={t("app:directory.venues.delete.title")}
              >
                {!showDeleteConfirmation ? (
                  <Button
                    label={t("app:directory.shared.actions.delete")}
                    onPress={() => setShowDeleteConfirmation(true)}
                    variant="destructive"
                  />
                ) : (
                  <ConfirmationCard
                    confirmLabel={t("app:directory.shared.actions.delete")}
                    description={t("app:directory.venues.delete.confirmationDescription")}
                    destructive
                    loading={deleteMutation.isPending}
                    onCancel={() => setShowDeleteConfirmation(false)}
                    onConfirm={handleDelete}
                    title={t("app:directory.venues.delete.confirmationTitle")}
                  />
                )}
                {deleteError ? (
                  <ErrorRecoveryCard
                    description={deleteError.message}
                    onRetry={handleDelete}
                    safeDetail={describeVenueDeleteConflict(deleteError, t)}
                    title={t("app:directory.shared.conflictTitle")}
                  />
                ) : null}
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function VenueCreateScreen() {
  return <VenueUpsertScreen mode="create" />;
}

export function VenueEditScreen() {
  return <VenueUpsertScreen mode="edit" />;
}

type VenueUpsertMode = "create" | "edit";

function VenueUpsertScreen({ mode }: { mode: VenueUpsertMode }) {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const venueId = resolveRouteParam(useLocalSearchParams<{ id?: string }>().id);
  const canCreate = hasPermission("venues.create");
  const canEdit = hasPermission("venues.edit");
  const venueQuery = useVenue(mode === "edit" ? venueId : null);
  const createMutation = useCreateVenue();
  const updateMutation = useUpdateVenue(venueId ?? "");
  const [formErrors, setFormErrors] = useState<VenueFormErrors>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  const allowed = mode === "create" ? canCreate : canEdit;

  if (!allowed) {
    return (
      <AppShell
        subtitle={t("directory.venues.form.subtitle")}
        title={mode === "create" ? t("directory.venues.create.title") : t("directory.venues.edit.title")}
      >
        <ForbiddenState
          description={t("directory.shared.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("directory.venues.forbidden.title")}
        />
      </AppShell>
    );
  }

  const venue = venueQuery.data?.data ?? null;
  const initialValues = useMemo(
    () =>
      venue
        ? {
            accessInstructions: venue.accessInstructions ?? "",
            addressLine1: venue.addressLine1 ?? "",
            addressLine2: venue.addressLine2 ?? "",
            capacity: venue.capacity === null ? "" : String(venue.capacity),
            city: venue.city ?? "",
            contactEmail: venue.contactEmail ?? "",
            contactName: venue.contactName ?? "",
            contactPhone: venue.contactPhone ?? "",
            countryCode: venue.countryCode ?? "",
            kitchenNotes: venue.kitchenNotes ?? "",
            latitude: venue.latitude ?? "",
            loadingNotes: venue.loadingNotes ?? "",
            longitude: venue.longitude ?? "",
            name: venue.name,
            notes: venue.notes ?? "",
            parkingNotes: venue.parkingNotes ?? "",
            postalCode: venue.postalCode ?? "",
            state: venue.state ?? "",
            status: venue.status ?? "active",
            timezone: venue.timezone ?? "",
          }
        : undefined,
    [venue]
  );

  const handleSubmit = async (values: Parameters<typeof createMutation.mutateAsync>[0]) => {
    try {
      setFormErrors({});
      setSubmitError(null);

      const savedVenue =
        mode === "create"
          ? await createMutation.mutateAsync(values)
          : await updateMutation.mutateAsync(values);

      router.replace({
        pathname: routes.app.directoryVenueDetail,
        params: { id: savedVenue.id },
      } as Href);
    } catch (error) {
      handleVenueFormError(error, setFormErrors, setSubmitError);
    }
  };

  return (
    <AppShell
      subtitle={t("directory.venues.form.subtitle")}
      title={
        mode === "create" ? t("directory.venues.create.title") : t("directory.venues.edit.title")
      }
    >
      <View style={{ gap: spacing[4] }}>
        {mode === "edit" && venueQuery.isLoading ? (
          <LoadingState title={t("directory.venues.loading")} />
        ) : null}
        {mode === "edit" && venueQuery.isError ? (
          <ErrorState
            detail={venueQuery.error instanceof Error ? venueQuery.error.message : undefined}
            onRetry={async () => {
              await venueQuery.refetch();
            }}
            title={t("directory.venues.errorTitle")}
          />
        ) : null}
        {(mode === "create" || venue) && (
          <VenueForm
            errorMessage={submitError}
            initialValues={initialValues}
            loading={createMutation.isPending || updateMutation.isPending}
            onCancel={() => router.back()}
            onSubmit={handleSubmit}
            submitLabel={
              mode === "create"
                ? t("directory.venues.actions.create")
                : t("directory.shared.actions.save")
            }
            subtitle={t("directory.venues.form.description")}
            title={
              mode === "create"
                ? t("directory.venues.create.cardTitle")
                : t("directory.venues.edit.cardTitle")
            }
            validationErrors={formErrors}
          />
        )}
      </View>
    </AppShell>
  );
}

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function buildClientRows(client: ClientRecord, t: (key: string, options?: Record<string, unknown>) => string) {
  const addressLines = formatAddressLines(
    client.addressLine1,
    client.addressLine2,
    client.city,
    client.state,
    client.postalCode,
    client.countryCode
  );

  return [
    {
      label: t("directory.clients.form.fields.email.label"),
      value: client.email ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.clients.form.fields.phone.label"),
      value: client.phone ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.clients.form.fields.website.label"),
      value: client.website ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.clients.form.fields.taxId.label"),
      value: client.taxId ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.clients.form.fields.addressLine1.label"),
      value: addressLines.length > 0 ? addressLines.join("\n") : t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.clients.form.fields.status.label"),
      value: client.status ? t(`common:status.${client.status}`) : t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.clients.detail.contactsCount"),
      value: String(client.contactsCount ?? 0),
    },
    {
      label: t("directory.clients.form.fields.notes.label"),
      value: client.notes ?? t("directory.shared.emptyValue"),
    },
  ];
}

function buildVenueRows(venue: VenueRecord, t: (key: string, options?: Record<string, unknown>) => string) {
  const addressLines = formatAddressLines(
    venue.addressLine1,
    venue.addressLine2,
    venue.city,
    venue.state,
    venue.postalCode,
    venue.countryCode
  );

  return [
    {
      label: t("directory.venues.form.fields.addressLine1.label"),
      value: addressLines.length > 0 ? addressLines.join("\n") : t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.timezone.label"),
      value: venue.timezone ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.contactName.label"),
      value: venue.contactName ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.contactEmail.label"),
      value: venue.contactEmail ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.contactPhone.label"),
      value: venue.contactPhone ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.capacity.label"),
      value:
        venue.capacity === null ? t("directory.shared.emptyValue") : String(venue.capacity),
    },
    {
      label: t("directory.venues.form.fields.status.label"),
      value: venue.status ? t(`common:status.${venue.status}`) : t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.accessInstructions.label"),
      value: venue.accessInstructions ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.parkingNotes.label"),
      value: venue.parkingNotes ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.loadingNotes.label"),
      value: venue.loadingNotes ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.kitchenNotes.label"),
      value: venue.kitchenNotes ?? t("directory.shared.emptyValue"),
    },
    {
      label: t("directory.venues.form.fields.notes.label"),
      value: venue.notes ?? t("directory.shared.emptyValue"),
    },
  ];
}

function buildContactRows(
  contact: {
    client: { name: string; companyName: string | null } | null;
    contactType: string | null;
    email: string | null;
    isPrimary: boolean;
    jobTitle: string | null;
    notes: string | null;
    phone: string | null;
  },
  t: (key: string, options?: Record<string, unknown>) => string
) {
  const emptyValue = t("directory.shared.emptyValue");

  return [
    {
      label: t("directory.contacts.form.fields.client.label"),
      value: contact.client?.companyName ?? contact.client?.name ?? emptyValue,
    },
    {
      label: t("directory.contacts.form.fields.email.label"),
      value: contact.email ?? emptyValue,
    },
    {
      label: t("directory.contacts.form.fields.phone.label"),
      value: contact.phone ?? emptyValue,
    },
    {
      label: t("directory.contacts.form.fields.jobTitle.label"),
      value: contact.jobTitle ?? emptyValue,
    },
    {
      label: t("directory.contacts.form.fields.contactType.label"),
      value: contact.contactType ?? emptyValue,
    },
    {
      label: t("directory.contacts.form.fields.isPrimary.label"),
      value: contact.isPrimary ? t("common:yes") : t("common:no"),
    },
    {
      label: t("directory.contacts.form.fields.notes.label"),
      value: contact.notes ?? emptyValue,
    },
  ];
}

function handleClientFormError(
  error: unknown,
  setFormErrors: (value: ClientFormErrors) => void,
  setSubmitError: (value: string | null) => void
) {
  if (!isApiError(error)) {
    setSubmitError(error instanceof Error ? error.message : null);
    return;
  }

  setSubmitError(error.message);
  setFormErrors({
    addressLine1: firstFieldMessage(error, "address_line_1"),
    addressLine2: firstFieldMessage(error, "address_line_2"),
    city: firstFieldMessage(error, "city"),
    companyName: firstFieldMessage(error, "company_name"),
    countryCode: firstFieldMessage(error, "country_code"),
    email: firstFieldMessage(error, "email"),
    name: firstFieldMessage(error, "name"),
    notes: firstFieldMessage(error, "notes"),
    phone: firstFieldMessage(error, "phone"),
    postalCode: firstFieldMessage(error, "postal_code"),
    state: firstFieldMessage(error, "state"),
    status: firstFieldMessage(error, "status"),
    taxId: firstFieldMessage(error, "tax_id"),
    website: firstFieldMessage(error, "website"),
  });
}

function handleContactFormError(
  error: unknown,
  setFormErrors: (value: ContactFormErrors) => void,
  setSubmitError: (value: string | null) => void
) {
  if (!isApiError(error)) {
    setSubmitError(error instanceof Error ? error.message : null);
    return;
  }

  setSubmitError(error.message);
  setFormErrors({
    clientId: firstFieldMessage(error, "client_id"),
    contactType: firstFieldMessage(error, "contact_type"),
    displayName: firstFieldMessage(error, "display_name"),
    email: firstFieldMessage(error, "email"),
    firstName: firstFieldMessage(error, "first_name"),
    jobTitle: firstFieldMessage(error, "job_title"),
    lastName: firstFieldMessage(error, "last_name"),
    notes: firstFieldMessage(error, "notes"),
    phone: firstFieldMessage(error, "phone"),
  });
}

function handleVenueFormError(
  error: unknown,
  setFormErrors: (value: VenueFormErrors) => void,
  setSubmitError: (value: string | null) => void
) {
  if (!isApiError(error)) {
    setSubmitError(error instanceof Error ? error.message : null);
    return;
  }

  setSubmitError(error.message);
  setFormErrors({
    accessInstructions: firstFieldMessage(error, "access_instructions"),
    addressLine1: firstFieldMessage(error, "address_line_1"),
    addressLine2: firstFieldMessage(error, "address_line_2"),
    capacity: firstFieldMessage(error, "capacity"),
    city: firstFieldMessage(error, "city"),
    contactEmail: firstFieldMessage(error, "contact_email"),
    contactName: firstFieldMessage(error, "contact_name"),
    contactPhone: firstFieldMessage(error, "contact_phone"),
    countryCode: firstFieldMessage(error, "country_code"),
    kitchenNotes: firstFieldMessage(error, "kitchen_notes"),
    latitude: firstFieldMessage(error, "latitude"),
    loadingNotes: firstFieldMessage(error, "loading_notes"),
    longitude: firstFieldMessage(error, "longitude"),
    name: firstFieldMessage(error, "name"),
    notes: firstFieldMessage(error, "notes"),
    parkingNotes: firstFieldMessage(error, "parking_notes"),
    postalCode: firstFieldMessage(error, "postal_code"),
    state: firstFieldMessage(error, "state"),
    status: firstFieldMessage(error, "status"),
    timezone: firstFieldMessage(error, "timezone"),
  });
}

function firstFieldMessage(error: ApiError, field: string) {
  return error.fieldErrors?.[field]?.[0];
}

function describeClientDeleteConflict(
  error: ApiError,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  const details = error.details;

  if (!details || typeof details !== "object") {
    return error.message;
  }

  const contactsCount = getNumericDetail(details, "contacts_count");
  const eventsCount = getNumericDetail(details, "events_count");

  return t("directory.clients.delete.conflictDetail", {
    contactsCount,
    eventsCount,
  });
}

function describeVenueDeleteConflict(
  error: ApiError,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  const details = error.details;

  if (!details || typeof details !== "object") {
    return error.message;
  }

  return t("directory.venues.delete.conflictDetail", {
    eventsCount: getNumericDetail(details, "events_count"),
  });
}

function describeContactDeleteConflict(
  error: ApiError,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  const details = error.details;

  if (!details || typeof details !== "object") {
    return error.message;
  }

  return t("directory.contacts.delete.conflictDetail", {
    eventsCount: getNumericDetail(details, "events_count"),
  });
}

function getNumericDetail(details: object, key: string) {
  const record = details as Record<string, unknown>;
  const value = record[key];
  return typeof value === "number" ? value : 0;
}

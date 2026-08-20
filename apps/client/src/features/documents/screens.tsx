import { router, useLocalSearchParams, type Href } from "expo-router";
import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { isApiError } from "@/api/types";
import { AppShell } from "@/components/patterns/AppShell";
import { BEOChangeAlert } from "@/components/patterns/beo-change-alert";
import { BEOChangeReview } from "@/components/patterns/beo-change-review";
import { BEOConflictAlert } from "@/components/patterns/beo-conflict-alert";
import { BEOEventLinkCard } from "@/components/patterns/beo-event-link-card";
import { BEOExtractionReview } from "@/components/patterns/beo-extraction-review";
import { BEOImpactSummary } from "@/components/patterns/beo-impact-summary";
import { BEOProcessingState } from "@/components/patterns/beo-processing-state";
import { BEOUploadForm } from "@/components/patterns/beo-upload-form";
import { BEOVersionCard } from "@/components/patterns/beo-version-card";
import { BEOVersionComparison } from "@/components/patterns/beo-version-comparison";
import { BEOViewer } from "@/components/patterns/beo-viewer";
import { DocumentCard } from "@/components/patterns/document-card";
import { DocumentList } from "@/components/patterns/document-list";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { SectionCard } from "@/components/patterns/SectionCard";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  type BEOExtractionReviewValidationErrors,
  type ExtractedFieldRecord,
  useDocument,
  useDocumentComparison,
  useDocumentExtraction,
  useDocumentVersions,
  useDocuments,
  useLinkDocumentToEvent,
  useReviewDocumentExtraction,
  useUploadDocument,
} from "@/features/documents";
import { useEvents } from "@/features/events";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";
import { useWorkspace } from "@/features/workspace";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function mapUploadErrors(error: unknown) {
  if (!isApiError(error)) {
    return {
      file: undefined,
      form: error instanceof Error ? error.message : undefined,
    };
  }

  return {
    file: error.fieldErrors?.file?.[0],
    form: error.message,
  };
}

export function DocumentsListScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const eventId = resolveRouteParam(
    useLocalSearchParams<{ eventId?: string }>().eventId
  );
  const canCreate = hasPermission("events.create") || hasPermission("events.edit");
  const canView = hasPermission("events.view");
  const documentsQuery = useDocuments({
    eventId,
    type: "beo",
  });

  if (!canView) {
    return (
      <AppShell
        subtitle={t("documents.subtitle")}
        title={t("documents.title")}
      >
        <ForbiddenState
          description={t("documents.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("documents.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={eventId ? t("documents.filteredSubtitle") : t("documents.subtitle")}
      title={t("documents.title")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          action={
            canCreate ? (
              <Button
                label={t("documents.actions.upload")}
                onPress={() =>
                  router.push({
                    pathname: routes.app.documentUpload,
                    params: eventId ? { eventId } : undefined,
                  } as Href)
                }
                size="sm"
              />
            ) : null
          }
          description={t("documents.listDescription")}
          title={t("documents.listTitle", { count: documentsQuery.documents.length })}
        >
          <DocumentList
            documents={documentsQuery.documents}
            error={
              documentsQuery.isError && documentsQuery.error instanceof Error
                ? documentsQuery.error.message
                : undefined
            }
            loading={documentsQuery.isLoading}
            onDocumentPress={(document) =>
              router.push({
                pathname: routes.app.documentDetail,
                params: { documentId: document.id },
              } as Href)
            }
            onEndReached={() => {
              if (documentsQuery.hasNextPage && !documentsQuery.isFetchingNextPage) {
                void documentsQuery.fetchNextPage();
              }
            }}
            onRefresh={async () => {
              await documentsQuery.refetch();
            }}
            refreshing={documentsQuery.isRefetching}
          />
          {documentsQuery.isFetchingNextPage ? (
            <Text tone="muted" variant="bodySmall">
              {t("documents.loadingMore")}
            </Text>
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function DocumentUploadScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const eventIdParam = resolveRouteParam(
    useLocalSearchParams<{ eventId?: string }>().eventId
  );
  const canCreate = hasPermission("events.create") || hasPermission("events.edit");
  const eventsQuery = useEvents({ perPage: 100 });
  const uploadMutation = useUploadDocument();
  const [selectedEventId, setSelectedEventId] = useState<string | null>(eventIdParam);
  const selectedEvent =
    eventsQuery.events.find((event) => event.id === selectedEventId) ?? null;

  if (!canCreate) {
    return (
      <AppShell
        subtitle={t("documents.uploadSubtitle")}
        title={t("documents.uploadTitle")}
      >
        <ForbiddenState
          description={t("documents.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("documents.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  const uploadErrors = mapUploadErrors(uploadMutation.error);

  return (
    <AppShell
      subtitle={t("documents.uploadSubtitle")}
      title={t("documents.uploadTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          description={t("documents.uploadDescription")}
          title={t("documents.uploadCardTitle")}
        >
          <View style={{ gap: spacing[4] }}>
            <BEOEventLinkCard
              editable
              event={selectedEvent}
              events={eventsQuery.events}
              onLink={async (eventId) => {
                setSelectedEventId(eventId);
              }}
            />
            <BEOUploadForm
              acceptedTypes={["application/pdf", "image/jpeg", "image/png", "image/webp"]}
              maxSize={10 * 1024 * 1024}
              onCancel={() => router.back()}
              onSubmit={async (file) => {
                const result = await uploadMutation.mutateAsync({
                  eventId: selectedEventId,
                  file,
                  source: "upload",
                  type: "beo",
                });

                router.replace({
                  pathname: routes.app.documentDetail,
                  params: { documentId: result.document.id },
                } as Href);
              }}
              submitting={uploadMutation.isPending}
              validationErrors={uploadErrors}
            />
          </View>
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function DocumentDetailScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const documentId = resolveRouteParam(
    useLocalSearchParams<{ documentId?: string }>().documentId
  );
  const canView = hasPermission("events.view");
  const canEdit = hasPermission("events.edit");
  const documentQuery = useDocument(documentId);
  const versionsQuery = useDocumentVersions(documentId);
  const extractionQuery = useDocumentExtraction(documentId);
  const comparisonQuery = useDocumentComparison(documentId);
  const eventsQuery = useEvents({ perPage: 100 });
  const linkMutation = useLinkDocumentToEvent(documentId ?? "");
  const detail = documentQuery.data ?? null;
  const document = detail?.document ?? null;
  const versions = versionsQuery.data ?? [];
  const extraction = extractionQuery.data?.extraction ?? null;
  const comparison = comparisonQuery.data ?? null;

  if (!canView) {
    return (
      <AppShell
        subtitle={t("documents.detailSubtitle")}
        title={t("documents.detailTitle")}
      >
        <ForbiddenState
          description={t("documents.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("documents.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!documentId) {
    return (
      <AppShell
        subtitle={t("documents.detailSubtitle")}
        title={t("documents.detailTitle")}
      >
        <ErrorState
          detail={t("documents.missingIdentifierDescription")}
          title={t("documents.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={document?.type ?? t("documents.detailSubtitle")}
      title={document?.name ?? t("documents.detailTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        {documentQuery.isLoading ? <LoadingState title={t("documents.loading")} /> : null}
        {documentQuery.isError ? (
          <ErrorState
            detail={documentQuery.error instanceof Error ? documentQuery.error.message : undefined}
            onRetry={async () => {
              await documentQuery.refetch();
            }}
            title={t("documents.errorTitle")}
          />
        ) : null}
        {document ? (
          <>
            <SectionCard
              action={
                <View style={{ flexDirection: "row", gap: spacing[2] }}>
                  {extraction?.run ? (
                    <Button
                      label={t("documents.actions.review")}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.documentReview,
                          params: { documentId: document.id },
                        } as Href)
                      }
                      size="sm"
                      variant="secondary"
                    />
                  ) : null}
                  <Button
                    label={t("documents.actions.versions")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.documentVersions,
                        params: { documentId: document.id },
                      } as Href)
                    }
                    size="sm"
                  />
                </View>
              }
              description={t("documents.detailDescription")}
              title={t("documents.detailCardTitle")}
            >
              <View style={{ gap: spacing[4] }}>
                <DocumentCard
                  document={document}
                  latestVersion={document.latestBeoVersion}
                />
                <BEOProcessingState
                  document={document}
                  extractionRun={extraction?.run ?? document.latestExtractionRun}
                />
                <BEOViewer
                  document={document}
                  sourceUri={document.downloadUrl}
                  structuredData={document.latestBeoVersion?.snapshotJson ?? null}
                  version={document.latestBeoVersion}
                />
              </View>
            </SectionCard>

            <SectionCard
              description={t("documents.eventLinkDescription")}
              title={t("documents.eventLinkTitle")}
            >
              <BEOEventLinkCard
                beo={detail?.beo ?? undefined}
                disabled={linkMutation.isPending || !canEdit}
                document={document}
                editable={!document.linkedEvent && canEdit}
                event={document.linkedEvent ?? detail?.beo?.event ?? null}
                events={eventsQuery.events}
                onLink={
                  canEdit
                    ? async (eventId) => {
                        await linkMutation.mutateAsync(eventId);
                      }
                    : undefined
                }
              />
            </SectionCard>

            {comparison && comparison.changes.length ? (
              <BEOChangeAlert
                changeCount={comparison.changes.length}
                impacts={comparison.impacts}
                newVersion={comparison.targetVersion}
                onReview={() =>
                  router.push({
                    pathname: routes.app.documentChangeReview,
                    params: { documentId: document.id },
                  } as Href)
                }
                previousVersion={comparison.baseVersion ?? null}
              />
            ) : null}

            {document.latestBeoVersion ? (
              <SectionCard
                description={t("documents.latestVersionDescription")}
                title={t("documents.latestVersionTitle")}
              >
                <BEOVersionCard
                  isCurrent
                  onCompare={
                    comparison?.changes.length
                      ? () =>
                          router.push({
                            pathname: routes.app.documentChangeReview,
                            params: { documentId: document.id },
                          } as Href)
                      : undefined
                  }
                  version={document.latestBeoVersion}
                />
              </SectionCard>
            ) : null}

            {versions.length > 1 ? (
              <SectionCard
                description={t("documents.versionHistoryDescription")}
                title={t("documents.versionHistoryTitle")}
              >
                <View style={{ gap: spacing[3] }}>
                  {versions.slice(0, 3).map((version) => (
                    <BEOVersionCard
                      key={version.id}
                      isCurrent={version.id === document.latestBeoVersion?.id}
                      onCompare={
                        comparison?.changes.length
                          ? () =>
                              router.push({
                                pathname: routes.app.documentChangeReview,
                                params: { documentId: document.id },
                              } as Href)
                          : undefined
                      }
                      version={version}
                    />
                  ))}
                </View>
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function DocumentReviewScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const documentId = resolveRouteParam(
    useLocalSearchParams<{ documentId?: string }>().documentId
  );
  const canView = hasPermission("events.view");
  const canEdit = hasPermission("events.edit");
  const extractionQuery = useDocumentExtraction(documentId);
  const reviewMutation = useReviewDocumentExtraction(documentId ?? "");
  const [draftFields, setDraftFields] = useState<Record<string, ExtractedFieldRecord | undefined>>({});
  const [validationErrors, setValidationErrors] =
    useState<BEOExtractionReviewValidationErrors>({});
  const extraction = extractionQuery.data?.extraction ?? null;
  const document = extractionQuery.data?.document ?? null;

  if (!canView || !canEdit) {
    return (
      <AppShell
        subtitle={t("documents.reviewSubtitle")}
        title={t("documents.reviewTitle")}
      >
        <ForbiddenState
          description={t("documents.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("documents.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!documentId) {
    return (
      <AppShell
        subtitle={t("documents.reviewSubtitle")}
        title={t("documents.reviewTitle")}
      >
        <ErrorState
          detail={t("documents.missingIdentifierDescription")}
          title={t("documents.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  const conflictData =
    isApiError(reviewMutation.error) && reviewMutation.error.kind === "conflict"
      ? reviewMutation.error.details
      : null;

  return (
    <AppShell
      subtitle={t("documents.reviewSubtitle")}
      title={t("documents.reviewTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        {extractionQuery.isLoading ? <LoadingState title={t("documents.loading")} /> : null}
        {extractionQuery.isError ? (
          <ErrorState
            detail={extractionQuery.error instanceof Error ? extractionQuery.error.message : undefined}
            onRetry={async () => {
              await extractionQuery.refetch();
            }}
            title={t("documents.errorTitle")}
          />
        ) : null}
        {conflictData ? (
          <BEOConflictAlert
            conflictType="http_409"
            onReload={async () => {
              setDraftFields({});
              await extractionQuery.refetch();
            }}
          />
        ) : null}
        {document && extraction?.run ? (
          <BEOExtractionReview
            document={document}
            draftCorrections={draftFields}
            extraction={extraction}
            onApprove={async (fields) => {
              setValidationErrors({});

              try {
                await reviewMutation.mutateAsync({
                  expectedUpdatedAt: extraction.run?.updatedAt ?? "",
                  fields: fields.map((field) => ({
                    correctedValueJson: field.correctedValueJson,
                    correctedValueText: field.correctedValueText,
                    id: field.id,
                    reviewNotes: field.reviewNotes,
                    reviewStatus: field.reviewStatus ?? "pending",
                  })),
                });

                router.replace({
                  pathname: routes.app.documentDetail,
                  params: { documentId },
                } as Href);
              } catch (error) {
                if (isApiError(error) && error.kind === "validation") {
                  setValidationErrors({
                    form: error.message,
                  });
                }
              }
            }}
            onCancel={() => router.back()}
            onFieldChange={async (field) => {
              setDraftFields((current) => ({
                ...current,
                [field.id]: field,
              }));
            }}
            sourceUri={document.downloadUrl}
            submitting={reviewMutation.isPending}
            validationErrors={validationErrors}
            version={document.latestBeoVersion}
          />
        ) : extraction && !extraction.run ? (
          <EmptyState
            description={t("documents.reviewEmptyDescription")}
            title={t("documents.reviewEmptyTitle")}
          />
        ) : null}
      </View>
    </AppShell>
  );
}

export function DocumentVersionsScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const documentId = resolveRouteParam(
    useLocalSearchParams<{ documentId?: string }>().documentId
  );
  const canView = hasPermission("events.view");
  const documentQuery = useDocument(documentId);
  const versionsQuery = useDocumentVersions(documentId);
  const comparisonQuery = useDocumentComparison(documentId);
  const detail = documentQuery.data ?? null;
  const document = detail?.document ?? null;
  const versions = versionsQuery.data ?? [];
  const comparison = comparisonQuery.data ?? null;

  if (!canView) {
    return (
      <AppShell
        subtitle={t("documents.versionHistorySubtitle")}
        title={t("documents.versionHistoryTitle")}
      >
        <ForbiddenState
          description={t("documents.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("documents.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!documentId) {
    return (
      <AppShell
        subtitle={t("documents.versionHistorySubtitle")}
        title={t("documents.versionHistoryTitle")}
      >
        <ErrorState
          detail={t("documents.missingIdentifierDescription")}
          title={t("documents.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={t("documents.versionHistorySubtitle")}
      title={t("documents.versionHistoryTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        {(documentQuery.isLoading || versionsQuery.isLoading) ? (
          <LoadingState title={t("documents.loading")} />
        ) : null}
        {documentQuery.isError || versionsQuery.isError ? (
          <ErrorState
            detail={
              (documentQuery.error instanceof Error
                ? documentQuery.error.message
                : versionsQuery.error instanceof Error
                ? versionsQuery.error.message
                : undefined)
            }
            onRetry={async () => {
              await Promise.all([documentQuery.refetch(), versionsQuery.refetch()]);
            }}
            title={t("documents.errorTitle")}
          />
        ) : null}
        {document ? (
          <SectionCard
            action={
              comparison?.changes.length ? (
                <Button
                  label={t("documents.actions.reviewChanges")}
                  onPress={() =>
                    router.push({
                      pathname: routes.app.documentChangeReview,
                      params: { documentId: document.id },
                    } as Href)
                  }
                  size="sm"
                />
              ) : null
            }
            description={t("documents.versionHistoryDescription")}
            title={t("documents.versionHistoryTitle")}
          >
            <View style={{ gap: spacing[3] }}>
              {versions.map((version) => (
                <BEOVersionCard
                  key={version.id}
                  isCurrent={version.id === document.latestBeoVersion?.id}
                  version={version}
                />
              ))}
            </View>
          </SectionCard>
        ) : null}
        {comparison?.changes.length ? (
          <>
            <BEOVersionComparison
              baseVersion={comparison.baseVersion ?? comparison.targetVersion}
              changes={comparison.changes}
              sections={comparison.sections}
              targetVersion={comparison.targetVersion}
            />
            <BEOImpactSummary impacts={comparison.impacts ?? []} />
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function DocumentChangeReviewScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const documentId = resolveRouteParam(
    useLocalSearchParams<{ documentId?: string }>().documentId
  );
  const canView = hasPermission("events.view");
  const comparisonQuery = useDocumentComparison(documentId);
  const comparison = comparisonQuery.data ?? null;

  if (!canView) {
    return (
      <AppShell
        subtitle={t("documents.changeReviewSubtitle")}
        title={t("documents.changeReviewTitle")}
      >
        <ForbiddenState
          description={t("documents.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("documents.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!documentId) {
    return (
      <AppShell
        subtitle={t("documents.changeReviewSubtitle")}
        title={t("documents.changeReviewTitle")}
      >
        <ErrorState
          detail={t("documents.missingIdentifierDescription")}
          title={t("documents.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={t("documents.changeReviewSubtitle")}
      title={t("documents.changeReviewTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        {comparisonQuery.isLoading ? <LoadingState title={t("documents.loading")} /> : null}
        {comparisonQuery.isError ? (
          <ErrorState
            detail={comparisonQuery.error instanceof Error ? comparisonQuery.error.message : undefined}
            onRetry={async () => {
              await comparisonQuery.refetch();
            }}
            title={t("documents.errorTitle")}
          />
        ) : null}
        {comparison ? (
          <BEOChangeReview
            changes={comparison.changes}
            impacts={comparison.impacts}
            newVersion={comparison.targetVersion}
            onCancel={() => router.back()}
            onConfirm={async () => {
              router.back();
            }}
            previousVersion={comparison.baseVersion ?? comparison.targetVersion}
            warnings={comparison.warnings}
          />
        ) : null}
      </View>
    </AppShell>
  );
}

import type { AppTheme } from "@/theme/tokens";

export type SemanticStatusTone =
  | "neutral"
  | "primary"
  | "success"
  | "warning"
  | "danger"
  | "info"
  | "special";

export type EventStatus =
  | "draft"
  | "tentative"
  | "confirmed"
  | "in_production"
  | "completed"
  | "cancelled";

export type MenuStatus = "draft" | "active" | "published" | "archived";
export type RecipeStatus = "draft" | "active" | "archived";
export type PrepListStatus =
  | "draft"
  | "active"
  | "in_progress"
  | "completed"
  | "cancelled";
export type PrepListVersionStatus =
  | "draft"
  | "review"
  | "approved"
  | "superseded"
  | "cancelled";

export type PrepTaskStatus =
  | "todo"
  | "in_progress"
  | "blocked"
  | "done"
  | "skipped";

export type TaskStatus =
  | "todo"
  | "in_progress"
  | "blocked"
  | "done"
  | "cancelled";

export type PurchasingStatus =
  | "pending"
  | "approved"
  | "ordered"
  | "received";

export type WorkspaceMemberStatus =
  | "active"
  | "inactive"
  | "invited"
  | "error";

export type AppOperationalStatus =
  | EventStatus
  | MenuStatus
  | RecipeStatus
  | PrepListStatus
  | PrepListVersionStatus
  | PrepTaskStatus
  | TaskStatus
  | PurchasingStatus
  | WorkspaceMemberStatus;

export type AppStateTone =
  | "loading"
  | "empty"
  | "error"
  | "forbidden"
  | "offline"
  | "success"
  | "conflict"
  | "info";

export type AlertTone = "info" | "success" | "warning" | "error";

type StatusDefinition = {
  tone: SemanticStatusTone;
  translationKey: string;
};

export const STATUS_CONFIG = {
  events: {
    draft: { tone: "neutral", translationKey: "events.status.draft" },
    tentative: { tone: "warning", translationKey: "events.status.tentative" },
    confirmed: { tone: "success", translationKey: "events.status.confirmed" },
    in_production: {
      tone: "primary",
      translationKey: "events.status.in_production",
    },
    completed: { tone: "success", translationKey: "events.status.completed" },
    cancelled: { tone: "danger", translationKey: "events.status.cancelled" },
  } satisfies Record<EventStatus, StatusDefinition>,
  menus: {
    draft: { tone: "neutral", translationKey: "menus.status.draft" },
    active: { tone: "success", translationKey: "menus.status.active" },
    published: { tone: "success", translationKey: "menus.status.published" },
    archived: { tone: "neutral", translationKey: "menus.status.archived" },
  } satisfies Record<MenuStatus, StatusDefinition>,
  recipes: {
    draft: { tone: "neutral", translationKey: "recipes.status.draft" },
    active: { tone: "success", translationKey: "recipes.status.active" },
    archived: { tone: "neutral", translationKey: "recipes.status.archived" },
  } satisfies Record<RecipeStatus, StatusDefinition>,
  prepLists: {
    draft: { tone: "neutral", translationKey: "prep.status.draft" },
    active: { tone: "primary", translationKey: "prep.status.active" },
    in_progress: {
      tone: "info",
      translationKey: "prep.status.in_progress",
    },
    completed: { tone: "success", translationKey: "prep.status.completed" },
    cancelled: { tone: "danger", translationKey: "prep.status.cancelled" },
  } satisfies Record<PrepListStatus, StatusDefinition>,
  prepListVersions: {
    draft: { tone: "neutral", translationKey: "prep.versionStatus.draft" },
    review: { tone: "warning", translationKey: "prep.versionStatus.review" },
    approved: { tone: "success", translationKey: "prep.versionStatus.approved" },
    superseded: {
      tone: "neutral",
      translationKey: "prep.versionStatus.superseded",
    },
    cancelled: {
      tone: "danger",
      translationKey: "prep.versionStatus.cancelled",
    },
  } satisfies Record<PrepListVersionStatus, StatusDefinition>,
  prepTasks: {
    todo: { tone: "neutral", translationKey: "status.todo" },
    in_progress: { tone: "info", translationKey: "status.in_progress" },
    blocked: { tone: "danger", translationKey: "status.blocked" },
    done: { tone: "success", translationKey: "status.done" },
    skipped: { tone: "neutral", translationKey: "status.skipped" },
  } satisfies Record<PrepTaskStatus, StatusDefinition>,
  tasks: {
    todo: { tone: "neutral", translationKey: "tasks.status.todo" },
    in_progress: {
      tone: "info",
      translationKey: "tasks.status.in_progress",
    },
    blocked: { tone: "danger", translationKey: "tasks.status.blocked" },
    done: { tone: "success", translationKey: "tasks.status.done" },
    cancelled: {
      tone: "danger",
      translationKey: "tasks.status.cancelled",
    },
  } satisfies Record<TaskStatus, StatusDefinition>,
  purchasing: {
    pending: { tone: "warning", translationKey: "status.pending" },
    approved: { tone: "success", translationKey: "status.approved" },
    ordered: { tone: "info", translationKey: "status.ordered" },
    received: { tone: "success", translationKey: "status.received" },
  } satisfies Record<PurchasingStatus, StatusDefinition>,
  workspaceMembers: {
    active: { tone: "success", translationKey: "status.active" },
    inactive: { tone: "neutral", translationKey: "status.inactive" },
    invited: { tone: "special", translationKey: "status.invited" },
    error: { tone: "danger", translationKey: "status.error" },
  } satisfies Record<WorkspaceMemberStatus, StatusDefinition>,
} as const;

export type StatusConfigNamespace = keyof typeof STATUS_CONFIG;

const APP_STATE_TONE_MAP: Record<AppStateTone, SemanticStatusTone> = {
  loading: "primary",
  empty: "neutral",
  error: "danger",
  forbidden: "special",
  offline: "info",
  success: "success",
  conflict: "warning",
  info: "info",
};

const ALERT_TONE_MAP: Record<AlertTone, SemanticStatusTone> = {
  info: "info",
  success: "success",
  warning: "warning",
  error: "danger",
};

export function getSemanticToneAppearance(
  theme: AppTheme,
  tone: SemanticStatusTone
) {
  if (tone === "primary") {
    return {
      accent: theme.colors.brand.primary,
      background: theme.colors.brand.soft,
      border: theme.colors.brand.primary,
      text: theme.colors.text.primary,
    };
  }

  if (tone === "neutral") {
    return {
      accent: theme.colors.text.secondary,
      background: theme.colors.background.muted,
      border: theme.colors.border.default,
      text: theme.colors.text.primary,
    };
  }

  if (tone === "success") {
    return {
      accent: theme.colors.status.success,
      background: theme.colors.status.successSoft,
      border: theme.colors.status.success,
      text: theme.colors.text.primary,
    };
  }

  if (tone === "warning") {
    return {
      accent: theme.colors.status.warning,
      background: theme.colors.status.warningSoft,
      border: theme.colors.status.warning,
      text: theme.colors.text.primary,
    };
  }

  if (tone === "danger") {
    return {
      accent: theme.colors.status.danger,
      background: theme.colors.status.dangerSoft,
      border: theme.colors.status.danger,
      text: theme.colors.text.primary,
    };
  }

  if (tone === "special") {
    return {
      accent: theme.colors.status.special,
      background: theme.colors.status.specialSoft,
      border: theme.colors.status.special,
      text: theme.colors.text.primary,
    };
  }

  return {
    accent: theme.colors.status.info,
    background: theme.colors.status.infoSoft,
    border: theme.colors.status.info,
    text: theme.colors.text.primary,
  };
}

export function getStatusTone(
  status: AppOperationalStatus,
  namespace?: StatusConfigNamespace
): SemanticStatusTone {
  if (namespace) {
    const definition = (
      STATUS_CONFIG[namespace] as Record<string, StatusDefinition>
    )[status];

    return definition?.tone ?? "neutral";
  }

  for (const scopedStatuses of Object.values(STATUS_CONFIG)) {
    const definition = (
      scopedStatuses as Record<string, StatusDefinition>
    )[status];

    if (definition) {
      return definition.tone;
    }
  }

  return "neutral";
}

export function getStatusTranslationKey(
  status: AppOperationalStatus,
  namespace?: StatusConfigNamespace
) {
  if (namespace) {
    const definition = (
      STATUS_CONFIG[namespace] as Record<string, StatusDefinition>
    )[status];

    return definition?.translationKey ?? `status.${status}`;
  }

  for (const scopedStatuses of Object.values(STATUS_CONFIG)) {
    const definition = (
      scopedStatuses as Record<string, StatusDefinition>
    )[status];

    if (definition) {
      return definition.translationKey;
    }
  }

  return `status.${status}`;
}

export function getStatusMetadata(
  status: AppOperationalStatus,
  namespace?: StatusConfigNamespace
) {
  return {
    tone: getStatusTone(status, namespace),
    translationKey: getStatusTranslationKey(status, namespace),
  };
}

export function getAppStateAppearance(theme: AppTheme, tone: AppStateTone) {
  return getSemanticToneAppearance(theme, APP_STATE_TONE_MAP[tone]);
}

export function getAlertAppearance(theme: AppTheme, tone: AlertTone) {
  return getSemanticToneAppearance(theme, ALERT_TONE_MAP[tone]);
}

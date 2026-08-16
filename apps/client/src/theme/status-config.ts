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

export type PrepTaskStatus =
  | "todo"
  | "in_progress"
  | "blocked"
  | "done"
  | "skipped";

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

export const STATUS_CONFIG = {
  events: {
    draft: "neutral",
    tentative: "warning",
    confirmed: "success",
    in_production: "primary",
    completed: "success",
    cancelled: "danger",
  } satisfies Record<EventStatus, SemanticStatusTone>,
  prepTasks: {
    todo: "neutral",
    in_progress: "info",
    blocked: "danger",
    done: "success",
    skipped: "neutral",
  } satisfies Record<PrepTaskStatus, SemanticStatusTone>,
  purchasing: {
    pending: "warning",
    approved: "success",
    ordered: "info",
    received: "success",
  } satisfies Record<PurchasingStatus, SemanticStatusTone>,
  workspaceMembers: {
    active: "success",
    inactive: "neutral",
    invited: "special",
    error: "danger",
  } satisfies Record<WorkspaceMemberStatus, SemanticStatusTone>,
} as const;

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

export function getAppStateAppearance(theme: AppTheme, tone: AppStateTone) {
  return getSemanticToneAppearance(theme, APP_STATE_TONE_MAP[tone]);
}

export function getAlertAppearance(theme: AppTheme, tone: AlertTone) {
  return getSemanticToneAppearance(theme, ALERT_TONE_MAP[tone]);
}

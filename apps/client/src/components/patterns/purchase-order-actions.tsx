import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/primitives/button";
import {
  isSupportedPurchaseOrderAction,
  resolvePurchaseOrderActionLabel,
  type PurchaseOrderAction,
  type PurchaseOrderActionId,
  type PurchaseOrderRecord,
} from "@/features/purchasing";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PurchaseOrderActionsProps = {
  accessibilityLabel?: string;
  availableActions?: PurchaseOrderAction[];
  compact?: boolean;
  disabled?: boolean;
  loadingAction?: string | null;
  onAction?: (action: PurchaseOrderAction) => void | Promise<void>;
  purchaseOrder?: PurchaseOrderRecord | null;
};

function getVariant(actionId: PurchaseOrderActionId) {
  if (actionId === "cancel") return "destructive" as const;
  if (actionId === "approve" || actionId === "receive") return "primary" as const;
  if (actionId === "place_order") return "secondary" as const;
  if (actionId === "reopen") return "secondary" as const;
  return "ghost" as const;
}

export function PurchaseOrderActions({
  accessibilityLabel,
  availableActions = [],
  compact = false,
  disabled = false,
  loadingAction,
  onAction,
}: PurchaseOrderActionsProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const supportedActions = availableActions.filter(isSupportedPurchaseOrderAction);

  if (!supportedActions.length) {
    return null;
  }

  return (
    <View
      accessibilityLabel={
        accessibilityLabel ?? t("purchasing.purchaseOrderActions.accessibilityLabel")
      }
      style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}
    >
      {supportedActions.map((action) => {
        const label = resolvePurchaseOrderActionLabel(action, t);
        if (!label) return null;
        const isLoading = loadingAction === action.id;

        return (
          <Button
            accessibilityHint={t("purchasing.purchaseOrderActions.accessibilityHint", {
              action: label,
            })}
            accessibilityLabel={label}
            disabled={disabled || Boolean(loadingAction) || action.disabled}
            key={action.id}
            label={label}
            loading={isLoading}
            onPress={() => onAction?.(action)}
            size={compact ? "sm" : "md"}
            variant={getVariant(action.id)}
          />
        );
      })}
    </View>
  );
}

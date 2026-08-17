import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import {
  formatPurchasingCurrency,
  formatSupplierLeadTime,
  formatSupplierPaymentTerms,
  getSupplierName,
  getSupplierStatusValue,
  type SupplierRecord,
} from "@/features/purchasing";

export type SupplierCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showContact?: boolean;
  showMetrics?: boolean;
  showTerms?: boolean;
  supplier: SupplierRecord;
};

export function SupplierCard({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  onPress,
  selected = false,
  showContact = true,
  showMetrics = !compact,
  showTerms = true,
  supplier,
}: SupplierCardProps) {
  const { t, i18n } = useTranslation("common");
  const title = getSupplierName(supplier) ?? t("purchasing.labels.supplier");
  const status = getSupplierStatusValue(supplier.status);
  const subtitle =
    supplier.companyName?.trim() &&
    supplier.companyName.trim() !== title
      ? supplier.companyName.trim()
      : supplier.contactName?.trim() || undefined;
  const metadata: EntityCardMetadataItem[] = [];
  const minimumOrder = formatPurchasingCurrency(
    supplier.minimumOrderAmount,
    supplier.currency,
    i18n.language
  );
  const leadTime = formatSupplierLeadTime(supplier.leadTimeDays, t);
  const paymentTerms = formatSupplierPaymentTerms(supplier.paymentTerms, t);

  if (showContact && supplier.contactName?.trim()) {
    metadata.push({
      label: t("purchasing.labels.contact"),
      value: supplier.contactName.trim(),
    });
  }

  if (showContact && supplier.contactEmail?.trim()) {
    metadata.push({
      label: t("purchasing.labels.contactEmail"),
      value: supplier.contactEmail.trim(),
    });
  }

  if (!compact && showContact && supplier.contactPhone?.trim()) {
    metadata.push({
      label: t("purchasing.labels.contactPhone"),
      value: supplier.contactPhone.trim(),
    });
  }

  if (showTerms && leadTime) {
    metadata.push({
      label: t("purchasing.labels.leadTime"),
      value: leadTime,
    });
  }

  if (showTerms && minimumOrder) {
    metadata.push({
      label: t("purchasing.labels.minimumOrder"),
      value: minimumOrder,
    });
  }

  if (!compact && showTerms && paymentTerms) {
    metadata.push({
      label: t("purchasing.labels.paymentTerms"),
      value: paymentTerms,
    });
  }

  if (showMetrics && typeof supplier.supplierItemCount === "number") {
    metadata.push({
      label: t("purchasing.labels.items"),
      value: t("purchasing.metrics.items", { count: supplier.supplierItemCount }),
    });
  }

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ??
        t("purchasing.suppliers.cardAccessibilityLabel", {
          name: title,
        })
      }
      disabled={disabled}
      eyebrow={supplier.preferred ? t("purchasing.suppliers.preferred") : undefined}
      leading={<Avatar name={title} size={compact ? "sm" : "md"} />}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      status={status ?? undefined}
      statusNamespace={status ? "suppliers" : undefined}
      subtitle={subtitle}
      title={title}
      trailing={actions}
      variant={compact ? "muted" : "default"}
    />
  );
}

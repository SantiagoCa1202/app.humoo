import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Checkbox } from "@/components/primitives/checkbox";
import { CurrencyInput, type CurrencyOption } from "@/components/primitives/currency-input";
import { FormCard } from "@/components/patterns/form-card";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createSupplierEditorValues,
  hasSupplierEditorErrors,
  normalizeSupplierEditorValues,
  SUPPLIER_PAYMENT_TERM_VALUES,
  SUPPLIER_STATUS_VALUES,
  validateSupplierEditorValues,
  type SupplierEditorMode,
  type SupplierEditorValidationErrors,
  type SupplierEditorValues,
} from "@/features/purchasing";
import { Select } from "@/components/primitives/select";
import { NumberField } from "@/components/primitives/number-field";
import type { AppOperationalStatus } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: SupplierEditorValidationErrors,
  externalErrors?: SupplierEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies SupplierEditorValidationErrors;
}

export type SupplierEditorFormProps = {
  accessibilityLabel?: string;
  currencyOptions?: CurrencyOption<string>[];
  disabled?: boolean;
  initialValues?: Partial<SupplierEditorValues>;
  mode?: SupplierEditorMode;
  onCancel?: () => void;
  onSubmit: (value: SupplierEditorValues) => void | Promise<void>;
  submitting?: boolean;
  validationErrors?: SupplierEditorValidationErrors;
};

export function SupplierEditorForm({
  accessibilityLabel,
  currencyOptions,
  disabled = false,
  initialValues,
  mode = "create",
  onCancel,
  onSubmit,
  submitting = false,
  validationErrors,
}: SupplierEditorFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createSupplierEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<SupplierEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<SupplierEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedCurrencyOptions =
    currencyOptions?.length
      ? currencyOptions
      : values.currency
      ? [{ label: values.currency, value: values.currency }]
      : [];

  const handleSubmit = async () => {
    const normalized = normalizeSupplierEditorValues(values);
    const nextErrors = validateSupplierEditorValues(normalized, t);

    if (hasSupplierEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("purchasing.forms.supplier.accessibilityLabel")}
      cancelLabel={t("purchasing.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={
        mode === "edit"
          ? t("purchasing.actions.saveChanges")
          : t("purchasing.actions.createSupplier")
      }
      submitting={submitting}
      title={
        mode === "edit"
          ? t("purchasing.forms.supplier.editTitle")
          : t("purchasing.forms.supplier.createTitle")
      }
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          accessibilityLabel={t("purchasing.forms.supplier.fields.name.accessibilityLabel")}
          editable={!disabled}
          error={resolvedErrors.name}
          label={t("purchasing.forms.supplier.fields.name.label")}
          onChangeText={(name) => setValues((current) => ({ ...current, name }))}
          placeholder={t("purchasing.forms.supplier.fields.name.placeholder")}
          required
          value={values.name ?? ""}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t("purchasing.forms.supplier.fields.code.accessibilityLabel")}
              editable={!disabled}
              error={resolvedErrors.code}
              label={t("purchasing.forms.supplier.fields.code.label")}
              onChangeText={(code) => setValues((current) => ({ ...current, code }))}
              placeholder={t("purchasing.forms.supplier.fields.code.placeholder")}
              value={values.code ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.companyName.accessibilityLabel"
              )}
              editable={!disabled}
              label={t("purchasing.forms.supplier.fields.companyName.label")}
              onChangeText={(companyName) =>
                setValues((current) => ({ ...current, companyName }))
              }
              placeholder={t("purchasing.forms.supplier.fields.companyName.placeholder")}
              value={values.companyName ?? ""}
            />
          </View>
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t("purchasing.forms.supplier.fields.email.accessibilityLabel")}
              autoCapitalize="none"
              autoComplete="email"
              editable={!disabled}
              error={resolvedErrors.email}
              keyboardType="email-address"
              label={t("purchasing.forms.supplier.fields.email.label")}
              onChangeText={(email) => setValues((current) => ({ ...current, email }))}
              placeholder={t("purchasing.forms.supplier.fields.email.placeholder")}
              value={values.email ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t("purchasing.forms.supplier.fields.phone.accessibilityLabel")}
              autoComplete="tel"
              editable={!disabled}
              error={resolvedErrors.phone}
              keyboardType="phone-pad"
              label={t("purchasing.forms.supplier.fields.phone.label")}
              onChangeText={(phone) => setValues((current) => ({ ...current, phone }))}
              placeholder={t("purchasing.forms.supplier.fields.phone.placeholder")}
              value={values.phone ?? ""}
            />
          </View>
        </View>
        <TextField
          accessibilityLabel={t("purchasing.forms.supplier.fields.website.accessibilityLabel")}
          autoCapitalize="none"
          autoComplete="url"
          editable={!disabled}
          label={t("purchasing.forms.supplier.fields.website.label")}
          onChangeText={(website) => setValues((current) => ({ ...current, website }))}
          placeholder={t("purchasing.forms.supplier.fields.website.placeholder")}
          value={values.website ?? ""}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.contactName.accessibilityLabel"
              )}
              editable={!disabled}
              error={resolvedErrors.contactName}
              label={t("purchasing.forms.supplier.fields.contactName.label")}
              onChangeText={(contactName) =>
                setValues((current) => ({ ...current, contactName }))
              }
              placeholder={t("purchasing.forms.supplier.fields.contactName.placeholder")}
              value={values.contactName ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.contactEmail.accessibilityLabel"
              )}
              autoCapitalize="none"
              autoComplete="email"
              editable={!disabled}
              error={resolvedErrors.contactEmail}
              keyboardType="email-address"
              label={t("purchasing.forms.supplier.fields.contactEmail.label")}
              onChangeText={(contactEmail) =>
                setValues((current) => ({ ...current, contactEmail }))
              }
              placeholder={t("purchasing.forms.supplier.fields.contactEmail.placeholder")}
              value={values.contactEmail ?? ""}
            />
          </View>
        </View>
        <TextField
          accessibilityLabel={t(
            "purchasing.forms.supplier.fields.contactPhone.accessibilityLabel"
          )}
          autoComplete="tel"
          editable={!disabled}
          error={resolvedErrors.contactPhone}
          keyboardType="phone-pad"
          label={t("purchasing.forms.supplier.fields.contactPhone.label")}
          onChangeText={(contactPhone) =>
            setValues((current) => ({ ...current, contactPhone }))
          }
          placeholder={t("purchasing.forms.supplier.fields.contactPhone.placeholder")}
          value={values.contactPhone ?? ""}
        />
        <TextField
          accessibilityLabel={t("purchasing.forms.supplier.fields.addressLine1.accessibilityLabel")}
          editable={!disabled}
          label={t("purchasing.forms.supplier.fields.addressLine1.label")}
          onChangeText={(addressLine1) =>
            setValues((current) => ({ ...current, addressLine1 }))
          }
          placeholder={t("purchasing.forms.supplier.fields.addressLine1.placeholder")}
          value={values.addressLine1 ?? ""}
        />
        <TextField
          accessibilityLabel={t("purchasing.forms.supplier.fields.addressLine2.accessibilityLabel")}
          editable={!disabled}
          label={t("purchasing.forms.supplier.fields.addressLine2.label")}
          onChangeText={(addressLine2) =>
            setValues((current) => ({ ...current, addressLine2 }))
          }
          placeholder={t("purchasing.forms.supplier.fields.addressLine2.placeholder")}
          value={values.addressLine2 ?? ""}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 160 }}>
            <TextField
              accessibilityLabel={t("purchasing.forms.supplier.fields.city.accessibilityLabel")}
              editable={!disabled}
              label={t("purchasing.forms.supplier.fields.city.label")}
              onChangeText={(city) => setValues((current) => ({ ...current, city }))}
              placeholder={t("purchasing.forms.supplier.fields.city.placeholder")}
              value={values.city ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 160 }}>
            <TextField
              accessibilityLabel={t("purchasing.forms.supplier.fields.state.accessibilityLabel")}
              editable={!disabled}
              label={t("purchasing.forms.supplier.fields.state.label")}
              onChangeText={(state) => setValues((current) => ({ ...current, state }))}
              placeholder={t("purchasing.forms.supplier.fields.state.placeholder")}
              value={values.state ?? ""}
            />
          </View>
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 160 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.postalCode.accessibilityLabel"
              )}
              editable={!disabled}
              label={t("purchasing.forms.supplier.fields.postalCode.label")}
              onChangeText={(postalCode) =>
                setValues((current) => ({ ...current, postalCode }))
              }
              placeholder={t("purchasing.forms.supplier.fields.postalCode.placeholder")}
              value={values.postalCode ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 160 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.countryCode.accessibilityLabel"
              )}
              autoCapitalize="characters"
              editable={!disabled}
              label={t("purchasing.forms.supplier.fields.countryCode.label")}
              maxLength={2}
              onChangeText={(countryCode) =>
                setValues((current) => ({ ...current, countryCode }))
              }
              placeholder={t("purchasing.forms.supplier.fields.countryCode.placeholder")}
              value={values.countryCode ?? ""}
            />
          </View>
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <NumberField
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.leadTimeDays.accessibilityLabel"
              )}
              disabled={disabled}
              error={resolvedErrors.leadTimeDays}
              label={t("purchasing.forms.supplier.fields.leadTimeDays.label")}
              min={0}
              onChange={(leadTimeDays) =>
                setValues((current) => ({ ...current, leadTimeDays }))
              }
              step={1}
              value={values.leadTimeDays ?? 0}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <CurrencyInput
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.minimumOrderAmount.accessibilityLabel"
              )}
              currencies={resolvedCurrencyOptions}
              currency={values.currency ?? undefined}
              disabled={disabled}
              error={resolvedErrors.minimumOrderAmount}
              label={t("purchasing.forms.supplier.fields.minimumOrderAmount.label")}
              onChange={(minimumOrderAmount) =>
                setValues((current) => ({ ...current, minimumOrderAmount }))
              }
              onCurrencyChange={(currency) =>
                setValues((current) => ({ ...current, currency }))
              }
              value={values.minimumOrderAmount ?? 0}
            />
          </View>
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <Select
              accessibilityLabel={t(
                "purchasing.forms.supplier.fields.paymentTerms.accessibilityLabel"
              )}
              disabled={disabled}
              error={resolvedErrors.paymentTerms}
              label={t("purchasing.forms.supplier.fields.paymentTerms.label")}
              onChange={(paymentTerms) =>
                setValues((current) => ({ ...current, paymentTerms }))
              }
              options={SUPPLIER_PAYMENT_TERM_VALUES.map((paymentTerm) => ({
                label: t(`purchasing.paymentTerms.${paymentTerm}`),
                value: paymentTerm,
              }))}
              placeholder={t("purchasing.forms.supplier.fields.paymentTerms.placeholder")}
              value={values.paymentTerms ?? undefined}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <StatusSelect
              accessibilityLabel={t("purchasing.forms.supplier.fields.status.accessibilityLabel")}
              disabled={disabled}
              error={resolvedErrors.status}
              label={t("purchasing.forms.supplier.fields.status.label")}
              namespace="suppliers"
              onChange={(status) =>
                setValues((current) => ({
                  ...current,
                  status: status as SupplierEditorValues["status"],
                }))
              }
              options={SUPPLIER_STATUS_VALUES.map((status) => ({
                namespace: "suppliers",
                value: status as AppOperationalStatus,
              }))}
              value={(values.status ?? undefined) as AppOperationalStatus | undefined}
            />
          </View>
        </View>
        <Checkbox
          accessibilityLabel={t("purchasing.forms.supplier.fields.preferred.accessibilityLabel")}
          checked={Boolean(values.preferred)}
          description={t("purchasing.forms.supplier.fields.preferred.helper")}
          disabled={disabled}
          label={t("purchasing.forms.supplier.fields.preferred.label")}
          onChange={(preferred) => setValues((current) => ({ ...current, preferred }))}
        />
        <TextArea
          accessibilityLabel={t("purchasing.forms.supplier.fields.notes.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          label={t("purchasing.forms.supplier.fields.notes.label")}
          onChangeText={(notes) => setValues((current) => ({ ...current, notes }))}
          placeholder={t("purchasing.forms.supplier.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
      </View>
    </FormCard>
  );
}

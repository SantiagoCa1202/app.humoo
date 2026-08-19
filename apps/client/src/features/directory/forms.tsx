import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { Checkbox } from "@/components/primitives/checkbox";
import { EntityPicker, EntityPickerOption } from "@/components/primitives/entity-picker";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import { TimezonePicker } from "@/components/primitives/timezone-picker";
import { spacing } from "@/theme";
import type {
  ClientMutationInput,
  ContactMutationInput,
  DirectoryStatus,
  VenueMutationInput,
} from "@/features/directory/types";

const STATUS_OPTIONS = [
  { value: "active" as const },
  { value: "inactive" as const },
];

type SharedFormProps = {
  disabled?: boolean;
  errorMessage?: string | null;
  loading?: boolean;
  onCancel?: () => void;
};

export type ClientFormValues = {
  name: string;
  companyName: string;
  email: string;
  phone: string;
  website: string;
  taxId: string;
  addressLine1: string;
  addressLine2: string;
  city: string;
  state: string;
  postalCode: string;
  countryCode: string;
  status: DirectoryStatus;
  notes: string;
};

export type ClientFormErrors = Partial<Record<keyof ClientFormValues, string>>;

type ClientFormProps = SharedFormProps & {
  initialValues?: Partial<ClientFormValues>;
  onSubmit: (values: ClientMutationInput) => void | Promise<void>;
  submitLabel: string;
  subtitle: string;
  title: string;
  validationErrors?: ClientFormErrors;
};

export type ContactFormValues = {
  clientId: string;
  firstName: string;
  lastName: string;
  displayName: string;
  email: string;
  phone: string;
  jobTitle: string;
  contactType: string;
  isPrimary: boolean;
  notes: string;
};

export type ContactFormErrors = Partial<Record<keyof ContactFormValues, string>>;

type ContactFormProps = SharedFormProps & {
  clientOptions: EntityPickerOption<string>[];
  initialValues?: Partial<ContactFormValues>;
  onSubmit: (values: ContactMutationInput) => void | Promise<void>;
  submitLabel: string;
  subtitle: string;
  title: string;
  validationErrors?: ContactFormErrors;
};

export type VenueFormValues = {
  name: string;
  addressLine1: string;
  addressLine2: string;
  city: string;
  state: string;
  postalCode: string;
  countryCode: string;
  latitude: string;
  longitude: string;
  timezone: string;
  contactName: string;
  contactEmail: string;
  contactPhone: string;
  capacity: string;
  accessInstructions: string;
  parkingNotes: string;
  loadingNotes: string;
  kitchenNotes: string;
  notes: string;
  status: DirectoryStatus;
};

export type VenueFormErrors = Partial<Record<keyof VenueFormValues, string>>;

type VenueFormProps = SharedFormProps & {
  initialValues?: Partial<VenueFormValues>;
  onSubmit: (values: VenueMutationInput) => void | Promise<void>;
  submitLabel: string;
  subtitle: string;
  title: string;
  validationErrors?: VenueFormErrors;
};

const DEFAULT_CLIENT_VALUES: ClientFormValues = {
  name: "",
  companyName: "",
  email: "",
  phone: "",
  website: "",
  taxId: "",
  addressLine1: "",
  addressLine2: "",
  city: "",
  state: "",
  postalCode: "",
  countryCode: "",
  status: "active",
  notes: "",
};

const DEFAULT_CONTACT_VALUES: ContactFormValues = {
  clientId: "",
  firstName: "",
  lastName: "",
  displayName: "",
  email: "",
  phone: "",
  jobTitle: "",
  contactType: "",
  isPrimary: false,
  notes: "",
};

const DEFAULT_VENUE_VALUES: VenueFormValues = {
  name: "",
  addressLine1: "",
  addressLine2: "",
  city: "",
  state: "",
  postalCode: "",
  countryCode: "",
  latitude: "",
  longitude: "",
  timezone: "",
  contactName: "",
  contactEmail: "",
  contactPhone: "",
  capacity: "",
  accessInstructions: "",
  parkingNotes: "",
  loadingNotes: "",
  kitchenNotes: "",
  notes: "",
  status: "active",
};

export function ClientForm({
  disabled = false,
  errorMessage,
  initialValues,
  loading = false,
  onCancel,
  onSubmit,
  submitLabel,
  subtitle,
  title,
  validationErrors,
}: ClientFormProps) {
  const { t } = useTranslation("app");
  const [values, setValues] = useState<ClientFormValues>({
    ...DEFAULT_CLIENT_VALUES,
    ...initialValues,
  });
  const [localErrors, setLocalErrors] = useState<ClientFormErrors>({});

  useEffect(() => {
    setValues({
      ...DEFAULT_CLIENT_VALUES,
      ...initialValues,
    });
  }, [initialValues]);

  useEffect(() => {
    setLocalErrors(validationErrors ?? {});
  }, [validationErrors]);

  const handleSubmit = async () => {
    const nextErrors = validateClient(values, t);
    setLocalErrors(nextErrors);

    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    await onSubmit({
      addressLine1: toNullable(values.addressLine1),
      addressLine2: toNullable(values.addressLine2),
      city: toNullable(values.city),
      companyName: toNullable(values.companyName),
      countryCode: toNullable(values.countryCode),
      email: toNullable(values.email),
      name: values.name.trim(),
      notes: toNullable(values.notes),
      phone: toNullable(values.phone),
      postalCode: toNullable(values.postalCode),
      state: toNullable(values.state),
      status: values.status,
      taxId: toNullable(values.taxId),
      website: toNullable(values.website),
    });
  };

  return (
    <FormCard
      disabled={disabled}
      error={errorMessage}
      onCancel={onCancel}
      onSubmit={() => {
        void handleSubmit();
      }}
      submitLabel={submitLabel}
      submitting={loading}
      subtitle={subtitle}
      title={title}
    >
      <View style={{ gap: spacing[3] }}>
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.name.accessibilityLabel")}
          error={localErrors.name}
          label={t("directory.clients.form.fields.name.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, name: value }))}
          placeholder={t("directory.clients.form.fields.name.placeholder")}
          value={values.name}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.companyName.accessibilityLabel")}
          error={localErrors.companyName}
          label={t("directory.clients.form.fields.companyName.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, companyName: value }))
          }
          placeholder={t("directory.clients.form.fields.companyName.placeholder")}
          value={values.companyName}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.email.accessibilityLabel")}
          autoCapitalize="none"
          error={localErrors.email}
          keyboardType="email-address"
          label={t("directory.clients.form.fields.email.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, email: value }))}
          placeholder={t("directory.clients.form.fields.email.placeholder")}
          value={values.email}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.phone.accessibilityLabel")}
          error={localErrors.phone}
          keyboardType="phone-pad"
          label={t("directory.clients.form.fields.phone.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, phone: value }))}
          placeholder={t("directory.clients.form.fields.phone.placeholder")}
          value={values.phone}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.website.accessibilityLabel")}
          autoCapitalize="none"
          error={localErrors.website}
          label={t("directory.clients.form.fields.website.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, website: value }))}
          placeholder={t("directory.clients.form.fields.website.placeholder")}
          value={values.website}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.taxId.accessibilityLabel")}
          error={localErrors.taxId}
          label={t("directory.clients.form.fields.taxId.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, taxId: value }))}
          placeholder={t("directory.clients.form.fields.taxId.placeholder")}
          value={values.taxId}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.addressLine1.accessibilityLabel")}
          error={localErrors.addressLine1}
          label={t("directory.clients.form.fields.addressLine1.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, addressLine1: value }))
          }
          placeholder={t("directory.clients.form.fields.addressLine1.placeholder")}
          value={values.addressLine1}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.addressLine2.accessibilityLabel")}
          error={localErrors.addressLine2}
          label={t("directory.clients.form.fields.addressLine2.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, addressLine2: value }))
          }
          placeholder={t("directory.clients.form.fields.addressLine2.placeholder")}
          value={values.addressLine2}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.city.accessibilityLabel")}
          error={localErrors.city}
          label={t("directory.clients.form.fields.city.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, city: value }))}
          placeholder={t("directory.clients.form.fields.city.placeholder")}
          value={values.city}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.state.accessibilityLabel")}
          error={localErrors.state}
          label={t("directory.clients.form.fields.state.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, state: value }))}
          placeholder={t("directory.clients.form.fields.state.placeholder")}
          value={values.state}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.postalCode.accessibilityLabel")}
          error={localErrors.postalCode}
          label={t("directory.clients.form.fields.postalCode.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, postalCode: value }))
          }
          placeholder={t("directory.clients.form.fields.postalCode.placeholder")}
          value={values.postalCode}
        />
        <TextField
          accessibilityLabel={t("directory.clients.form.fields.countryCode.accessibilityLabel")}
          autoCapitalize="characters"
          error={localErrors.countryCode}
          label={t("directory.clients.form.fields.countryCode.label")}
          maxLength={2}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, countryCode: value }))
          }
          placeholder={t("directory.clients.form.fields.countryCode.placeholder")}
          value={values.countryCode}
        />
        <StatusSelect
          accessibilityLabel={t("directory.clients.form.fields.status.accessibilityLabel")}
          error={localErrors.status}
          label={t("directory.clients.form.fields.status.label")}
          namespace="workspaceMembers"
          onChange={(value) =>
            setValues((current) => ({
              ...current,
              status: value as DirectoryStatus,
            }))
          }
          options={STATUS_OPTIONS}
          value={values.status}
        />
        <TextArea
          accessibilityLabel={t("directory.clients.form.fields.notes.accessibilityLabel")}
          autoGrow
          error={localErrors.notes}
          label={t("directory.clients.form.fields.notes.label")}
          minHeight={120}
          onChangeText={(value) => setValues((current) => ({ ...current, notes: value }))}
          placeholder={t("directory.clients.form.fields.notes.placeholder")}
          value={values.notes}
        />
      </View>
    </FormCard>
  );
}

export function ContactForm({
  clientOptions,
  disabled = false,
  errorMessage,
  initialValues,
  loading = false,
  onCancel,
  onSubmit,
  submitLabel,
  subtitle,
  title,
  validationErrors,
}: ContactFormProps) {
  const { t } = useTranslation("app");
  const [values, setValues] = useState<ContactFormValues>({
    ...DEFAULT_CONTACT_VALUES,
    ...initialValues,
  });
  const [localErrors, setLocalErrors] = useState<ContactFormErrors>({});

  useEffect(() => {
    setValues({
      ...DEFAULT_CONTACT_VALUES,
      ...initialValues,
    });
  }, [initialValues]);

  useEffect(() => {
    setLocalErrors(validationErrors ?? {});
  }, [validationErrors]);

  const hasClientOptions = clientOptions.length > 0;

  const handleSubmit = async () => {
    const nextErrors = validateContact(values, t);
    setLocalErrors(nextErrors);

    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    await onSubmit({
      clientId: toNullable(values.clientId),
      contactType: toNullable(values.contactType),
      displayName: toNullable(values.displayName),
      email: toNullable(values.email),
      firstName: values.firstName.trim(),
      isPrimary: values.isPrimary,
      jobTitle: toNullable(values.jobTitle),
      lastName: toNullable(values.lastName),
      notes: toNullable(values.notes),
      phone: toNullable(values.phone),
    });
  };

  return (
    <FormCard
      disabled={disabled}
      error={errorMessage}
      onCancel={onCancel}
      onSubmit={() => {
        void handleSubmit();
      }}
      submitLabel={submitLabel}
      submitting={loading}
      subtitle={subtitle}
      title={title}
    >
      <View style={{ gap: spacing[3] }}>
        <EntityPicker
          accessibilityLabel={t("directory.contacts.form.fields.client.accessibilityLabel")}
          disabled={!hasClientOptions || disabled}
          entities={clientOptions}
          error={localErrors.clientId}
          helperText={
            !hasClientOptions
              ? t("directory.contacts.form.fields.client.empty")
              : undefined
          }
          label={t("directory.contacts.form.fields.client.label")}
          onChange={(value) => setValues((current) => ({ ...current, clientId: value }))}
          placeholder={t("directory.contacts.form.fields.client.placeholder")}
          value={values.clientId || undefined}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.firstName.accessibilityLabel")}
          error={localErrors.firstName}
          label={t("directory.contacts.form.fields.firstName.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, firstName: value }))
          }
          placeholder={t("directory.contacts.form.fields.firstName.placeholder")}
          value={values.firstName}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.lastName.accessibilityLabel")}
          error={localErrors.lastName}
          label={t("directory.contacts.form.fields.lastName.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, lastName: value }))}
          placeholder={t("directory.contacts.form.fields.lastName.placeholder")}
          value={values.lastName}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.displayName.accessibilityLabel")}
          error={localErrors.displayName}
          label={t("directory.contacts.form.fields.displayName.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, displayName: value }))
          }
          placeholder={t("directory.contacts.form.fields.displayName.placeholder")}
          value={values.displayName}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.email.accessibilityLabel")}
          autoCapitalize="none"
          error={localErrors.email}
          keyboardType="email-address"
          label={t("directory.contacts.form.fields.email.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, email: value }))}
          placeholder={t("directory.contacts.form.fields.email.placeholder")}
          value={values.email}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.phone.accessibilityLabel")}
          error={localErrors.phone}
          keyboardType="phone-pad"
          label={t("directory.contacts.form.fields.phone.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, phone: value }))}
          placeholder={t("directory.contacts.form.fields.phone.placeholder")}
          value={values.phone}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.jobTitle.accessibilityLabel")}
          error={localErrors.jobTitle}
          label={t("directory.contacts.form.fields.jobTitle.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, jobTitle: value }))
          }
          placeholder={t("directory.contacts.form.fields.jobTitle.placeholder")}
          value={values.jobTitle}
        />
        <TextField
          accessibilityLabel={t("directory.contacts.form.fields.contactType.accessibilityLabel")}
          error={localErrors.contactType}
          label={t("directory.contacts.form.fields.contactType.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, contactType: value }))
          }
          placeholder={t("directory.contacts.form.fields.contactType.placeholder")}
          value={values.contactType}
        />
        <Checkbox
          accessibilityLabel={t("directory.contacts.form.fields.isPrimary.accessibilityLabel")}
          checked={values.isPrimary}
          description={t("directory.contacts.form.fields.isPrimary.helper")}
          label={t("directory.contacts.form.fields.isPrimary.label")}
          onChange={(nextValue) =>
            setValues((current) => ({ ...current, isPrimary: nextValue }))
          }
        />
        <TextArea
          accessibilityLabel={t("directory.contacts.form.fields.notes.accessibilityLabel")}
          autoGrow
          error={localErrors.notes}
          label={t("directory.contacts.form.fields.notes.label")}
          minHeight={120}
          onChangeText={(value) => setValues((current) => ({ ...current, notes: value }))}
          placeholder={t("directory.contacts.form.fields.notes.placeholder")}
          value={values.notes}
        />
      </View>
    </FormCard>
  );
}

export function VenueForm({
  disabled = false,
  errorMessage,
  initialValues,
  loading = false,
  onCancel,
  onSubmit,
  submitLabel,
  subtitle,
  title,
  validationErrors,
}: VenueFormProps) {
  const { t } = useTranslation("app");
  const [values, setValues] = useState<VenueFormValues>({
    ...DEFAULT_VENUE_VALUES,
    ...initialValues,
  });
  const [localErrors, setLocalErrors] = useState<VenueFormErrors>({});

  useEffect(() => {
    setValues({
      ...DEFAULT_VENUE_VALUES,
      ...initialValues,
    });
  }, [initialValues]);

  useEffect(() => {
    setLocalErrors(validationErrors ?? {});
  }, [validationErrors]);

  const handleSubmit = async () => {
    const nextErrors = validateVenue(values, t);
    setLocalErrors(nextErrors);

    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    await onSubmit({
      accessInstructions: toNullable(values.accessInstructions),
      addressLine1: toNullable(values.addressLine1),
      addressLine2: toNullable(values.addressLine2),
      capacity: toNullableInteger(values.capacity),
      city: toNullable(values.city),
      contactEmail: toNullable(values.contactEmail),
      contactName: toNullable(values.contactName),
      contactPhone: toNullable(values.contactPhone),
      countryCode: toNullable(values.countryCode),
      kitchenNotes: toNullable(values.kitchenNotes),
      latitude: toNullable(values.latitude),
      loadingNotes: toNullable(values.loadingNotes),
      longitude: toNullable(values.longitude),
      name: values.name.trim(),
      notes: toNullable(values.notes),
      parkingNotes: toNullable(values.parkingNotes),
      postalCode: toNullable(values.postalCode),
      state: toNullable(values.state),
      status: values.status,
      timezone: toNullable(values.timezone),
    });
  };

  return (
    <FormCard
      disabled={disabled}
      error={errorMessage}
      onCancel={onCancel}
      onSubmit={() => {
        void handleSubmit();
      }}
      submitLabel={submitLabel}
      submitting={loading}
      subtitle={subtitle}
      title={title}
    >
      <View style={{ gap: spacing[3] }}>
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.name.accessibilityLabel")}
          error={localErrors.name}
          label={t("directory.venues.form.fields.name.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, name: value }))}
          placeholder={t("directory.venues.form.fields.name.placeholder")}
          value={values.name}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.addressLine1.accessibilityLabel")}
          error={localErrors.addressLine1}
          label={t("directory.venues.form.fields.addressLine1.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, addressLine1: value }))
          }
          placeholder={t("directory.venues.form.fields.addressLine1.placeholder")}
          value={values.addressLine1}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.addressLine2.accessibilityLabel")}
          error={localErrors.addressLine2}
          label={t("directory.venues.form.fields.addressLine2.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, addressLine2: value }))
          }
          placeholder={t("directory.venues.form.fields.addressLine2.placeholder")}
          value={values.addressLine2}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.city.accessibilityLabel")}
          error={localErrors.city}
          label={t("directory.venues.form.fields.city.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, city: value }))}
          placeholder={t("directory.venues.form.fields.city.placeholder")}
          value={values.city}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.state.accessibilityLabel")}
          error={localErrors.state}
          label={t("directory.venues.form.fields.state.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, state: value }))}
          placeholder={t("directory.venues.form.fields.state.placeholder")}
          value={values.state}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.postalCode.accessibilityLabel")}
          error={localErrors.postalCode}
          label={t("directory.venues.form.fields.postalCode.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, postalCode: value }))
          }
          placeholder={t("directory.venues.form.fields.postalCode.placeholder")}
          value={values.postalCode}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.countryCode.accessibilityLabel")}
          autoCapitalize="characters"
          error={localErrors.countryCode}
          label={t("directory.venues.form.fields.countryCode.label")}
          maxLength={2}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, countryCode: value }))
          }
          placeholder={t("directory.venues.form.fields.countryCode.placeholder")}
          value={values.countryCode}
        />
        <TimezonePicker
          accessibilityLabel={t("directory.venues.form.fields.timezone.accessibilityLabel")}
          error={localErrors.timezone}
          helperText={t("directory.venues.form.fields.timezone.helper")}
          label={t("directory.venues.form.fields.timezone.label")}
          onChange={(value) => setValues((current) => ({ ...current, timezone: value }))}
          value={values.timezone || undefined}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.latitude.accessibilityLabel")}
          error={localErrors.latitude}
          keyboardType="decimal-pad"
          label={t("directory.venues.form.fields.latitude.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, latitude: value }))}
          placeholder={t("directory.venues.form.fields.latitude.placeholder")}
          value={values.latitude}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.longitude.accessibilityLabel")}
          error={localErrors.longitude}
          keyboardType="decimal-pad"
          label={t("directory.venues.form.fields.longitude.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, longitude: value }))}
          placeholder={t("directory.venues.form.fields.longitude.placeholder")}
          value={values.longitude}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.contactName.accessibilityLabel")}
          error={localErrors.contactName}
          label={t("directory.venues.form.fields.contactName.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, contactName: value }))
          }
          placeholder={t("directory.venues.form.fields.contactName.placeholder")}
          value={values.contactName}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.contactEmail.accessibilityLabel")}
          autoCapitalize="none"
          error={localErrors.contactEmail}
          keyboardType="email-address"
          label={t("directory.venues.form.fields.contactEmail.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, contactEmail: value }))
          }
          placeholder={t("directory.venues.form.fields.contactEmail.placeholder")}
          value={values.contactEmail}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.contactPhone.accessibilityLabel")}
          error={localErrors.contactPhone}
          keyboardType="phone-pad"
          label={t("directory.venues.form.fields.contactPhone.label")}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, contactPhone: value }))
          }
          placeholder={t("directory.venues.form.fields.contactPhone.placeholder")}
          value={values.contactPhone}
        />
        <TextField
          accessibilityLabel={t("directory.venues.form.fields.capacity.accessibilityLabel")}
          error={localErrors.capacity}
          keyboardType="number-pad"
          label={t("directory.venues.form.fields.capacity.label")}
          onChangeText={(value) => setValues((current) => ({ ...current, capacity: value }))}
          placeholder={t("directory.venues.form.fields.capacity.placeholder")}
          value={values.capacity}
        />
        <StatusSelect
          accessibilityLabel={t("directory.venues.form.fields.status.accessibilityLabel")}
          error={localErrors.status}
          label={t("directory.venues.form.fields.status.label")}
          namespace="workspaceMembers"
          onChange={(value) =>
            setValues((current) => ({
              ...current,
              status: value as DirectoryStatus,
            }))
          }
          options={STATUS_OPTIONS}
          value={values.status}
        />
        <TextArea
          accessibilityLabel={t("directory.venues.form.fields.accessInstructions.accessibilityLabel")}
          autoGrow
          error={localErrors.accessInstructions}
          label={t("directory.venues.form.fields.accessInstructions.label")}
          minHeight={96}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, accessInstructions: value }))
          }
          placeholder={t("directory.venues.form.fields.accessInstructions.placeholder")}
          value={values.accessInstructions}
        />
        <TextArea
          accessibilityLabel={t("directory.venues.form.fields.parkingNotes.accessibilityLabel")}
          autoGrow
          error={localErrors.parkingNotes}
          label={t("directory.venues.form.fields.parkingNotes.label")}
          minHeight={96}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, parkingNotes: value }))
          }
          placeholder={t("directory.venues.form.fields.parkingNotes.placeholder")}
          value={values.parkingNotes}
        />
        <TextArea
          accessibilityLabel={t("directory.venues.form.fields.loadingNotes.accessibilityLabel")}
          autoGrow
          error={localErrors.loadingNotes}
          label={t("directory.venues.form.fields.loadingNotes.label")}
          minHeight={96}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, loadingNotes: value }))
          }
          placeholder={t("directory.venues.form.fields.loadingNotes.placeholder")}
          value={values.loadingNotes}
        />
        <TextArea
          accessibilityLabel={t("directory.venues.form.fields.kitchenNotes.accessibilityLabel")}
          autoGrow
          error={localErrors.kitchenNotes}
          label={t("directory.venues.form.fields.kitchenNotes.label")}
          minHeight={96}
          onChangeText={(value) =>
            setValues((current) => ({ ...current, kitchenNotes: value }))
          }
          placeholder={t("directory.venues.form.fields.kitchenNotes.placeholder")}
          value={values.kitchenNotes}
        />
        <TextArea
          accessibilityLabel={t("directory.venues.form.fields.notes.accessibilityLabel")}
          autoGrow
          error={localErrors.notes}
          label={t("directory.venues.form.fields.notes.label")}
          minHeight={120}
          onChangeText={(value) => setValues((current) => ({ ...current, notes: value }))}
          placeholder={t("directory.venues.form.fields.notes.placeholder")}
          value={values.notes}
        />
      </View>
    </FormCard>
  );
}

function validateClient(
  values: ClientFormValues,
  t: (key: string) => string
): ClientFormErrors {
  const errors: ClientFormErrors = {};

  if (!values.name.trim()) {
    errors.name = t("directory.clients.form.errors.nameRequired");
  }

  if (values.email.trim() && !isValidEmail(values.email)) {
    errors.email = t("directory.clients.form.errors.emailInvalid");
  }

  if (values.website.trim() && !isValidUrl(values.website)) {
    errors.website = t("directory.clients.form.errors.websiteInvalid");
  }

  if (values.countryCode.trim() && values.countryCode.trim().length !== 2) {
    errors.countryCode = t("directory.clients.form.errors.countryCodeInvalid");
  }

  return errors;
}

function validateContact(
  values: ContactFormValues,
  t: (key: string) => string
): ContactFormErrors {
  const errors: ContactFormErrors = {};

  if (!values.firstName.trim()) {
    errors.firstName = t("directory.contacts.form.errors.firstNameRequired");
  }

  if (values.email.trim() && !isValidEmail(values.email)) {
    errors.email = t("directory.contacts.form.errors.emailInvalid");
  }

  return errors;
}

function validateVenue(
  values: VenueFormValues,
  t: (key: string) => string
): VenueFormErrors {
  const errors: VenueFormErrors = {};

  if (!values.name.trim()) {
    errors.name = t("directory.venues.form.errors.nameRequired");
  }

  if (values.contactEmail.trim() && !isValidEmail(values.contactEmail)) {
    errors.contactEmail = t("directory.venues.form.errors.contactEmailInvalid");
  }

  if (values.countryCode.trim() && values.countryCode.trim().length !== 2) {
    errors.countryCode = t("directory.venues.form.errors.countryCodeInvalid");
  }

  if (values.latitude.trim() && Number.isNaN(Number(values.latitude))) {
    errors.latitude = t("directory.venues.form.errors.latitudeInvalid");
  }

  if (values.longitude.trim() && Number.isNaN(Number(values.longitude))) {
    errors.longitude = t("directory.venues.form.errors.longitudeInvalid");
  }

  if (values.capacity.trim()) {
    const capacity = Number(values.capacity);

    if (!Number.isInteger(capacity) || capacity < 0) {
      errors.capacity = t("directory.venues.form.errors.capacityInvalid");
    }
  }

  return errors;
}

function toNullable(value: string) {
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

function toNullableInteger(value: string) {
  const trimmed = value.trim();

  if (!trimmed) {
    return null;
  }

  const numericValue = Number(trimmed);
  return Number.isFinite(numericValue) ? numericValue : null;
}

function isValidEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function isValidUrl(value: string) {
  try {
    const url = new URL(value.trim());
    return url.protocol === "http:" || url.protocol === "https:";
  } catch {
    return false;
  }
}

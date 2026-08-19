import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { FilePicker, type FilePickerFile } from "@/components/primitives/file-picker";
import {
  type BEOUploadValidationErrors,
} from "@/features/documents";

function mergeValidationErrors(
  localErrors: BEOUploadValidationErrors,
  externalErrors?: BEOUploadValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies BEOUploadValidationErrors;
}

export type BEOUploadFormProps = {
  acceptedTypes?: string[];
  accessibilityLabel?: string;
  disabled?: boolean;
  maxSize?: number;
  onCancel?: () => void;
  onChange?: (file: FilePickerFile | null) => void;
  onSubmit: (file: FilePickerFile) => void | Promise<void>;
  submitting?: boolean;
  validationErrors?: BEOUploadValidationErrors;
  value?: FilePickerFile | null;
};

export function BEOUploadForm({
  acceptedTypes,
  accessibilityLabel,
  disabled = false,
  maxSize,
  onCancel,
  onChange,
  onSubmit,
  submitting = false,
  validationErrors,
  value,
}: BEOUploadFormProps) {
  const { t } = useTranslation("common");
  const initialSignature = JSON.stringify(value ?? null);
  const defaultFile = useMemo(() => value ?? null, [initialSignature]);
  const [selectedFile, setSelectedFile] = useState<FilePickerFile | null>(defaultFile);
  const [localErrors, setLocalErrors] = useState<BEOUploadValidationErrors>({});

  useEffect(() => {
    setSelectedFile(defaultFile);
    setLocalErrors({});
  }, [defaultFile]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);

  const handleSubmit = async () => {
    if (!selectedFile) {
      setLocalErrors({
        file: t("documents.upload.errors.required"),
      });
      return;
    }

    setLocalErrors({});
    await onSubmit(selectedFile);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("documents.upload.accessibilityLabel")}
      cancelLabel={t("documents.actions.cancel")}
      disabled={disabled}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={t("documents.actions.uploadBEO")}
      submitting={submitting}
      title={t("documents.upload.title")}
      variant="default"
    >
      <FilePicker
        acceptedTypes={acceptedTypes}
        accessibilityLabel={t("documents.upload.fields.file.accessibilityLabel")}
        disabled={disabled || submitting}
        error={resolvedErrors.file}
        files={selectedFile ? [selectedFile] : []}
        helperText={t("documents.upload.fields.file.helper")}
        label={t("documents.upload.fields.file.label")}
        maxFiles={1}
        maxSize={maxSize}
        multiple={false}
        onChange={(files) => {
          const nextFile = files[0] ?? null;
          setSelectedFile(nextFile);
          setLocalErrors({});
          onChange?.(nextFile);
        }}
      />
    </FormCard>
  );
}

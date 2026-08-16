import * as DocumentPicker from "expo-document-picker";
import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/primitives/button";
import { FieldLabel } from "@/components/primitives/field-label";
import { FieldMessage } from "@/components/primitives/field-message";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type FilePickerFile = {
  mimeType?: string | null;
  name: string;
  size?: number | null;
  type?: string;
  uri: string;
};

export type FilePickerProps = {
  acceptedTypes?: string[];
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  files?: FilePickerFile[];
  helperText?: string;
  label?: string;
  maxFiles?: number;
  maxSize?: number;
  multiple?: boolean;
  onChange: (files: FilePickerFile[]) => void;
};

function formatFileSize(size?: number | null) {
  if (!size) {
    return undefined;
  }

  if (size >= 1024 * 1024) {
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }

  if (size >= 1024) {
    return `${Math.round(size / 1024)} KB`;
  }

  return `${size} B`;
}

function matchesAcceptedType(file: FilePickerFile, acceptedTypes: string[]) {
  if (acceptedTypes.length === 0) {
    return true;
  }

  const normalizedName = file.name.toLowerCase();
  const mimeType = file.mimeType?.toLowerCase() ?? "";

  return acceptedTypes.some((acceptedType) => {
    const normalizedAcceptedType = acceptedType.toLowerCase();

    if (normalizedAcceptedType.startsWith(".")) {
      return normalizedName.endsWith(normalizedAcceptedType);
    }

    if (normalizedAcceptedType.endsWith("/*")) {
      return mimeType.startsWith(normalizedAcceptedType.replace("*", ""));
    }

    return mimeType === normalizedAcceptedType;
  });
}

export function FilePicker({
  acceptedTypes = [],
  accessibilityLabel,
  disabled = false,
  error,
  files = [],
  helperText,
  label,
  maxFiles,
  maxSize,
  multiple = false,
  onChange,
}: FilePickerProps) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");
  const [localError, setLocalError] = useState<string | undefined>(undefined);

  const pickFiles = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({
        multiple,
        type: acceptedTypes.length > 0 ? acceptedTypes : "*/*",
      });

      if (result.canceled) {
        return;
      }

      const pickedFiles: FilePickerFile[] = result.assets.map((asset) => ({
        mimeType: asset.mimeType,
        name: asset.name,
        size: asset.size,
        type: asset.mimeType ?? undefined,
        uri: asset.uri,
      }));

      const nextFiles = multiple ? [...files, ...pickedFiles] : pickedFiles.slice(0, 1);

      if (typeof maxFiles === "number" && nextFiles.length > maxFiles) {
        setLocalError(t("forms.filePicker.errors.maxFiles"));
        return;
      }

      const oversizedFile = typeof maxSize === "number"
        ? nextFiles.find((file) => (file.size ?? 0) > maxSize)
        : undefined;

      if (oversizedFile) {
        setLocalError(
          t("forms.filePicker.errors.tooLarge", { name: oversizedFile.name })
        );
        return;
      }

      const unsupportedFile = nextFiles.find(
        (file) => !matchesAcceptedType(file, acceptedTypes)
      );

      if (unsupportedFile) {
        setLocalError(
          t("forms.filePicker.errors.unsupported", { name: unsupportedFile.name })
        );
        return;
      }

      setLocalError(undefined);
      onChange(nextFiles);
    } catch {
      setLocalError(t("forms.filePicker.errors.pickFailed"));
    }
  };

  return (
    <View style={{ gap: theme.spacing[2] }}>
      {label ? <FieldLabel label={label} /> : null}
      <View
        style={{
          backgroundColor: disabled
            ? theme.colors.interaction.disabledBackground
            : theme.colors.background.surface,
          borderColor: error || localError
            ? theme.colors.border.error
            : theme.colors.border.default,
          borderCurve: "continuous",
          borderRadius: theme.radius.lg,
          borderWidth: 1,
          gap: theme.spacing[3],
          padding: theme.spacing[4],
        }}
      >
        <Button
          accessibilityLabel={
            accessibilityLabel ??
            t(multiple ? "forms.filePicker.chooseMultiple" : "forms.filePicker.choose")
          }
          disabled={disabled}
          label={t(
            multiple ? "forms.filePicker.chooseMultiple" : "forms.filePicker.choose"
          )}
          onPress={pickFiles}
          variant="secondary"
        />
        {acceptedTypes.length > 0 ? (
          <Text tone="muted" variant="caption">
            {t("forms.filePicker.acceptedTypes", {
              types: acceptedTypes.join(", "),
            })}
          </Text>
        ) : null}
        {typeof maxSize === "number" ? (
          <Text tone="muted" variant="caption">
            {t("forms.filePicker.maxSize", {
              size: formatFileSize(maxSize) ?? maxSize,
            })}
          </Text>
        ) : null}
        {typeof maxFiles === "number" ? (
          <Text tone="muted" variant="caption">
            {t("forms.filePicker.maxFiles", { count: maxFiles })}
          </Text>
        ) : null}
        <View style={{ gap: theme.spacing[2] }}>
          {files.length === 0 ? (
            <Text tone="muted" variant="bodySmall">
              {t("forms.filePicker.noFiles")}
            </Text>
          ) : (
            files.map((file, index) => (
              <View
                key={`${file.uri}-${index}`}
                style={{
                  alignItems: "center",
                  flexDirection: "row",
                  gap: theme.spacing[2],
                  justifyContent: "space-between",
                }}
              >
                <View style={{ flex: 1, gap: theme.spacing[1] }}>
                  <Text variant="bodySmall">{file.name}</Text>
                  <Text tone="muted" variant="caption">
                    {[file.type ?? file.mimeType, formatFileSize(file.size)]
                      .filter(Boolean)
                      .join(" · ")}
                  </Text>
                </View>
                <IconButton
                  accessibilityLabel={t("forms.filePicker.remove")}
                  disabled={disabled}
                  icon={<Text variant="bodySmall">x</Text>}
                  onPress={() =>
                    onChange(files.filter((_, fileIndex) => fileIndex !== index))
                  }
                  shape="circle"
                  size="sm"
                  variant="ghost"
                />
              </View>
            ))
          )}
        </View>
      </View>
      <FieldMessage error={error ?? localError} helperText={helperText} />
    </View>
  );
}

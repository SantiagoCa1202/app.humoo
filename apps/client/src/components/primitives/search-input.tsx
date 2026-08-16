import type { TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { InputGlyph, TextFieldBase } from "@/components/primitives/text-field-base";

export type SearchInputProps = Omit<TextInputProps, "label"> & {
  accessibilityLabel?: string;
};

export function SearchInput({
  accessibilityLabel,
  placeholder,
  value,
  onChangeText,
  ...props
}: SearchInputProps) {
  const { t } = useTranslation("common");

  return (
    <TextFieldBase
      accessibilityLabel={accessibilityLabel ?? t("search")}
      leftAdornment={<InputGlyph label="?" />}
      onChangeText={onChangeText}
      placeholder={placeholder ?? t("search")}
      rightAdornment={
        value ? (
          <IconButton
            accessibilityLabel={t("clearSearch")}
            disabled={props.editable === false}
            icon={<Text variant="bodySmall">x</Text>}
            onPress={() => onChangeText?.("")}
            shape="circle"
            size="sm"
            variant="ghost"
          />
        ) : null
      }
      returnKeyType="search"
      value={value}
      {...props}
    />
  );
}

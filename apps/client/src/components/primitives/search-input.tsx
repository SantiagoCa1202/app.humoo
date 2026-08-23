import { Feather } from "@expo/vector-icons";
import type { TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { TextFieldBase } from "@/components/primitives/text-field-base";
import { useAppTheme } from "@/theme/ThemeProvider";

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
  const { theme } = useAppTheme();

  return (
    <TextFieldBase
      accessibilityLabel={accessibilityLabel ?? t("search")}
      leftAdornment={
        <Feather
          color={theme.colors.text.muted}
          name="search"
          size={theme.iconSizes.md}
        />
      }
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

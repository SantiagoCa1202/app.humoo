import type { TextInputProps } from "react-native";

import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { InputGlyph, TextFieldBase } from "@/components/primitives/text-field-base";

export type SearchInputProps = Omit<TextInputProps, "label"> & {
  accessibilityLabel?: string;
};

export function SearchInput({
  accessibilityLabel = "Search",
  placeholder = "Search",
  value,
  onChangeText,
  ...props
}: SearchInputProps) {
  return (
    <TextFieldBase
      accessibilityLabel={accessibilityLabel}
      leftAdornment={<InputGlyph label="?" />}
      onChangeText={onChangeText}
      placeholder={placeholder}
      rightAdornment={
        value ? (
          <IconButton
            accessibilityLabel="Clear search"
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

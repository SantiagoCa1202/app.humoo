import type { TextInputProps } from "react-native";

import { TextFieldBase } from "@/components/primitives/text-field-base";

export type TextAreaProps = Omit<TextInputProps, "multiline"> & {
  autoGrow?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  minHeight?: number;
  optional?: boolean;
  required?: boolean;
};

export function TextArea({
  autoGrow = false,
  error,
  helperText,
  minHeight,
  optional,
  required,
  ...props
}: TextAreaProps) {
  return (
    <TextFieldBase
      autoGrow={autoGrow}
      error={error}
      helperText={helperText}
      minHeight={minHeight}
      multiline
      optional={optional}
      required={required}
      {...props}
    />
  );
}

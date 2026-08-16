import { useState } from "react";
import type { TextInputProps } from "react-native";

import {
  InputGlyph,
  TextFieldBase,
  ToggleAdornment,
} from "@/components/primitives/text-field-base";

export type TextFieldProps = TextInputProps & {
  error?: string;
  helperText?: string;
  hint?: string;
  label?: string;
  leftIcon?: React.ReactNode;
  optional?: boolean;
  required?: boolean;
  rightIcon?: React.ReactNode;
  secure?: boolean;
};

export function TextField({
  error,
  helperText,
  hint,
  leftIcon,
  optional,
  required,
  rightIcon,
  secure = false,
  secureTextEntry,
  ...props
}: TextFieldProps) {
  const [isSecure, setIsSecure] = useState(Boolean(secure || secureTextEntry));
  const showSecureToggle = secure || secureTextEntry;

  return (
    <TextFieldBase
      error={error}
      helperText={helperText ?? hint}
      leftAdornment={
        leftIcon ?? (props.keyboardType === "email-address" ? <InputGlyph label="@" /> : null)
      }
      optional={optional}
      required={required}
      rightAdornment={
        showSecureToggle ? (
          <ToggleAdornment
            disabled={props.editable === false}
            onPress={() => setIsSecure((value) => !value)}
            secure={isSecure}
          />
        ) : (
          rightIcon
        )
      }
      secureTextEntry={showSecureToggle ? isSecure : secureTextEntry}
      {...props}
    />
  );
}

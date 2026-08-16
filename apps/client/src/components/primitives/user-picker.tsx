import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import {
  EntityPicker,
  type EntityPickerOption,
} from "@/components/primitives/entity-picker";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type UserPickerOption<T extends string> = EntityPickerOption<T> & {
  avatarSource?: Parameters<typeof Avatar>[0]["source"];
  roleLabel?: string;
  roleTranslationKey?: string;
  status?: Parameters<typeof Avatar>[0]["status"];
};

export type UserPickerProps<T extends string> = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onChange: (value: T) => void;
  placeholder?: string;
  searchable?: boolean;
  users: UserPickerOption<T>[];
  value?: T;
};

export function UserPicker<T extends string>({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  onChange,
  placeholder,
  searchable = true,
  users,
  value,
}: UserPickerProps<T>) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <EntityPicker
      accessibilityLabel={accessibilityLabel}
      disabled={disabled}
      entities={users.map((user) => ({
        ...user,
        icon: (
          <Avatar
            name={user.label ?? user.name}
            size="sm"
            source={user.avatarSource}
            status={user.status}
          />
        ),
        metadata: user.roleTranslationKey
          ? t(user.roleTranslationKey)
          : user.roleLabel ?? user.metadata,
      }))}
      error={error}
      helperText={helperText}
      label={label}
      onChange={onChange}
      placeholder={placeholder ?? t("forms.userPicker.placeholder")}
      searchable={searchable}
      value={value}
    />
  );
}

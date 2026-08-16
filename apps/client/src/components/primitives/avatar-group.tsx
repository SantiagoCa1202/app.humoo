import { View } from "react-native";

import { Avatar, type AvatarProps } from "@/components/primitives/avatar";
import { useAppTheme } from "@/theme/ThemeProvider";

export type AvatarGroupUser = Pick<
  AvatarProps,
  "name" | "source" | "status" | "variant"
>;

export type AvatarGroupProps = {
  max?: number;
  size?: AvatarProps["size"];
  users: AvatarGroupUser[];
};

export function AvatarGroup({
  max = 3,
  size = "md",
  users,
}: AvatarGroupProps) {
  const { theme } = useAppTheme();
  const visibleUsers = users.slice(0, max);
  const extraCount = Math.max(users.length - max, 0);

  return (
    <View style={{ alignItems: "center", flexDirection: "row" }}>
      {visibleUsers.map((user, index) => (
        <View
          key={`${user.name ?? "avatar"}-${index}`}
          style={{
            marginLeft: index === 0 ? 0 : -theme.spacing[3],
            zIndex: visibleUsers.length - index,
          }}
        >
          <Avatar
            name={user.name}
            showBorder
            size={size}
            source={user.source}
            status={user.status}
            variant={user.variant}
          />
        </View>
      ))}
      {extraCount > 0 ? (
        <View
          style={{
            marginLeft: visibleUsers.length === 0 ? 0 : -theme.spacing[3],
            zIndex: 0,
          }}
        >
          <Avatar name={`+${extraCount}`} showBorder size={size} variant="neutral" />
        </View>
      ) : null}
    </View>
  );
}

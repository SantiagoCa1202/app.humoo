import { Feather } from "@expo/vector-icons";
import type { Href } from "expo-router";

import type { GlobalSearchResult } from "@/features/search/types";
import { routes } from "@/navigation/routes";

export type SearchCommand = {
  icon: keyof typeof Feather.glyphMap;
  key: string;
  keywords: string[];
  labelKey: string;
  permission?: string;
  route: Href;
};

type SearchNavigationTarget = Href | {
  pathname: string;
  params: Record<string, string>;
};

export const searchCommands: SearchCommand[] = [
  { icon: "calendar", key: "navigation.events", keywords: ["event"], labelKey: "commandEvents", permission: "events.view", route: routes.app.events },
  { icon: "coffee", key: "navigation.recipes", keywords: ["recipe"], labelKey: "commandRecipes", permission: "recipes.view", route: routes.app.recipes },
  { icon: "book-open", key: "navigation.menus", keywords: ["menu"], labelKey: "commandMenus", permission: "menus.view", route: routes.app.menus },
  { icon: "clipboard", key: "navigation.prep", keywords: ["prep", "production"], labelKey: "commandPrep", permission: "prep_lists.view", route: routes.app.prep },
  { icon: "check-square", key: "navigation.tasks", keywords: ["task"], labelKey: "commandTasks", permission: "tasks.view", route: routes.app.tasks },
  { icon: "settings", key: "navigation.settings", keywords: ["settings", "configuration"], labelKey: "commandSettings", route: routes.app.settings },
  { icon: "plus-circle", key: "create.event", keywords: ["new", "event"], labelKey: "commandCreateEvent", permission: "events.create", route: routes.app.eventCreate },
  { icon: "upload", key: "create.uploadBeo", keywords: ["upload", "beo", "document"], labelKey: "commandUploadBeo", permission: "events.create", route: routes.app.documentUpload },
  { icon: "plus-circle", key: "create.recipe", keywords: ["new", "recipe"], labelKey: "commandCreateRecipe", permission: "recipes.create", route: routes.app.recipeCreate },
  { icon: "plus-circle", key: "create.menu", keywords: ["new", "menu"], labelKey: "commandCreateMenu", permission: "menus.create", route: routes.app.menuCreate },
  { icon: "plus-circle", key: "create.task", keywords: ["new", "task"], labelKey: "commandCreateTask", permission: "tasks.create", route: routes.app.taskCreate },
];

export function getAvailableCommands(
  hasPermission: (permissionKey: string) => boolean,
  translate: (key: string) => string,
  input = ""
) {
  const normalizedInput = input.trim().toLocaleLowerCase();

  return searchCommands.filter((command) => {
    if (command.permission && !hasPermission(command.permission)) {
      return false;
    }

    if (!normalizedInput) {
      return true;
    }

    return [translate(command.labelKey), ...command.keywords]
      .join(" ")
      .toLocaleLowerCase()
      .includes(normalizedInput);
  });
}

export function navigateToSearchResult(
  result: GlobalSearchResult,
  navigate: (href: SearchNavigationTarget) => void
) {
  switch (result.type) {
    case "event":
      navigate({ pathname: "/(app)/events/[eventId]", params: { eventId: result.id } });
      return;
    case "document":
      navigate({ pathname: "/(app)/documents/[documentId]", params: { documentId: result.id } });
      return;
    case "recipe":
      navigate({ pathname: "/(app)/recipes/[recipeId]", params: { recipeId: result.id } });
      return;
    case "menu":
      navigate({ pathname: "/(app)/menus/[menuId]", params: { menuId: result.id } });
      return;
    case "prep":
      navigate({ pathname: "/(app)/prep/[prepListId]", params: { prepListId: result.id } });
      return;
    case "task":
      navigate({ pathname: "/(app)/tasks/[taskId]", params: { taskId: result.id } });
      return;
    case "staff":
    case "station":
    case "team":
      navigate(routes.app.teamRoster);
      return;
    default:
      return;
  }
}

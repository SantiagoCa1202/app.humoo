import { apiRequest } from "@/api/client";
import type { MenuEditorValues } from "@/features/menus/forms";
import type {
  MenuDetailRecord,
  MenuDuplicateOptions,
  MenuRecord,
  MenuSectionRecord,
  MenusCursorPage,
  MenuVersionRecord,
} from "@/features/menus/types";

type ApiUserReference = {
  id?: string | null;
  name?: string | null;
};

type ApiEventReference = {
  id?: string | null;
  name?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  status?: string | null;
  timezone?: string | null;
  venue?:
    | {
        id?: string | null;
        name?: string | null;
      }
    | null
    | string;
};

type ApiRecipeReference = {
  id: string;
  name?: string | null;
  current_version_id?: string | null;
};

type ApiMenuItem = {
  id?: string | null;
  description?: string | null;
  name: string;
  notes?: string | null;
  position?: number | null;
  recipe?: ApiRecipeReference | null;
  recipe_id?: string | null;
  recipe_version?: { id?: string | null; name?: string | null; version?: number | null } | null;
  recipe_version_id?: string | null;
};

type ApiMenuSection = {
  id?: string | null;
  item_count?: number | null;
  items?: ApiMenuItem[] | null;
  name?: string | null;
  position?: number | null;
};

type ApiMenuVersion = {
  change_summary?: string | null;
  created_at?: string | null;
  created_by?: ApiUserReference | null;
  id: string;
  is_current?: boolean | null;
  revision?: number | null;
  sections?: ApiMenuSection[] | null;
  status?: string | null;
  version?: number | null;
};

type ApiAllergen = {
  code?: string | null;
  id?: string | null;
  metadata?: string | null;
  name?: string | null;
  severity?: MenuRecord["allergens"] extends Array<infer T>
    ? T extends { severity?: infer S }
      ? S
      : never
    : never;
  translation_key?: string | null;
};

type ApiMenu = {
  allergen_count?: number | null;
  allergens?: ApiAllergen[] | null;
  created_at?: string | null;
  current_version?: number | null;
  current_version_id?: string | null;
  current_version_record?: ApiMenuVersion | null;
  default_guest_count?: number | null;
  description?: string | null;
  event?: ApiEventReference | null;
  guest_count?: number | null;
  id: string;
  item_count?: number | null;
  metadata?: Record<string, unknown> | null;
  name: string;
  recipe_count?: number | null;
  section_count?: number | null;
  sections?: ApiMenuSection[] | null;
  status?: MenuRecord["status"] | null;
  summary?: string | null;
  type?: string | null;
  unknown_allergen_item_count?: number | null;
  updated_at?: string | null;
  version?: ApiMenuVersion | null;
};

type ApiCursorResponse = {
  data: ApiMenu[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

function mapUser(user?: ApiUserReference | null) {
  if (!user) {
    return null;
  }

  return {
    id: user.id ?? undefined,
    name: user.name ?? null,
  };
}

function mapItem(item: ApiMenuItem) {
  return {
    description: item.description ?? null,
    id: item.id ?? null,
    name: item.name,
    notes: item.notes ?? null,
    position: item.position ?? null,
    recipe: item.recipe
      ? {
          id: item.recipe.id,
          name: item.recipe.name ?? null,
        }
      : null,
    recipeId: item.recipe_id ?? item.recipe?.id ?? null,
    recipeVersionId: item.recipe_version_id ?? item.recipe_version?.id ?? null,
  };
}

function mapSection(section: ApiMenuSection): MenuSectionRecord {
  return {
    id: section.id ?? undefined,
    itemCount: section.item_count ?? section.items?.length ?? 0,
    items: section.items?.map(mapItem) ?? [],
    name: section.name ?? null,
    position: section.position ?? null,
  };
}

function mapVersion(version: ApiMenuVersion): MenuVersionRecord {
  return {
    changeSummary: version.change_summary ?? null,
    createdAt: version.created_at ?? null,
    createdBy: mapUser(version.created_by),
    id: version.id,
    isCurrent: version.is_current ?? null,
    revision: version.revision ?? null,
    sections: version.sections?.map(mapSection) ?? [],
    status: version.status ?? null,
    versionLabel:
      version.version !== null && version.version !== undefined
        ? `Version ${version.version}`
        : null,
    versionNumber: version.version ?? null,
  };
}

function mapEvent(event?: ApiEventReference | null) {
  if (!event) {
    return null;
  }

  return {
    endsAt: event.ends_at ?? null,
    id: event.id ?? undefined,
    name: event.name ?? null,
    startsAt: event.starts_at ?? null,
    status: (event.status as MenuRecord["event"] extends { status?: infer T } ? T : never) ?? null,
    timezone: event.timezone ?? null,
    venue:
      typeof event.venue === "string"
        ? event.venue
        : event.venue
        ? {
            id: event.venue.id ?? undefined,
            name: event.venue.name ?? null,
          }
        : null,
  };
}

function mapMenu(menu: ApiMenu): MenuRecord {
  const currentVersion = menu.current_version_record ? mapVersion(menu.current_version_record) : null;

  return {
    allergenCount: menu.allergen_count ?? null,
    allergens:
      menu.allergens?.map((allergen) => ({
        code: allergen.code ?? null,
        id: allergen.id ?? undefined,
        metadata: allergen.metadata ?? null,
        name: allergen.name ?? null,
        severity: allergen.severity ?? null,
        translationKey: allergen.translation_key ?? null,
      })) ?? [],
    createdAt: menu.created_at ?? null,
    currentVersion: menu.current_version ?? null,
    currentVersionId: menu.current_version_id ?? null,
    currentVersionRecord: currentVersion,
    description: menu.description ?? null,
    event: mapEvent(menu.event),
    eventId: menu.event?.id ?? null,
    guestCount: menu.guest_count ?? menu.default_guest_count ?? null,
    id: menu.id,
    itemCount: menu.item_count ?? null,
    metadata: menu.metadata ?? null,
    name: menu.name,
    recipeCount: menu.recipe_count ?? null,
    sectionCount: menu.section_count ?? null,
    sections: (menu.sections ?? currentVersion?.sections ?? []).map(mapSection),
    status: menu.status ?? null,
    summary: menu.summary ?? null,
    tags: [],
    type: menu.type ?? null,
    unknownAllergenItemCount: menu.unknown_allergen_item_count ?? null,
    updatedAt: menu.updated_at ?? null,
    version: menu.version ? mapVersion(menu.version) : currentVersion,
    versions: null,
  };
}

function buildMenuPayload(values: MenuEditorValues, includeConflict: boolean) {
  const payload: Record<string, unknown> = {
    name: values.name.trim(),
    description: values.description?.trim() || null,
    status: values.status ?? "draft",
    type: values.type?.trim() || null,
    default_guest_count: values.guestCount ?? null,
    event_id: values.eventId ?? null,
    sections: (values.sections ?? []).map((section, sectionIndex) => ({
      id: section.id ?? undefined,
      name: section.name?.trim() || "",
      position: section.position ?? sectionIndex + 1,
      items: (section.items ?? []).map((item, itemIndex) => ({
        id: item.id ?? undefined,
        name: item.name.trim(),
        description: item.description?.trim() || null,
        notes: item.notes?.trim() || null,
        position: item.position ?? itemIndex + 1,
        recipe_id: item.recipeId ?? null,
        recipe_version_id: item.recipeVersionId ?? null,
      })),
    })),
  };

  if (includeConflict) {
    payload.current_version_id = values.currentVersionId;
    payload.expected_revision = values.version?.revision ?? values.currentVersionRecord?.revision ?? null;
  }

  return payload;
}

export async function listMenus(
  authToken: string,
  workspaceId: string,
  filters: { cursor?: string | null; perPage?: number; search?: string; status?: string | null } = {}
): Promise<MenusCursorPage> {
  const response = await apiRequest<ApiCursorResponse>("/menus", {
    authToken,
    query: {
      cursor: filters.cursor ?? undefined,
      per_page: filters.perPage ?? undefined,
      search: filters.search?.trim() || undefined,
      status: filters.status ?? undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapMenu),
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}

export async function getMenu(authToken: string, workspaceId: string, menuId: string): Promise<MenuDetailRecord> {
  const response = await apiRequest<{ data: ApiMenu }>(`/menus/${menuId}`, {
    authToken,
    workspaceId,
  });

  const menu = mapMenu(response.data);

  return {
    currentVersion: menu.currentVersionRecord ?? menu.version ?? null,
    menu,
  };
}

export async function createMenu(
  authToken: string,
  workspaceId: string,
  values: MenuEditorValues
): Promise<MenuDetailRecord> {
  const response = await apiRequest<{ data: ApiMenu }>("/menus", {
    method: "POST",
    authToken,
    body: JSON.stringify(buildMenuPayload(values, false)),
    workspaceId,
  });

  const menu = mapMenu(response.data);

  return {
    currentVersion: menu.currentVersionRecord ?? menu.version ?? null,
    menu,
  };
}

export async function updateMenu(
  authToken: string,
  workspaceId: string,
  menuId: string,
  values: MenuEditorValues
): Promise<MenuDetailRecord> {
  const response = await apiRequest<{ data: ApiMenu }>(`/menus/${menuId}`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify(buildMenuPayload(values, true)),
    workspaceId,
  });

  const menu = mapMenu(response.data);

  return {
    currentVersion: menu.currentVersionRecord ?? menu.version ?? null,
    menu,
  };
}

export async function getMenuVersions(
  authToken: string,
  workspaceId: string,
  menuId: string
): Promise<MenuVersionRecord[]> {
  const response = await apiRequest<{ data: ApiMenuVersion[] }>(`/menus/${menuId}/versions`, {
    authToken,
    workspaceId,
  });

  return response.data.map(mapVersion);
}

export async function duplicateMenu(
  authToken: string,
  workspaceId: string,
  menuId: string,
  options: MenuDuplicateOptions
): Promise<MenuDetailRecord> {
  const response = await apiRequest<{ data: ApiMenu }>(`/menus/${menuId}/duplicate`, {
    method: "POST",
    authToken,
    body: JSON.stringify({
      include_items: options.includeItems ?? true,
      include_recipe_links: options.includeRecipeLinks ?? false,
      include_sections: options.includeSections ?? true,
      proposed_name: options.proposedName?.trim() || null,
      target_event_id: options.targetEventId ?? null,
    }),
    workspaceId,
  });

  const menu = mapMenu(response.data);

  return {
    currentVersion: menu.currentVersionRecord ?? menu.version ?? null,
    menu,
  };
}

export type MenuStatus = "draft" | "active" | "published" | "archived";

export type MenuTagValue =
  | string
  | {
      id?: string;
      label: string;
    };

export type MenuSectionSummary = {
  id?: string;
  itemCount?: number | null;
  name?: string | null;
  position?: number | null;
  translationKey?: string | null;
};

export type MenuRecipeReference = {
  id: string;
  name?: string | null;
};

export type MenuItemRecord = {
  clientId?: string | null;
  description?: string | null;
  id?: string | null;
  name: string;
  notes?: string | null;
  position?: number | null;
  quantityLabel?: string | null;
  recipe?: MenuRecipeReference | null;
  recipeId?: string | null;
};

export type MenuSectionRecord = MenuSectionSummary & {
  clientId?: string | null;
  items: MenuItemRecord[];
};

export type MenuEventReference = {
  id?: string;
  name?: string | null;
  startsAt?: string | null;
  endsAt?: string | null;
  timezone?: string | null;
};

export type MenuRecord = {
  allergenCount?: number | null;
  createdAt?: string | null;
  description?: string | null;
  event?: MenuEventReference | null;
  eventId?: string | null;
  guestCount?: number | null;
  id: string;
  itemCount?: number | null;
  name: string;
  recipeCount?: number | null;
  sectionCount?: number | null;
  sections?: MenuSectionRecord[];
  status?: MenuStatus | null;
  summary?: string | null;
  tags?: MenuTagValue[];
  updatedAt?: string | null;
};

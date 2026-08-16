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
  translationKey?: string | null;
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
  guestCount?: number | null;
  id: string;
  itemCount?: number | null;
  name: string;
  recipeCount?: number | null;
  sectionCount?: number | null;
  sections?: MenuSectionSummary[];
  status?: MenuStatus | null;
  summary?: string | null;
  tags?: MenuTagValue[];
  updatedAt?: string | null;
};

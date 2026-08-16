import type React from "react";

import type { EventStatus } from "@/features/events/types";
import type { SemanticStatusTone } from "@/theme/status-config";

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
  status?: EventStatus | null;
  timezone?: string | null;
  venue?:
    | string
    | {
        id?: string;
        name?: string | null;
      }
    | null;
};

export type MenuAllergenRecord = {
  code?: string | null;
  id?: string;
  metadata?: string | null;
  name?: string | null;
  severity?: SemanticStatusTone | null;
  translationKey?: string | null;
};

export type MenuVersionActor = {
  id?: string;
  name?: string | null;
};

export type MenuVersionRecord = {
  changeSummary?: string | null;
  createdAt?: string | null;
  createdBy?: MenuVersionActor | null;
  id: string;
  isCurrent?: boolean | null;
  notes?: string | null;
  versionLabel?: string | null;
  versionNumber?: number | string | null;
};

export type MenuConflictType =
  | "version_conflict"
  | "remote_update"
  | "section_changed"
  | "item_changed"
  | "stale_data";

export type MenuConflictChange = {
  after: React.ReactNode;
  before: React.ReactNode;
  id?: string;
  label: React.ReactNode;
};

export type MenuDuplicateOptions = {
  includeItems?: boolean;
  includeRecipeLinks?: boolean;
  includeSections?: boolean;
  proposedName?: string | null;
  targetEvent?: MenuEventReference | null;
  targetEventId?: string | null;
};

export type MenuRecipeSummaryRecord = {
  id: string;
  itemCount?: number | null;
  itemNames?: string[] | null;
  name?: string | null;
};

export type MenuRecord = {
  allergenCount?: number | null;
  allergens?: MenuAllergenRecord[] | null;
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
  version?: MenuVersionRecord | null;
  versions?: MenuVersionRecord[] | null;
};

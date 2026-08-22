export const searchResultTypes = [
  "event",
  "document",
  "recipe",
  "menu",
  "team",
  "station",
  "prep",
  "task",
  "staff",
] as const;

export type GlobalSearchResultType = (typeof searchResultTypes)[number];

export type GlobalSearchResult = {
  type: GlobalSearchResultType;
  id: string;
  title: string;
  subtitle: string | null;
  metadata: Record<string, unknown>;
  target: string;
};

export type GlobalSearchResponse = {
  query: string;
  results: GlobalSearchResult[];
};

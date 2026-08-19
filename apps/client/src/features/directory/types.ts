export type DirectoryStatus = "active" | "inactive";

export type DirectoryCollection<T> = {
  data: T[];
};

export type DirectoryDetail<T> = {
  data: T;
};

export type ClientSummary = {
  id: string;
  name: string;
  companyName: string | null;
  email: string | null;
  phone: string | null;
  status: DirectoryStatus | null;
};

export type ContactSummary = {
  id: string;
  clientId: string | null;
  displayName: string | null;
  fullName: string;
  email: string | null;
  phone: string | null;
  jobTitle: string | null;
  contactType: string | null;
  isPrimary: boolean;
};

export type VenueSummary = {
  id: string;
  name: string;
  city: string | null;
  state: string | null;
  timezone: string | null;
  status: DirectoryStatus | null;
};

export type ClientRecord = {
  id: string;
  name: string;
  companyName: string | null;
  email: string | null;
  phone: string | null;
  website: string | null;
  taxId: string | null;
  addressLine1: string | null;
  addressLine2: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  countryCode: string | null;
  status: DirectoryStatus | null;
  notes: string | null;
  contactsCount: number | null;
  primaryContact: ContactSummary | null;
  contacts: ContactSummary[];
  createdAt: string | null;
  updatedAt: string | null;
};

export type ContactRecord = {
  id: string;
  clientId: string | null;
  firstName: string;
  lastName: string | null;
  displayName: string | null;
  fullName: string;
  email: string | null;
  phone: string | null;
  jobTitle: string | null;
  contactType: string | null;
  isPrimary: boolean;
  notes: string | null;
  client: ClientSummary | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type VenueRecord = {
  id: string;
  name: string;
  addressLine1: string | null;
  addressLine2: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  countryCode: string | null;
  latitude: string | null;
  longitude: string | null;
  timezone: string | null;
  contactName: string | null;
  contactEmail: string | null;
  contactPhone: string | null;
  capacity: number | null;
  accessInstructions: string | null;
  parkingNotes: string | null;
  loadingNotes: string | null;
  kitchenNotes: string | null;
  notes: string | null;
  status: DirectoryStatus | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type ClientFilters = {
  search?: string;
  status?: DirectoryStatus | "";
};

export type ContactFilters = {
  clientId?: string;
  search?: string;
};

export type VenueFilters = {
  search?: string;
  status?: DirectoryStatus | "";
};

export type ClientMutationInput = {
  name: string;
  companyName: string | null;
  email: string | null;
  phone: string | null;
  website: string | null;
  taxId: string | null;
  addressLine1: string | null;
  addressLine2: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  countryCode: string | null;
  status: DirectoryStatus | null;
  notes: string | null;
};

export type ContactMutationInput = {
  clientId: string | null;
  firstName: string;
  lastName: string | null;
  displayName: string | null;
  email: string | null;
  phone: string | null;
  jobTitle: string | null;
  contactType: string | null;
  isPrimary: boolean;
  notes: string | null;
};

export type VenueMutationInput = {
  name: string;
  addressLine1: string | null;
  addressLine2: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  countryCode: string | null;
  latitude: string | null;
  longitude: string | null;
  timezone: string | null;
  contactName: string | null;
  contactEmail: string | null;
  contactPhone: string | null;
  capacity: number | null;
  accessInstructions: string | null;
  parkingNotes: string | null;
  loadingNotes: string | null;
  kitchenNotes: string | null;
  notes: string | null;
  status: DirectoryStatus | null;
};

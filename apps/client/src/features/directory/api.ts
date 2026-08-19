import { apiRequest } from "@/api/client";
import type {
  ClientFilters,
  ClientMutationInput,
  ClientRecord,
  ClientSummary,
  ContactFilters,
  ContactMutationInput,
  ContactRecord,
  ContactSummary,
  DirectoryCollection,
  DirectoryDetail,
  DirectoryStatus,
  VenueFilters,
  VenueMutationInput,
  VenueRecord,
  VenueSummary,
} from "@/features/directory/types";

type ApiClientSummary = {
  id: string;
  name: string;
  company_name: string | null;
  email: string | null;
  phone: string | null;
  status: DirectoryStatus | null;
};

type ApiContactSummary = {
  id: string;
  client_id: string | null;
  display_name: string | null;
  full_name: string;
  email: string | null;
  phone: string | null;
  job_title: string | null;
  contact_type: string | null;
  is_primary: boolean;
};

type ApiVenueSummary = {
  id: string;
  name: string;
  city: string | null;
  state: string | null;
  timezone: string | null;
  status: DirectoryStatus | null;
};

type ApiClientRecord = {
  id: string;
  name: string;
  company_name: string | null;
  email: string | null;
  phone: string | null;
  website: string | null;
  tax_id: string | null;
  address_line_1: string | null;
  address_line_2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country_code: string | null;
  status: DirectoryStatus | null;
  notes: string | null;
  contacts_count?: number | null;
  primary_contact?: ApiContactSummary | null;
  contacts?: ApiContactSummary[];
  created_at: string | null;
  updated_at: string | null;
};

type ApiContactRecord = {
  id: string;
  client_id: string | null;
  first_name: string;
  last_name: string | null;
  display_name: string | null;
  full_name: string;
  email: string | null;
  phone: string | null;
  job_title: string | null;
  contact_type: string | null;
  is_primary: boolean;
  notes: string | null;
  client?: ApiClientSummary | null;
  created_at: string | null;
  updated_at: string | null;
};

type ApiVenueRecord = {
  id: string;
  name: string;
  address_line_1: string | null;
  address_line_2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country_code: string | null;
  latitude: string | null;
  longitude: string | null;
  timezone: string | null;
  contact_name: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  capacity: number | null;
  access_instructions: string | null;
  parking_notes: string | null;
  loading_notes: string | null;
  kitchen_notes: string | null;
  notes: string | null;
  status: DirectoryStatus | null;
  created_at: string | null;
  updated_at: string | null;
};

type ClientCollectionResponse = {
  data: ApiClientRecord[];
};

type ContactCollectionResponse = {
  data: ApiContactRecord[];
};

type VenueCollectionResponse = {
  data: ApiVenueRecord[];
};

type ClientDetailResponse = {
  data: ApiClientRecord;
};

type ContactDetailResponse = {
  data: ApiContactRecord;
};

type VenueDetailResponse = {
  data: ApiVenueRecord;
};

export async function listClients(
  authToken: string,
  workspaceId: string,
  filters: ClientFilters = {}
): Promise<DirectoryCollection<ClientRecord>> {
  const response = await apiRequest<ClientCollectionResponse>("/clients", {
    authToken,
    query: {
      search: filters.search?.trim() || undefined,
      status: filters.status || undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapClient),
  };
}

export async function getClient(
  authToken: string,
  workspaceId: string,
  clientId: string
): Promise<DirectoryDetail<ClientRecord>> {
  const response = await apiRequest<ClientDetailResponse>(`/clients/${clientId}`, {
    authToken,
    workspaceId,
  });

  return {
    data: mapClient(response.data),
  };
}

export async function createClient(
  authToken: string,
  workspaceId: string,
  input: ClientMutationInput
): Promise<ClientRecord> {
  const response = await apiRequest<ClientDetailResponse>("/clients", {
    authToken,
    body: JSON.stringify(mapClientInput(input)),
    method: "POST",
    workspaceId,
  });

  return mapClient(response.data);
}

export async function updateClient(
  authToken: string,
  workspaceId: string,
  clientId: string,
  input: ClientMutationInput
): Promise<ClientRecord> {
  const response = await apiRequest<ClientDetailResponse>(`/clients/${clientId}`, {
    authToken,
    body: JSON.stringify(mapClientInput(input)),
    method: "PATCH",
    workspaceId,
  });

  return mapClient(response.data);
}

export async function deleteClient(
  authToken: string,
  workspaceId: string,
  clientId: string
): Promise<void> {
  await apiRequest<null>(`/clients/${clientId}`, {
    authToken,
    method: "DELETE",
    workspaceId,
  });
}

export async function listContacts(
  authToken: string,
  workspaceId: string,
  filters: ContactFilters = {}
): Promise<DirectoryCollection<ContactRecord>> {
  const response = await apiRequest<ContactCollectionResponse>("/contacts", {
    authToken,
    query: {
      client_id: filters.clientId || undefined,
      search: filters.search?.trim() || undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapContact),
  };
}

export async function getContact(
  authToken: string,
  workspaceId: string,
  contactId: string
): Promise<DirectoryDetail<ContactRecord>> {
  const response = await apiRequest<ContactDetailResponse>(`/contacts/${contactId}`, {
    authToken,
    workspaceId,
  });

  return {
    data: mapContact(response.data),
  };
}

export async function createContact(
  authToken: string,
  workspaceId: string,
  input: ContactMutationInput
): Promise<ContactRecord> {
  const response = await apiRequest<ContactDetailResponse>("/contacts", {
    authToken,
    body: JSON.stringify(mapContactInput(input)),
    method: "POST",
    workspaceId,
  });

  return mapContact(response.data);
}

export async function updateContact(
  authToken: string,
  workspaceId: string,
  contactId: string,
  input: ContactMutationInput
): Promise<ContactRecord> {
  const response = await apiRequest<ContactDetailResponse>(`/contacts/${contactId}`, {
    authToken,
    body: JSON.stringify(mapContactInput(input)),
    method: "PATCH",
    workspaceId,
  });

  return mapContact(response.data);
}

export async function deleteContact(
  authToken: string,
  workspaceId: string,
  contactId: string
): Promise<void> {
  await apiRequest<null>(`/contacts/${contactId}`, {
    authToken,
    method: "DELETE",
    workspaceId,
  });
}

export async function listVenues(
  authToken: string,
  workspaceId: string,
  filters: VenueFilters = {}
): Promise<DirectoryCollection<VenueRecord>> {
  const response = await apiRequest<VenueCollectionResponse>("/venues", {
    authToken,
    query: {
      search: filters.search?.trim() || undefined,
      status: filters.status || undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapVenue),
  };
}

export async function getVenue(
  authToken: string,
  workspaceId: string,
  venueId: string
): Promise<DirectoryDetail<VenueRecord>> {
  const response = await apiRequest<VenueDetailResponse>(`/venues/${venueId}`, {
    authToken,
    workspaceId,
  });

  return {
    data: mapVenue(response.data),
  };
}

export async function createVenue(
  authToken: string,
  workspaceId: string,
  input: VenueMutationInput
): Promise<VenueRecord> {
  const response = await apiRequest<VenueDetailResponse>("/venues", {
    authToken,
    body: JSON.stringify(mapVenueInput(input)),
    method: "POST",
    workspaceId,
  });

  return mapVenue(response.data);
}

export async function updateVenue(
  authToken: string,
  workspaceId: string,
  venueId: string,
  input: VenueMutationInput
): Promise<VenueRecord> {
  const response = await apiRequest<VenueDetailResponse>(`/venues/${venueId}`, {
    authToken,
    body: JSON.stringify(mapVenueInput(input)),
    method: "PATCH",
    workspaceId,
  });

  return mapVenue(response.data);
}

export async function deleteVenue(
  authToken: string,
  workspaceId: string,
  venueId: string
): Promise<void> {
  await apiRequest<null>(`/venues/${venueId}`, {
    authToken,
    method: "DELETE",
    workspaceId,
  });
}

function mapClientSummary(client: ApiClientSummary): ClientSummary {
  return {
    id: client.id,
    name: client.name,
    companyName: client.company_name,
    email: client.email,
    phone: client.phone,
    status: client.status,
  };
}

function mapContactSummary(contact: ApiContactSummary): ContactSummary {
  return {
    id: contact.id,
    clientId: contact.client_id,
    displayName: contact.display_name,
    fullName: contact.full_name,
    email: contact.email,
    phone: contact.phone,
    jobTitle: contact.job_title,
    contactType: contact.contact_type,
    isPrimary: contact.is_primary,
  };
}

function mapVenueSummary(venue: ApiVenueSummary): VenueSummary {
  return {
    id: venue.id,
    name: venue.name,
    city: venue.city,
    state: venue.state,
    timezone: venue.timezone,
    status: venue.status,
  };
}

function mapClient(client: ApiClientRecord): ClientRecord {
  return {
    id: client.id,
    name: client.name,
    companyName: client.company_name,
    email: client.email,
    phone: client.phone,
    website: client.website,
    taxId: client.tax_id,
    addressLine1: client.address_line_1,
    addressLine2: client.address_line_2,
    city: client.city,
    state: client.state,
    postalCode: client.postal_code,
    countryCode: client.country_code,
    status: client.status,
    notes: client.notes,
    contactsCount: client.contacts_count ?? null,
    primaryContact: client.primary_contact ? mapContactSummary(client.primary_contact) : null,
    contacts: (client.contacts ?? []).map(mapContactSummary),
    createdAt: client.created_at,
    updatedAt: client.updated_at,
  };
}

function mapContact(contact: ApiContactRecord): ContactRecord {
  return {
    id: contact.id,
    clientId: contact.client_id,
    firstName: contact.first_name,
    lastName: contact.last_name,
    displayName: contact.display_name,
    fullName: contact.full_name,
    email: contact.email,
    phone: contact.phone,
    jobTitle: contact.job_title,
    contactType: contact.contact_type,
    isPrimary: contact.is_primary,
    notes: contact.notes,
    client: contact.client ? mapClientSummary(contact.client) : null,
    createdAt: contact.created_at,
    updatedAt: contact.updated_at,
  };
}

function mapVenue(venue: ApiVenueRecord): VenueRecord {
  return {
    id: venue.id,
    name: venue.name,
    addressLine1: venue.address_line_1,
    addressLine2: venue.address_line_2,
    city: venue.city,
    state: venue.state,
    postalCode: venue.postal_code,
    countryCode: venue.country_code,
    latitude: venue.latitude,
    longitude: venue.longitude,
    timezone: venue.timezone,
    contactName: venue.contact_name,
    contactEmail: venue.contact_email,
    contactPhone: venue.contact_phone,
    capacity: venue.capacity,
    accessInstructions: venue.access_instructions,
    parkingNotes: venue.parking_notes,
    loadingNotes: venue.loading_notes,
    kitchenNotes: venue.kitchen_notes,
    notes: venue.notes,
    status: venue.status,
    createdAt: venue.created_at,
    updatedAt: venue.updated_at,
  };
}

function mapClientInput(input: ClientMutationInput) {
  return {
    name: input.name,
    company_name: input.companyName,
    email: input.email,
    phone: input.phone,
    website: input.website,
    tax_id: input.taxId,
    address_line_1: input.addressLine1,
    address_line_2: input.addressLine2,
    city: input.city,
    state: input.state,
    postal_code: input.postalCode,
    country_code: input.countryCode,
    status: input.status,
    notes: input.notes,
  };
}

function mapContactInput(input: ContactMutationInput) {
  return {
    client_id: input.clientId,
    first_name: input.firstName,
    last_name: input.lastName,
    display_name: input.displayName,
    email: input.email,
    phone: input.phone,
    job_title: input.jobTitle,
    contact_type: input.contactType,
    is_primary: input.isPrimary,
    notes: input.notes,
  };
}

function mapVenueInput(input: VenueMutationInput) {
  return {
    name: input.name,
    address_line_1: input.addressLine1,
    address_line_2: input.addressLine2,
    city: input.city,
    state: input.state,
    postal_code: input.postalCode,
    country_code: input.countryCode,
    latitude: input.latitude,
    longitude: input.longitude,
    timezone: input.timezone,
    contact_name: input.contactName,
    contact_email: input.contactEmail,
    contact_phone: input.contactPhone,
    capacity: input.capacity,
    access_instructions: input.accessInstructions,
    parking_notes: input.parkingNotes,
    loading_notes: input.loadingNotes,
    kitchen_notes: input.kitchenNotes,
    notes: input.notes,
    status: input.status,
  };
}

export { mapClientSummary, mapContactSummary, mapVenueSummary };

import { EntityPickerOption } from "@/components/primitives/entity-picker";
import type {
  EventClientValue,
  EventContactValue,
  EventVenueValue,
} from "@/features/events";
import type {
  ClientRecord,
  ClientSummary,
  ContactRecord,
  ContactSummary,
  VenueRecord,
} from "@/features/directory/types";

function summaryToContactValue(
  contact: ContactSummary | null | undefined,
  client?: ClientSummary | null
): EventContactValue | null {
  if (!contact) {
    return null;
  }

  return {
    email: contact.email,
    id: contact.id,
    name: contact.displayName?.trim() || contact.fullName.trim(),
    organization: client?.companyName ?? client?.name ?? null,
    phone: contact.phone,
    role: contact.contactType,
    title: contact.jobTitle,
  };
}

export function clientToCardValue(client: ClientRecord): EventClientValue {
  return {
    company: client.companyName,
    contact: summaryToContactValue(client.primaryContact, {
      id: client.id,
      name: client.name,
      companyName: client.companyName,
      email: client.email,
      phone: client.phone,
      status: client.status,
    }),
    email: client.email,
    id: client.id,
    metadata: [client.city, client.state].filter(Boolean).join(", ") || client.status || null,
    name: client.name,
    organization: client.companyName,
    phone: client.phone,
  };
}

export function contactToCardValue(contact: ContactRecord): EventContactValue {
  return {
    email: contact.email,
    id: contact.id,
    name: contact.displayName?.trim() || contact.fullName.trim(),
    organization: contact.client?.companyName ?? contact.client?.name ?? null,
    phone: contact.phone,
    role: contact.contactType,
    title: contact.jobTitle,
  };
}

export function venueToCardValue(venue: VenueRecord): EventVenueValue {
  return {
    address: {
      addressLine1: venue.addressLine1,
      addressLine2: venue.addressLine2,
      city: venue.city,
      country: venue.countryCode,
      postalCode: venue.postalCode,
      region: venue.state,
    },
    contact:
      venue.contactName || venue.contactEmail || venue.contactPhone
        ? {
            email: venue.contactEmail,
            name: venue.contactName,
            phone: venue.contactPhone,
          }
        : null,
    id: venue.id,
    name: venue.name,
    notes: venue.notes,
    summary: [venue.city, venue.state, venue.timezone].filter(Boolean).join(" · ") || null,
  };
}

export function clientToPickerOption(
  client: ClientRecord
): EntityPickerOption<string> {
  return {
    label: client.name,
    metadata:
      [client.companyName, client.email, client.phone]
        .filter(Boolean)
        .join(" · ") || undefined,
    value: client.id,
  };
}

export function contactToPickerOption(
  contact: ContactRecord
): EntityPickerOption<string> {
  return {
    label: contact.displayName?.trim() || contact.fullName.trim(),
    metadata:
      [
        contact.client?.companyName ?? contact.client?.name ?? null,
        contact.jobTitle,
        contact.email,
      ]
        .filter(Boolean)
        .join(" · ") || undefined,
    value: contact.id,
  };
}

export function venueToPickerOption(
  venue: VenueRecord
): EntityPickerOption<string> {
  return {
    label: venue.name,
    metadata:
      [venue.city, venue.state, venue.timezone].filter(Boolean).join(" · ") || undefined,
    value: venue.id,
  };
}

export function formatAddressLines(
  addressLine1?: string | null,
  addressLine2?: string | null,
  city?: string | null,
  state?: string | null,
  postalCode?: string | null,
  countryCode?: string | null
) {
  const locality = [city, state, postalCode].filter(Boolean).join(", ");

  return [addressLine1, addressLine2, locality || null, countryCode]
    .filter(Boolean)
    .map((value) => value as string);
}

export function formatClientLocation(client: ClientRecord) {
  return [client.city, client.state, client.countryCode].filter(Boolean).join(", ");
}

export function formatVenueLocation(venue: VenueRecord) {
  return [venue.city, venue.state, venue.countryCode].filter(Boolean).join(", ");
}

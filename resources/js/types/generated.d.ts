declare namespace App {
namespace Data {
export type ArchivedTenantData = {
slug: string,
name: string,
deleted_at: string | null,
};
export type CategoryData = {
id: number,
name: string,
description: string | null,
created_at: string,
creator: string | null,
};
export type CustomerData = {
id: number,
name: string,
contact_person: string | null,
email: string | null,
phone: string | null,
tin: string | null,
registration_no: string | null,
sst_registration_no: string | null,
address: string | null,
city: string | null,
postcode: string | null,
state_code: string | null,
country_code: App.Enums.Country | null,
notes: string | null,
created_at: string,
creator: string | null,
};
export type SupplierData = {
id: number,
name: string,
contact_person: string | null,
email: string | null,
tax_id: string | null,
phone: string | null,
address: string | null,
notes: string | null,
created_at: string,
creator: string | null,
};
export type TenantData = {
slug: string,
name: string,
created_at: string,
};
}
namespace Enums {
export type Country = 'MY' | 'SG';
}
}

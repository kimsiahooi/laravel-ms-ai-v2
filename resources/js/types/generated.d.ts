declare namespace App {
namespace Data {
export type ArchivedTenantData = {
slug: string,
name: string,
deleted_at: string | null,
};
export type TenantData = {
slug: string,
name: string,
created_at: string,
};
}
}

declare namespace App {
namespace Data {
export type ArchivedTenantData = {
slug: string,
name: string,
deleted_at: string | null,
};
export type BomItemData = {
id: number,
raw_material_id: number,
name: string,
quantity: string,
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
export type LocationData = {
id: number,
name: string,
code: string | null,
address: string | null,
warehouses: string[],
warehouse_count: number,
created_at: string,
creator: string | null,
};
export type OptionData = {
id: number,
name: string,
};
export type ProductData = {
id: number,
name: string,
sku: string,
barcode: string | null,
description: string | null,
category_id: number | null,
category: string | null,
supplier_id: number | null,
supplier: string | null,
unit: App.Enums.Unit,
thumb_url: string | null,
bom: App.Data.BomItemData[],
created_at: string,
creator: string | null,
};
export type RawMaterialData = {
id: number,
name: string,
sku: string,
barcode: string | null,
unit: App.Enums.Unit,
bom_products: string[],
bom_product_count: number,
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
export type WarehouseData = {
id: number,
location_id: number,
location: string,
name: string,
code: string | null,
address: string | null,
created_at: string,
creator: string | null,
};
}
namespace Enums {
export type Country = 'MY' | 'SG';
export type Dimension = 'mass' | 'volume' | 'length' | 'count';
export type StockMovementReason = 'adjustment' | 'stock_take' | 'transfer_in' | 'transfer_out' | 'purchase_receipt' | 'purchase_return' | 'sales_fulfillment' | 'sales_return' | 'production_consume' | 'production_output';
export type Unit = 'g' | 'kg' | 't' | 'ml' | 'l' | 'mm' | 'cm' | 'm' | 'pcs' | 'box' | 'roll' | 'sheet' | 'pair' | 'set';
}
}

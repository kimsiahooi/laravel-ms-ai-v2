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
export type BusinessSettingsData = {
base_currency: string,
currencies: string[],
tax_rate: string,
tax_label: string,
purchase_order_prefix: string,
purchase_return_prefix: string,
sales_order_prefix: string,
sales_return_prefix: string,
number_reset: App.Enums.NumberReset,
financial_year_start_month: number,
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
export type PurchaseOrderData = {
id: number,
number: string,
supplier: string | null,
supplier_id: number | null,
status: App.Enums.PurchaseOrderStatus,
currency: string,
exchange_rate: string,
tax_rate: string,
subtotal: string,
discount_total: string,
tax_total: string,
total: string,
notes: string | null,
expected_date: string | null,
created_by: string | null,
received_by: string | null,
received_at: string | null,
received_warehouse: string | null,
line_count: number,
created_at: string,
};
export type PurchaseOrderItemData = {
id: number,
item: string,
name: string | null,
sku: string | null,
unit: App.Enums.Unit | null,
quantity: string,
unit_cost: string,
discount_type: App.Enums.DiscountType,
discount_value: string,
taxable: boolean,
line_total: string,
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
export type StockItemOptionData = {
value: string,
name: string,
sku: string,
type: App.Enums.StockItemType,
default_amount: string | null,
};
export type StockMovementData = {
id: number,
warehouse: string,
site: string,
item: string | null,
item_sku: string | null,
item_type: App.Enums.StockItemType,
quantity: string,
reason: App.Enums.StockMovementReason,
user: string | null,
notes: string | null,
source_type: App.Enums.MovementSource | null,
source_id: number | null,
created_at: string,
};
export type StockOnHandData = {
on_hand: string,
unit: App.Enums.Unit,
};
export type StockTakeData = {
id: number,
warehouse: string,
site: string,
status: App.Enums.StockTakeStatus,
line_count: number,
counted_count: number,
variance_count: number,
notes: string | null,
created_by: string | null,
posted_by: string | null,
posted_at: string | null,
created_at: string,
};
export type StockTakeItemData = {
id: number,
item: string,
name: string | null,
sku: string | null,
type: App.Enums.StockItemType,
unit: App.Enums.Unit | null,
system_quantity: string,
counted_quantity: string | null,
applied_delta: string | null,
};
export type StockTransferData = {
id: number,
item: string | null,
item_sku: string | null,
item_type: App.Enums.StockItemType,
from_warehouse: string,
from_site: string,
to_warehouse: string,
to_site: string,
quantity: string,
user: string | null,
notes: string | null,
created_at: string,
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
needs_reorder: number,
};
export type WarehouseItemData = {
item: string,
name: string,
sku: string,
type: App.Enums.StockItemType,
unit: App.Enums.Unit,
on_hand: string,
min_stock: string | null,
needs_reorder: boolean,
};
export type WarehouseOptionData = {
id: number,
name: string,
site: string,
};
}
namespace Enums {
export type Country = 'MY' | 'SG';
export type Dimension = 'mass' | 'volume' | 'length' | 'count';
export type DiscountType = 'none' | 'percent' | 'amount';
export type DocumentType = 'purchase_order' | 'purchase_return' | 'sales_order' | 'sales_return';
export type MovementSource = 'purchase_order' | 'stock_take' | 'stock_transfer';
export type NumberReset = 'yearly' | 'never';
export type PurchaseOrderStatus = 'pending' | 'received' | 'cancelled';
export type StockItemType = 'product' | 'raw_material';
export type StockMovementReason = 'adjustment' | 'stock_take' | 'transfer_in' | 'transfer_out' | 'purchase_receipt' | 'purchase_return' | 'sales_fulfillment' | 'sales_return' | 'production_consume' | 'production_output';
export type StockTakeStatus = 'draft' | 'posted' | 'cancelled';
export type TableKey = 'admin-tenants' | 'admin-tenants-trashed' | 'categories' | 'customers' | 'locations' | 'products' | 'purchase-orders' | 'raw-materials' | 'stock-movements' | 'stock-takes' | 'stock-transfers' | 'suppliers' | 'warehouse-items' | 'warehouses';
export type Unit = 'g' | 'kg' | 't' | 'ml' | 'l' | 'mm' | 'cm' | 'm' | 'pcs' | 'box' | 'roll' | 'sheet' | 'pair' | 'set';
}
}

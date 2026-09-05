<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Unit;
use App\Models\BomItem;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * A small, coherent workspace to develop against — a furniture maker.
 *
 * **Not part of provisioning.** {@see TenantDatabaseSeeder} runs on every new workspace
 * and seeds only permissions and roles; a real customer does not want sample products.
 * This one is asked for by hand:
 *
 *     php artisan tenants:seed --class=DemoCatalogSeeder
 *
 * **Additive and idempotent — it truncates nothing.** Every row is `firstOrCreate`d on
 * the column that is already unique for it, so running it twice changes nothing and
 * running it over a workspace somebody has been clicking around in leaves their rows
 * alone. That is deliberate: this seeder is meant to be safe to run against the
 * workspace you are currently looking at.
 *
 * The data is fixed rather than faked. Faker gives a different catalog every run, which
 * makes "did that number change because of my code or because of the seed?" a question
 * you have to stop and answer; and a product called "Beau Norris" tells you nothing
 * about whether a bill of materials rendered correctly.
 *
 * Sizes are chosen for what they exercise, not for realism: a product with no bill, one
 * with a single line, and one with several; a material used by two products, and one
 * used by none; quantities with four decimal places, because that is the column's scale
 * and the place a rounding bug would show.
 */
final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->categories();
        $suppliers = $this->suppliers();
        $materials = $this->materials($suppliers);
        $this->products($categories, $suppliers, $materials);
        $this->sites();
    }

    /** @return array<string, Category> */
    private function categories(): array
    {
        $rows = [
            'Seating' => 'Chairs, stools and benches.',
            'Tables' => 'Dining, coffee and work surfaces.',
            'Storage' => 'Shelving, cabinets and boxes.',
        ];

        $made = [];

        foreach ($rows as $name => $description) {
            $made[$name] = Category::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        return $made;
    }

    /** @return array<string, Supplier> */
    private function suppliers(): array
    {
        $rows = [
            'Hardwood Supplies Sdn Bhd' => ['contact_person' => 'Lim Wei Ming', 'email' => 'sales@hardwood.example'],
            'Klang Fastener Co.' => ['contact_person' => 'Nurul Aina', 'email' => 'orders@klangfastener.example'],
            'Selangor Finishes' => ['contact_person' => 'Tan Boon Huat', 'email' => 'hello@sgorfinishes.example'],
        ];

        $made = [];

        foreach ($rows as $name => $attributes) {
            $made[$name] = Supplier::firstOrCreate(['name' => $name], $attributes);
        }

        return $made;
    }

    /**
     * @param  array<string, Supplier>  $suppliers
     * @return array<string, RawMaterial>
     */
    private function materials(array $suppliers): array
    {
        $rows = [
            // sku => [name, unit]
            'RM-OAK' => ['Oak board 18mm', Unit::Metre],
            'RM-PINE' => ['Pine board 18mm', Unit::Metre],
            'RM-SCREW' => ['Wood screw 40mm', Unit::Piece],
            'RM-GLUE' => ['Wood glue', Unit::Litre],
            'RM-LACQUER' => ['Clear lacquer', Unit::Litre],
            // Deliberately used by nothing: the material filter must not offer it, and
            // deleting it must be allowed.
            'RM-FELT' => ['Felt pad', Unit::Piece],
        ];

        $made = [];

        foreach ($rows as $sku => [$name, $unit]) {
            $made[$sku] = RawMaterial::firstOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'unit' => $unit],
            );
        }

        return $made;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Supplier>  $suppliers
     * @param  array<string, RawMaterial>  $materials
     */
    private function products(array $categories, array $suppliers, array $materials): void
    {
        $rows = [
            'P-STOOL' => [
                'name' => 'Folding step stool',
                'unit' => Unit::Piece,
                'category' => 'Seating',
                'supplier' => 'Hardwood Supplies Sdn Bhd',
                // Several lines, including one at the column's full scale.
                'bom' => ['RM-PINE' => '1.2500', 'RM-SCREW' => '12', 'RM-GLUE' => '0.0350'],
            ],
            'P-DESK' => [
                'name' => 'Oak writing desk',
                'unit' => Unit::Piece,
                'category' => 'Tables',
                'supplier' => 'Hardwood Supplies Sdn Bhd',
                'bom' => ['RM-OAK' => '3.4000', 'RM-SCREW' => '24', 'RM-GLUE' => '0.1200', 'RM-LACQUER' => '0.2500'],
            ],
            'P-SHELF' => [
                'name' => 'Wall shelf 900mm',
                'unit' => Unit::Piece,
                'category' => 'Storage',
                'supplier' => null,
                // A single line, so the singular branch of every "N materials" string
                // has something to render.
                'bom' => ['RM-PINE' => '0.9000'],
            ],
            'P-CUSHION' => [
                'name' => 'Seat cushion',
                'unit' => Unit::Piece,
                'category' => 'Seating',
                'supplier' => 'Selangor Finishes',
                // Bought in, not made: no bill at all.
                'bom' => [],
            ],
        ];

        foreach ($rows as $sku => $row) {
            $product = Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'category_id' => $categories[$row['category']]->id,
                    'supplier_id' => $row['supplier'] === null ? null : $suppliers[$row['supplier']]->id,
                ],
            );

            foreach ($row['bom'] as $materialSku => $quantity) {
                // The bill's own unique key is (product, material), so this is the same
                // additive story one level down — a quantity somebody edited by hand is
                // left as they left it.
                BomItem::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'raw_material_id' => $materials[$materialSku]->id,
                    ],
                    ['quantity' => $quantity],
                );
            }
        }
    }

    /**
     * Sites and the warehouses on them.
     *
     * Two warehouses on one site and one on another, because that is the shape the
     * site filter and the site delete guard both need in order to say anything.
     */
    private function sites(): void
    {
        $rows = [
            'HQ' => [
                'name' => 'Shah Alam works',
                'address' => 'Lot 5, Jalan Utas 15/7, Seksyen 15, 40200 Shah Alam',
                'warehouses' => [
                    'HQ-RAW' => ['Raw material store', 'Building A'],
                    'HQ-FG' => ['Finished goods', 'Building B'],
                ],
            ],
            'PEN' => [
                'name' => 'Penang branch',
                'address' => '12 Lebuh Pantai, 10300 George Town, Pulau Pinang',
                'warehouses' => [
                    'PEN-MAIN' => ['Branch store', null],
                ],
            ],
        ];

        foreach ($rows as $code => $row) {
            $site = Location::firstOrCreate(
                ['code' => $code],
                ['name' => $row['name'], 'address' => $row['address']],
            );

            foreach ($row['warehouses'] as $warehouseCode => [$name, $address]) {
                Warehouse::firstOrCreate(
                    ['code' => $warehouseCode],
                    ['location_id' => $site->id, 'name' => $name, 'address' => $address],
                );
            }
        }
    }
}

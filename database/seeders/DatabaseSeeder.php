<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles for staff guard
        $roles = [
            'super_admin' => 'Full system access',
            'admin'       => 'Admin access',
            'manager'     => 'Store manager',
            'cashier'     => 'POS and sales',
            'support'     => 'Customer support',
        ];

        $permissions = [
            'view_dashboard', 'manage_products', 'manage_orders',
            'manage_staff', 'manage_transactions', 'view_reports',
            'manage_chat', 'manage_settings', 'use_pos',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'staff']);
        }

        // Customer role on web guard
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'staff']);
        $superAdmin->syncPermissions(Permission::where('guard_name', 'staff')->get());

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'staff']);
        $adminRole->syncPermissions(['view_dashboard', 'manage_products', 'manage_orders', 'manage_staff', 'manage_transactions', 'view_reports', 'manage_chat', 'use_pos']);

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'staff']);
        $managerRole->syncPermissions(['view_dashboard', 'manage_products', 'manage_orders', 'view_reports', 'use_pos']);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'staff']);
        $cashierRole->syncPermissions(['use_pos', 'view_dashboard']);

        $supportRole = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'staff']);
        $supportRole->syncPermissions(['manage_chat', 'view_dashboard']);

        // Super admin staff
        $owner = Staff::firstOrCreate(['email' => 'admin@leviyah.com'], [
            'name'        => 'Leviyah Admin',
            'password'    => Hash::make('Leviyah@2024'),
            'phone'       => '+2349057782627',
            'employee_id' => 'EMP-OWNER',
            'position'    => 'Owner',
            'department'  => 'Management',
            'qr_code'     => Str::uuid()->toString(),
            'status'      => 'active',
        ]);
        $owner->assignRole('super_admin');

        // Categories
        $categories = [
            ['name' => 'Hair',          'description' => 'Premium hair extensions and wigs'],
            ['name' => 'Supplements',   'description' => 'Beauty supplements, vitamins, and wellness products'],
            ['name' => 'Lip Essentials','description' => 'Lipsticks, lip gloss, lip liners, and lip care'],
            ['name' => 'Bags',          'description' => 'Fashion bags and accessories'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => Str::slug($cat['name'])], [
                'name'        => $cat['name'],
                'description' => $cat['description'],
                'is_active'   => true,
                'sort_order'  => 0,
            ]);
        }

        // Sample products
        $hairCategory = Category::where('slug', 'hair')->first();

        $sampleProducts = [
            [
                'category' => 'hair',
                'name'     => 'Brazilian Body Wave Bundle',
                'short_description' => '100% virgin Brazilian hair, silky smooth body wave texture.',
                'base_price' => 45000,
                'has_variants' => true,
                'product_type' => 'variable',
                'is_featured' => true,
            ],
            [
                'category' => 'hair',
                'name'     => 'Peruvian Straight Bundle',
                'short_description' => 'Straight, tangle-free Peruvian virgin hair.',
                'base_price' => 40000,
                'has_variants' => true,
                'product_type' => 'variable',
                'is_featured' => true,
            ],
            [
                'category' => 'supplements',
                'name'     => 'Collagen Beauty Gummies',
                'short_description' => 'Marine collagen gummies for glowing skin and stronger hair.',
                'base_price' => 8500,
                'is_featured' => true,
            ],
            [
                'category' => 'supplements',
                'name'     => 'Biotin Hair Growth Capsules',
                'short_description' => 'High-strength biotin supplement for hair growth and thickness.',
                'base_price' => 6500,
            ],
            [
                'category' => 'supplements',
                'name'     => 'Vitamin C Glow Serum Drink',
                'short_description' => 'Drinkable vitamin C serum for brighter, even-toned skin.',
                'base_price' => 5500,
                'is_featured' => true,
            ],
            [
                'category' => 'supplements',
                'name'     => 'Hyaluronic Acid Hydration Capsules',
                'short_description' => 'Skin hydration from within — plumper skin in 4 weeks.',
                'base_price' => 7000,
            ],
            [
                'category' => 'supplements',
                'name'     => 'Glutathione Skin Brightening Pills',
                'short_description' => 'Glutathione + vitamin E for skin brightening and antioxidant support.',
                'base_price' => 9500,
            ],
            [
                'category' => 'supplements',
                'name'     => 'Beauty Multivitamin Pack',
                'short_description' => 'Complete daily beauty vitamin — hair, skin, nails.',
                'base_price' => 5000,
            ],
            [
                'category' => 'lip-essentials',
                'name'     => 'Velvet Matte Lipstick',
                'short_description' => 'Long-lasting velvet matte finish lipstick.',
                'base_price' => 3500,
                'has_variants' => true,
                'product_type' => 'variable',
                'is_featured' => true,
            ],
            [
                'category' => 'bags',
                'name'     => 'Leviyah Signature Tote Bag',
                'short_description' => 'Premium leather tote bag with Leviyah branding.',
                'base_price' => 25000,
                'is_featured' => true,
            ],
        ];

        $hairLengths = ['10 inch', '12 inch', '14 inch', '16 inch', '18 inch', '20 inch', '22 inch', '24 inch'];
        $hairColors  = [
            ['name' => 'Natural Black', 'hex' => '#1a1a1a'],
            ['name' => '1B Natural Black', 'hex' => '#2d2d2d'],
            ['name' => 'Dark Brown', 'hex' => '#4a2c1a'],
            ['name' => '613 Blonde', 'hex' => '#f5e6a3'],
            ['name' => 'Ombre Brown', 'hex' => '#8B4513'],
        ];
        $lipColors   = [
            ['name' => 'Ruby Red', 'hex' => '#9b111e'],
            ['name' => 'Nude Beige', 'hex' => '#d4a574'],
            ['name' => 'Dusty Rose', 'hex' => '#dcae96'],
            ['name' => 'Berry', 'hex' => '#6d2b42'],
            ['name' => 'Coral', 'hex' => '#ff7f50'],
        ];

        foreach ($sampleProducts as $p) {
            $catSlug = $p['category'];
            $cat     = Category::where('slug', $catSlug)->first();
            if (!$cat) continue;

            unset($p['category']);
            $p['category_id']    = $cat->id;
            $p['slug']           = Str::slug($p['name']) . '-' . Str::random(4);
            $p['stock_quantity'] = 50;
            $p['is_active']      = true;
            $p['is_featured']    = $p['is_featured'] ?? false;
            $p['has_variants']   = $p['has_variants'] ?? false;
            $p['product_type']   = $p['product_type'] ?? 'simple';

            $product = Product::firstOrCreate(['slug' => $p['slug']], $p);

            if ($product->wasRecentlyCreated && $product->has_variants) {
                if (in_array($cat->slug, ['hair'])) {
                    foreach ($hairColors as $color) {
                        foreach ($hairLengths as $length) {
                            $priceAdj = match(true) {
                                str_contains($length, '20') || str_contains($length, '22') || str_contains($length, '24') => 10000,
                                str_contains($length, '16') || str_contains($length, '18') => 5000,
                                default => 0,
                            };
                            $product->variants()->create([
                                'color'          => $color['name'],
                                'color_hex'      => $color['hex'],
                                'length'         => $length,
                                'price'          => $product->base_price + $priceAdj,
                                'stock_quantity' => 10,
                            ]);
                        }
                    }
                } elseif ($cat->slug === 'lip-essentials') {
                    foreach ($lipColors as $color) {
                        $product->variants()->create([
                            'color'          => $color['name'],
                            'color_hex'      => $color['hex'],
                            'price'          => $product->base_price,
                            'stock_quantity' => 20,
                        ]);
                    }
                }
            }
        }

        // Assign product images in order of creation
        $imageFiles = [
            'products/1-brazilian-body-wave.jpg',
            'products/2-peruvian-straight.jpg',
            'products/3-face-serum.jpg',
            'products/4-face-cream.jpg',
            'products/5-body-oil.jpg',
            'products/6-body-scrub.jpg',
            'products/7-shower-gel.jpg',
            'products/8-shea-soap.jpg',
            'products/9-lipstick.jpg',
            'products/10-tote-bag.jpg',
        ];

        \App\Models\Product::orderBy('id')->get()->each(function ($product, $index) use ($imageFiles) {
            $path = $imageFiles[$index] ?? null;
            if (!$path) return;
            $product->update(['thumbnail' => $path]);
            \App\Models\ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'sort_order' => 0],
                ['url' => $path, 'is_primary' => true, 'alt' => $product->name]
            );
        });
    }
}

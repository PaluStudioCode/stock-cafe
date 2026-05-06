<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (
            DB::table('users')->where('email', 'owner@cafestock.test')->exists()
            && DB::table('stock_movements')->where('id', 66)->exists()
        ) {
            return;
        }

        $now = now();

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Owner Cafe', 'email' => 'owner@cafestock.test', 'password' => Hash::make('password'), 'role' => 'owner', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Rani Inventory', 'email' => 'inventory@cafestock.test', 'password' => Hash::make('password'), 'role' => 'inventory_staff', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Dimas Barista', 'email' => 'dimas@cafestock.test', 'password' => Hash::make('password'), 'role' => 'barista', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Naya Barista', 'email' => 'naya@cafestock.test', 'password' => Hash::make('password'), 'role' => 'barista', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Akun Nonaktif', 'email' => 'inactive@cafestock.test', 'password' => Hash::make('password'), 'role' => 'barista', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('ingredient_categories')->insert($this->stamp([
            ['id' => 1, 'name' => 'Kopi', 'description' => 'Biji kopi dan bahan utama espresso'],
            ['id' => 2, 'name' => 'Susu dan Dairy', 'description' => 'Susu UHT, fresh milk, dan produk dairy'],
            ['id' => 3, 'name' => 'Pemanis', 'description' => 'Gula pasir, gula aren, dan pemanis lain'],
            ['id' => 4, 'name' => 'Sirup dan Flavor', 'description' => 'Sirup perasa untuk menu minuman'],
            ['id' => 5, 'name' => 'Powder', 'description' => 'Bubuk cokelat, matcha, dan bahan minuman bubuk'],
            ['id' => 6, 'name' => 'Kemasan', 'description' => 'Cup, lid, dan kemasan penyajian'],
            ['id' => 7, 'name' => 'Bahan Pendukung', 'description' => 'Es batu, snack frozen, dan bahan operasional menu'],
        ], $now));

        DB::table('units')->insert($this->stamp([
            ['id' => 1, 'name' => 'Gram', 'symbol' => 'g'],
            ['id' => 2, 'name' => 'Milliliter', 'symbol' => 'ml'],
            ['id' => 3, 'name' => 'Pieces', 'symbol' => 'pcs'],
            ['id' => 4, 'name' => 'Kilogram', 'symbol' => 'kg'],
            ['id' => 5, 'name' => 'Liter', 'symbol' => 'l'],
        ], $now));

        DB::table('suppliers')->insert($this->stamp([
            ['id' => 1, 'name' => 'Roastery Timur Makassar', 'phone' => '081234560101', 'address' => 'Jl. Pengayoman No. 18, Makassar', 'notes' => 'Supplier biji kopi arabica dan robusta', 'is_active' => true],
            ['id' => 2, 'name' => 'Dairy Fresh Sulawesi', 'phone' => '081234560102', 'address' => 'Jl. Veteran Selatan No. 42, Makassar', 'notes' => 'Supplier susu dan dairy', 'is_active' => true],
            ['id' => 3, 'name' => 'Bahan Rasa Nusantara', 'phone' => '081234560103', 'address' => 'Jl. Sultan Alauddin No. 88, Makassar', 'notes' => 'Supplier gula, sirup, dan powder', 'is_active' => true],
            ['id' => 4, 'name' => 'Kemasan Kopi Mandiri', 'phone' => '081234560104', 'address' => 'Jl. Perintis Kemerdekaan No. 11, Makassar', 'notes' => 'Supplier cup, lid, dan kemasan', 'is_active' => true],
            ['id' => 5, 'name' => 'Frozen Pastry House', 'phone' => '081234560105', 'address' => 'Jl. Toddopuli Raya No. 22, Makassar', 'notes' => 'Supplier pastry frozen', 'is_active' => true],
        ], $now));

        DB::table('ingredients')->insert($this->stamp([
            [1,1,1,1,'ING-20260501-0001','Arabica Beans',160,5200,1000,2500,true],
            [2,1,1,1,'ING-20260501-0002','Robusta Beans',95,2200,800,1800,true],
            [3,2,2,2,'ING-20260501-0003','Susu UHT',18,7920,5000,9000,true],
            [4,2,2,2,'ING-20260501-0004','Fresh Milk',22,1900,3000,6000,true],
            [5,3,2,3,'ING-20260501-0005','Gula Aren Cair',35,1700,1500,3000,true],
            [6,3,1,3,'ING-20260501-0006','Gula Pasir',15,5130,2000,4000,true],
            [7,4,2,3,'ING-20260501-0007','Sirup Vanilla',48,1250,1000,2000,true],
            [8,4,2,3,'ING-20260501-0008','Sirup Caramel',50,0,800,1800,true],
            [9,5,1,3,'ING-20260501-0009','Cokelat Bubuk',80,1560,700,1600,true],
            [10,5,1,3,'ING-20260501-0010','Matcha Powder',180,650,700,1500,true],
            [11,7,1,2,'ING-20260501-0011','Es Batu',2,6450,5000,10000,true],
            [12,6,3,4,'ING-20260501-0012','Cup 16 oz',800,308,100,250,true],
            [13,6,3,4,'ING-20260501-0013','Cup 12 oz',650,240,100,220,true],
            [14,6,3,4,'ING-20260501-0014','Lid Cup',250,318,120,300,true],
            [15,7,3,5,'ING-20260501-0015','Croissant Frozen',9000,17,10,25,true],
        ], $now, fn ($r) => [
            'id' => $r[0], 'ingredient_category_id' => $r[1], 'unit_id' => $r[2], 'primary_supplier_id' => $r[3],
            'code' => $r[4], 'name' => $r[5], 'last_unit_cost' => $r[6], 'current_stock' => $r[7],
            'minimum_stock' => $r[8], 'reorder_level' => $r[9], 'is_active' => $r[10],
        ]));

        DB::table('menu_items')->insert($this->stamp([
            [1,'MENU-20260501-0001','Es Kopi Susu Aren','Coffee',25000,true],
            [2,'MENU-20260501-0002','Americano Iced','Coffee',22000,true],
            [3,'MENU-20260501-0003','Cafe Latte Hot','Coffee',28000,true],
            [4,'MENU-20260501-0004','Matcha Latte Iced','Non Coffee',30000,true],
            [5,'MENU-20260501-0005','Chocolate Iced','Non Coffee',28000,true],
            [6,'MENU-20260501-0006','Kopi Tubruk','Manual Brew',18000,true],
            [7,'MENU-20260501-0007','Croissant Butter','Pastry',24000,true],
            [8,'MENU-20260501-0008','Caramel Latte Iced','Coffee',30000,false],
        ], $now, fn ($r) => ['id' => $r[0], 'code' => $r[1], 'name' => $r[2], 'category' => $r[3], 'selling_price' => $r[4], 'is_active' => $r[5]]));

        DB::table('recipe_items')->insert($this->stamp($this->recipeRows(), $now, fn ($r) => ['id' => $r[0], 'menu_item_id' => $r[1], 'ingredient_id' => $r[2], 'quantity_per_serving' => $r[3], 'notes' => $r[4]]));

        DB::table('purchase_orders')->insert($this->stamp([
            [1,1,2,'PO-20260502-0001','2026-05-02','2026-05-02 09:15:00',445000,5000,440000,'received','Restock biji kopi dan gula'],
            [2,2,2,'PO-20260502-0002','2026-05-02','2026-05-02 10:30:00',156000,0,156000,'received','Restock susu untuk akhir pekan'],
            [3,3,2,'PO-20260503-0001','2026-05-03','2026-05-03 08:45:00',287000,2000,285000,'received','Restock sirup dan powder'],
            [4,4,2,'PO-20260503-0002','2026-05-03','2026-05-03 11:00:00',585000,0,585000,'received','Restock kemasan dan pastry'],
            [5,2,2,'PO-20260504-0001','2026-05-04',null,54000,0,54000,'draft','Draft restock susu dan es batu'],
        ], $now, fn ($r) => ['id' => $r[0], 'supplier_id' => $r[1], 'user_id' => $r[2], 'purchase_code' => $r[3], 'purchase_date' => $r[4], 'received_at' => $r[5], 'subtotal' => $r[6], 'discount' => $r[7], 'total_amount' => $r[8], 'status' => $r[9], 'notes' => $r[10]]));

        DB::table('purchase_order_items')->insert($this->stamp($this->poItemRows(), $now, fn ($r) => ['id' => $r[0], 'purchase_order_id' => $r[1], 'ingredient_id' => $r[2], 'quantity' => $r[3], 'unit_cost' => $r[4], 'subtotal' => $r[5]]));

        DB::table('production_logs')->insert($this->stamp([
            [1,1,3,'PROD-20260503-0001','2026-05-03 09:30:00',20,145300,'completed','Produksi batch pagi Es Kopi Susu Aren'],
            [2,2,3,'PROD-20260503-0002','2026-05-03 10:15:00',15,56400,'completed','Americano iced untuk rush hour'],
            [3,3,4,'PROD-20260503-0003','2026-05-03 11:00:00',10,77400,'completed','Latte hot'],
            [4,4,4,'PROD-20260503-0004','2026-05-03 13:00:00',10,75000,'completed','Matcha latte iced'],
            [5,5,3,'PROD-20260503-0005','2026-05-03 14:20:00',12,67440,'completed','Chocolate iced'],
            [6,6,3,'PROD-20260503-0006','2026-05-03 15:00:00',15,44250,'completed','Kopi tubruk'],
            [7,7,4,'PROD-20260503-0007','2026-05-03 16:10:00',8,72000,'completed','Croissant butter'],
            [8,8,3,'PROD-20260504-0001','2026-05-04 09:00:00',2,0,'cancelled','Dibatalkan sebelum stok dikurangi karena menu nonaktif'],
        ], $now, fn ($r) => ['id' => $r[0], 'menu_item_id' => $r[1], 'user_id' => $r[2], 'production_code' => $r[3], 'production_date' => $r[4], 'quantity' => $r[5], 'estimated_total_cost' => $r[6], 'status' => $r[7], 'notes' => $r[8]]));

        DB::table('production_log_items')->insert($this->stamp($this->productionItemRows(), $now, fn ($r) => ['id' => $r[0], 'production_log_id' => $r[1], 'ingredient_id' => $r[2], 'quantity_per_serving_snapshot' => $r[3], 'quantity_used' => $r[4], 'unit_cost_snapshot' => $r[5], 'estimated_cost' => $r[6]]));

        DB::table('stock_usages')->insert($this->stamp([
            [1,2,'USE-20260504-0001','2026-05-04 12:00:00','waste',98400,'completed','Bahan rusak dan tumpah saat operasional'],
            [2,2,'USE-20260504-0002','2026-05-04 15:00:00','internal_use',25000,'draft','Draft bahan untuk training barista'],
            [3,2,'USE-20260504-0003','2026-05-04 16:00:00','expired',3500,'cancelled','Dibatalkan setelah pengecekan ulang tanggal expired'],
        ], $now, fn ($r) => ['id' => $r[0], 'user_id' => $r[1], 'usage_code' => $r[2], 'usage_date' => $r[3], 'usage_type' => $r[4], 'estimated_total_cost' => $r[5], 'status' => $r[6], 'notes' => $r[7]]));

        DB::table('stock_usage_items')->insert($this->stamp([
            [1,1,4,300,22,6600,'Fresh milk tumpah'],
            [2,1,7,350,48,16800,'Botol sirup rusak'],
            [3,1,8,1500,50,75000,'Caramel bocor dan tidak layak pakai'],
            [4,2,1,100,160,16000,'Draft training latte art'],
            [5,2,3,500,18,9000,'Draft training latte art'],
            [6,3,5,100,35,3500,'Batal karena bahan masih layak'],
        ], $now, fn ($r) => ['id' => $r[0], 'stock_usage_id' => $r[1], 'ingredient_id' => $r[2], 'quantity' => $r[3], 'unit_cost_snapshot' => $r[4], 'estimated_cost' => $r[5], 'notes' => $r[6]]));

        DB::table('stock_adjustments')->insert($this->stamp([
            [1,2,1,'ADJ-20260504-0001','2026-05-04 17:00:00','approved','Selisih hasil timbang Arabica Beans saat closing stock opname','2026-05-04 17:20:00'],
            [2,2,null,'ADJ-20260505-0001','2026-05-05 08:30:00','draft','Draft stock opname kemasan pagi',null],
        ], $now, fn ($r) => ['id' => $r[0], 'user_id' => $r[1], 'approved_by' => $r[2], 'adjustment_code' => $r[3], 'adjustment_date' => $r[4], 'status' => $r[5], 'reason' => $r[6], 'approved_at' => $r[7]]));

        DB::table('stock_adjustment_items')->insert($this->stamp([
            [1,1,1,5220,5200,-20,'Selisih timbang minor'],
            [2,2,12,308,310,2,'Draft belum memengaruhi stok'],
        ], $now, fn ($r) => ['id' => $r[0], 'stock_adjustment_id' => $r[1], 'ingredient_id' => $r[2], 'system_stock' => $r[3], 'counted_stock' => $r[4], 'difference' => $r[5], 'notes' => $r[6]]));

        DB::table('stock_movements')->insert($this->stamp($this->movementRows(), $now, fn ($r) => [
            'id' => $r[0], 'ingredient_id' => $r[1], 'user_id' => $r[2], 'type' => $r[3], 'reference_type' => $r[4], 'reference_id' => $r[5],
            'quantity_in' => $r[6], 'quantity_out' => $r[7], 'stock_before' => $r[8], 'stock_after' => $r[9], 'unit_cost_snapshot' => $r[10], 'notes' => $r[11],
        ]));

        DB::table('activity_logs')->insert($this->stamp([
            [1,null,'login_failed','auth','Percobaan login gagal untuk inactive@cafestock.test','users',5,'127.0.0.1'],
            [2,1,'create','ingredients','Owner membuat data bahan Arabica Beans','ingredients',1,'127.0.0.1'],
            [3,2,'receive_purchase','purchase_orders','Rani menerima PO-20260502-0001','purchase_orders',1,'127.0.0.1'],
            [4,2,'receive_purchase','purchase_orders','Rani menerima PO-20260503-0002','purchase_orders',4,'127.0.0.1'],
            [5,3,'complete_production','production_logs','Dimas menyelesaikan PROD-20260503-0001','production_logs',1,'127.0.0.1'],
            [6,4,'complete_production','production_logs','Naya menyelesaikan PROD-20260503-0004','production_logs',4,'127.0.0.1'],
            [7,2,'complete_usage','stock_usages','Rani mencatat waste USE-20260504-0001','stock_usages',1,'127.0.0.1'],
            [8,1,'approve_adjustment','stock_adjustments','Owner menyetujui ADJ-20260504-0001','stock_adjustments',1,'127.0.0.1'],
            [10,1,'export_report','reports','Owner export laporan stok periode 2026-05-01 sampai 2026-05-04','stock_movements',null,'127.0.0.1'],
        ], $now, fn ($r) => ['id' => $r[0], 'user_id' => $r[1], 'action' => $r[2], 'module' => $r[3], 'description' => $r[4], 'reference_type' => $r[5], 'reference_id' => $r[6], 'ip_address' => $r[7]]));

        DB::table('settings')->insert($this->stamp([
            ['cafe_name','CafeStock Demo Cafe','Nama cafe'],
            ['cafe_tagline','Pantau Bahan Baku, Cegah Kehabisan Stok, Jaga Operasional Cafe.','Tagline cafe'],
            ['cafe_address','Jl. Pengayoman No. 10, Makassar','Alamat cafe'],
            ['cafe_phone','081234567890','Nomor kontak cafe'],
            ['timezone','Asia/Makassar','Timezone aplikasi'],
            ['default_minimum_stock','10','Default minimum stock bahan baru'],
            ['table_per_page','20','Jumlah data default per halaman'],
            ['report_export_formats','pdf,xlsx','Format export laporan yang didukung'],
            ['upload_max_file_size_mb','2','Batas maksimal upload file'],
        ], $now, fn ($r, $i) => ['id' => $i + 1, 'key' => $r[0], 'value' => $r[1], 'description' => $r[2]]));
    }

    private function stamp(array $rows, $now, ?callable $map = null): array
    {
        return array_map(function ($row, $index) use ($now, $map) {
            $mapped = $map ? $map($row, $index) : $row;
            return $mapped + ['created_at' => $now, 'updated_at' => $now];
        }, $rows, array_keys($rows));
    }

    private function recipeRows(): array
    {
        return [
            [1,1,1,18,'Espresso base'],[2,1,3,120,'Susu UHT'],[3,1,5,25,'Gula aren'],[4,1,11,150,'Es batu'],[5,1,12,1,'Cup 16 oz'],[6,1,14,1,'Lid cup'],
            [7,2,1,16,'Espresso base'],[8,2,11,150,'Es batu'],[9,2,13,1,'Cup 12 oz'],[10,2,14,1,'Lid cup'],
            [11,3,1,18,'Espresso base'],[12,3,4,180,'Fresh milk steamed'],[13,3,13,1,'Cup 12 oz'],[14,3,14,1,'Lid cup'],
            [15,4,10,15,'Matcha powder'],[16,4,4,150,'Fresh milk'],[17,4,6,10,'Gula pasir'],[18,4,11,150,'Es batu'],[19,4,12,1,'Cup 16 oz'],[20,4,14,1,'Lid cup'],
            [21,5,9,20,'Cokelat bubuk'],[22,5,3,140,'Susu UHT'],[23,5,6,10,'Gula pasir'],[24,5,11,150,'Es batu'],[25,5,12,1,'Cup 16 oz'],[26,5,14,1,'Lid cup'],
            [27,6,2,20,'Robusta'],[28,6,6,10,'Gula pasir'],[29,6,13,1,'Cup 12 oz'],[30,6,14,1,'Lid cup'],[31,7,15,1,'Croissant frozen'],
        ];
    }

    private function poItemRows(): array
    {
        return [
            [1,1,1,2000,160,320000],[2,1,2,1000,95,95000],[3,1,6,2000,15,30000],[4,2,3,5000,18,90000],[5,2,4,3000,22,66000],
            [6,3,5,1000,35,35000],[7,3,7,1000,48,48000],[8,3,8,1000,50,50000],[9,3,9,800,80,64000],[10,3,10,500,180,90000],
            [11,4,12,250,800,200000],[12,4,13,200,650,130000],[13,4,14,300,250,75000],[14,4,15,20,9000,180000],[15,5,11,5000,2,10000],[16,5,4,2000,22,44000],
        ];
    }

    private function productionItemRows(): array
    {
        return [
            [1,1,1,18,360,160,57600],[2,1,3,120,2400,18,43200],[3,1,5,25,500,35,17500],[4,1,11,150,3000,2,6000],[5,1,12,1,20,800,16000],[6,1,14,1,20,250,5000],
            [7,2,1,16,240,160,38400],[8,2,11,150,2250,2,4500],[9,2,13,1,15,650,9750],[10,2,14,1,15,250,3750],
            [11,3,1,18,180,160,28800],[12,3,4,180,1800,22,39600],[13,3,13,1,10,650,6500],[14,3,14,1,10,250,2500],
            [15,4,10,15,150,180,27000],[16,4,4,150,1500,22,33000],[17,4,6,10,100,15,1500],[18,4,11,150,1500,2,3000],[19,4,12,1,10,800,8000],[20,4,14,1,10,250,2500],
            [21,5,9,20,240,80,19200],[22,5,3,140,1680,18,30240],[23,5,6,10,120,15,1800],[24,5,11,150,1800,2,3600],[25,5,12,1,12,800,9600],[26,5,14,1,12,250,3000],
            [27,6,2,20,300,95,28500],[28,6,6,10,150,15,2250],[29,6,13,1,15,650,9750],[30,6,14,1,15,250,3750],[31,7,15,1,8,9000,72000],
        ];
    }

    private function movementRows(): array
    {
        return [
            [1,1,1,'opening_stock','opening_stock',null,4000,0,0,4000,160,'Saldo awal seed'],[2,2,1,'opening_stock','opening_stock',null,1500,0,0,1500,95,'Saldo awal seed'],[3,3,1,'opening_stock','opening_stock',null,7000,0,0,7000,18,'Saldo awal seed'],[4,4,1,'opening_stock','opening_stock',null,2500,0,0,2500,22,'Saldo awal seed'],[5,5,1,'opening_stock','opening_stock',null,1200,0,0,1200,35,'Saldo awal seed'],[6,6,1,'opening_stock','opening_stock',null,3500,0,0,3500,15,'Saldo awal seed'],[7,7,1,'opening_stock','opening_stock',null,600,0,0,600,48,'Saldo awal seed'],[8,8,1,'opening_stock','opening_stock',null,500,0,0,500,50,'Saldo awal seed'],[9,9,1,'opening_stock','opening_stock',null,1000,0,0,1000,80,'Saldo awal seed'],[10,10,1,'opening_stock','opening_stock',null,300,0,0,300,180,'Saldo awal seed'],[11,11,1,'opening_stock','opening_stock',null,15000,0,0,15000,2,'Saldo awal seed'],[12,12,1,'opening_stock','opening_stock',null,100,0,0,100,800,'Saldo awal seed'],[13,13,1,'opening_stock','opening_stock',null,80,0,0,80,650,'Saldo awal seed'],[14,14,1,'opening_stock','opening_stock',null,100,0,0,100,250,'Saldo awal seed'],[15,15,1,'opening_stock','opening_stock',null,5,0,0,5,9000,'Saldo awal seed'],
            [16,1,2,'purchase_receipt','purchase_orders',1,2000,0,4000,6000,160,'PO-20260502-0001'],[17,2,2,'purchase_receipt','purchase_orders',1,1000,0,1500,2500,95,'PO-20260502-0001'],[18,6,2,'purchase_receipt','purchase_orders',1,2000,0,3500,5500,15,'PO-20260502-0001'],[19,3,2,'purchase_receipt','purchase_orders',2,5000,0,7000,12000,18,'PO-20260502-0002'],[20,4,2,'purchase_receipt','purchase_orders',2,3000,0,2500,5500,22,'PO-20260502-0002'],[21,5,2,'purchase_receipt','purchase_orders',3,1000,0,1200,2200,35,'PO-20260503-0001'],[22,7,2,'purchase_receipt','purchase_orders',3,1000,0,600,1600,48,'PO-20260503-0001'],[23,8,2,'purchase_receipt','purchase_orders',3,1000,0,500,1500,50,'PO-20260503-0001'],[24,9,2,'purchase_receipt','purchase_orders',3,800,0,1000,1800,80,'PO-20260503-0001'],[25,10,2,'purchase_receipt','purchase_orders',3,500,0,300,800,180,'PO-20260503-0001'],[26,12,2,'purchase_receipt','purchase_orders',4,250,0,100,350,800,'PO-20260503-0002'],[27,13,2,'purchase_receipt','purchase_orders',4,200,0,80,280,650,'PO-20260503-0002'],[28,14,2,'purchase_receipt','purchase_orders',4,300,0,100,400,250,'PO-20260503-0002'],[29,15,2,'purchase_receipt','purchase_orders',4,20,0,5,25,9000,'PO-20260503-0002'],
            [32,1,3,'production_usage','production_logs',1,0,360,6000,5640,160,'PROD-20260503-0001'],[33,3,3,'production_usage','production_logs',1,0,2400,12000,9600,18,'PROD-20260503-0001'],[34,5,3,'production_usage','production_logs',1,0,500,2200,1700,35,'PROD-20260503-0001'],[35,11,3,'production_usage','production_logs',1,0,3000,15000,12000,2,'PROD-20260503-0001'],[36,12,3,'production_usage','production_logs',1,0,20,350,330,800,'PROD-20260503-0001'],[37,14,3,'production_usage','production_logs',1,0,20,400,380,250,'PROD-20260503-0001'],[38,1,3,'production_usage','production_logs',2,0,240,5640,5400,160,'PROD-20260503-0002'],[39,11,3,'production_usage','production_logs',2,0,2250,12000,9750,2,'PROD-20260503-0002'],[40,13,3,'production_usage','production_logs',2,0,15,280,265,650,'PROD-20260503-0002'],[41,14,3,'production_usage','production_logs',2,0,15,380,365,250,'PROD-20260503-0002'],[42,1,4,'production_usage','production_logs',3,0,180,5400,5220,160,'PROD-20260503-0003'],[43,4,4,'production_usage','production_logs',3,0,1800,5500,3700,22,'PROD-20260503-0003'],[44,13,4,'production_usage','production_logs',3,0,10,265,255,650,'PROD-20260503-0003'],[45,14,4,'production_usage','production_logs',3,0,10,365,355,250,'PROD-20260503-0003'],[46,10,4,'production_usage','production_logs',4,0,150,800,650,180,'PROD-20260503-0004'],[47,4,4,'production_usage','production_logs',4,0,1500,3700,2200,22,'PROD-20260503-0004'],[48,6,4,'production_usage','production_logs',4,0,100,5500,5400,15,'PROD-20260503-0004'],[49,11,4,'production_usage','production_logs',4,0,1500,9750,8250,2,'PROD-20260503-0004'],[50,12,4,'production_usage','production_logs',4,0,10,330,320,800,'PROD-20260503-0004'],[51,14,4,'production_usage','production_logs',4,0,10,355,345,250,'PROD-20260503-0004'],[52,9,3,'production_usage','production_logs',5,0,240,1800,1560,80,'PROD-20260503-0005'],[53,3,3,'production_usage','production_logs',5,0,1680,9600,7920,18,'PROD-20260503-0005'],[54,6,3,'production_usage','production_logs',5,0,120,5400,5280,15,'PROD-20260503-0005'],[55,11,3,'production_usage','production_logs',5,0,1800,8250,6450,2,'PROD-20260503-0005'],[56,12,3,'production_usage','production_logs',5,0,12,320,308,800,'PROD-20260503-0005'],[57,14,3,'production_usage','production_logs',5,0,12,345,333,250,'PROD-20260503-0005'],[58,2,3,'production_usage','production_logs',6,0,300,2500,2200,95,'PROD-20260503-0006'],[59,6,3,'production_usage','production_logs',6,0,150,5280,5130,15,'PROD-20260503-0006'],[60,13,3,'production_usage','production_logs',6,0,15,255,240,650,'PROD-20260503-0006'],[61,14,3,'production_usage','production_logs',6,0,15,333,318,250,'PROD-20260503-0006'],[62,15,4,'production_usage','production_logs',7,0,8,25,17,9000,'PROD-20260503-0007'],
            [63,4,2,'waste','stock_usages',1,0,300,2200,1900,22,'USE-20260504-0001'],[64,7,2,'waste','stock_usages',1,0,350,1600,1250,48,'USE-20260504-0001'],[65,8,2,'waste','stock_usages',1,0,1500,1500,0,50,'USE-20260504-0001'],[66,1,1,'adjustment_out','stock_adjustments',1,0,20,5220,5200,160,'ADJ-20260504-0001'],
        ];
    }
}

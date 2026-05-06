export const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value || 0);

export const formatDecimal = (value, digits = 3) =>
    Number(value || 0).toLocaleString('id-ID', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });

const LABELS = {
    owner: 'Owner',
    inventory_staff: 'Staf Inventori',
    barista: 'Barista',
    draft: 'Draf',
    received: 'Diterima',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    approved: 'Disetujui',
    waste: 'Terbuang',
    expired: 'Kedaluwarsa',
    damaged: 'Rusak',
    internal_use: 'Pemakaian internal',
    sample: 'Sampel',
    other: 'Lainnya',
    opening_stock: 'Stok awal',
    purchase_receipt: 'Penerimaan pembelian',
    production_usage: 'Pemakaian produksi',
    manual_usage: 'Pemakaian manual',
    adjustment_in: 'Penyesuaian masuk',
    adjustment_out: 'Penyesuaian keluar',
    cancel_production: 'Pembatalan produksi',
    cancel_usage: 'Pembatalan pemakaian',
    cancel_adjustment: 'Pembatalan penyesuaian',
    purchase_orders: 'Pesanan pembelian',
    production_logs: 'Catatan produksi',
    stock_usages: 'Pemakaian stok',
    stock_adjustments: 'Penyesuaian stok',
    stock_movements: 'Riwayat stok',
    ingredients: 'Bahan baku',
    ingredient_categories: 'Kategori bahan',
    units: 'Satuan',
    suppliers: 'Supplier',
    menu_items: 'Menu',
    recipe_items: 'Resep menu',
    users: 'Pengguna',
    settings: 'Pengaturan',
    reports: 'Laporan',
    login_failed: 'Masuk gagal',
    login_lockout: 'Masuk dibatasi',
    create: 'Buat',
    create_master_data: 'Buat master data',
    update_master_data: 'Ubah master data',
    delete_master_data: 'Hapus master data',
    update_unit_cost: 'Ubah harga modal',
    create_settings: 'Buat pengaturan',
    update_settings: 'Ubah pengaturan',
    delete_settings: 'Hapus pengaturan',
    receive_purchase: 'Terima pembelian',
    complete_production: 'Selesaikan produksi',
    cancel_production: 'Batalkan produksi',
    complete_usage: 'Selesaikan pemakaian',
    cancel_usage: 'Batalkan pemakaian',
    approve_adjustment: 'Setujui penyesuaian',
    cancel_adjustment: 'Batalkan penyesuaian',
    export_report: 'Ekspor laporan',
};

export const labelForValue = (value) => LABELS[value] || String(value || '-')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

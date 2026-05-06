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

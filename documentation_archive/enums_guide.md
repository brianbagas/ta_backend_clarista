# Panduan Implementasi PHP Enums

Berikut adalah panduan lengkap cara menghubungkan Enum yang baru dibuat ke Model dan Controller Anda tanpa merusak kode lama.

## 1. Pemasangan di Model (`$casts`)

Laravel memiliki fitur `casts` yang otomatis mengubah string dari database menjadi Instance Enum saat Anda mengakses properti tersebut, dan mengubah kembali menjadi string saat disimpan.

### A. Model `Pemesanan` (`app/Models/Pemesanan.php`)
Tambahkan property `$casts` (atau merge dengan yang sudah ada):

```php
use App\Enums\PemesananStatus;

class Pemesanan extends Model
{
    protected $casts = [
        'status_pemesanan' => PemesananStatus::class,
        'tanggal_check_in' => 'date',  // Casts lain yang mungkin sudah ada
        'tanggal_check_out' => 'date',
    ];
}
```

### B. Model `KamarUnit` (`app/Models/KamarUnit.php`)
```php
use App\Enums\UnitStatus;

class KamarUnit extends Model 
{
    protected $casts = [
        'status_unit' => UnitStatus::class,
    ];
}
```

### C. Model `PenempatanKamar` (`app/Models/PenempatanKamar.php`)
```php
use App\Enums\PenempatanStatus;

class PenempatanKamar extends Model
{
    protected $casts = [
        'status_penempatan' => PenempatanStatus::class,
    ];
}
```

### D. Model `Pembayaran` (`app/Models/Pembayaran.php`)
```php
use App\Enums\PembayaranStatus;

class Pembayaran extends Model
{
    protected $casts = [
        'status_verifikasi' => PembayaranStatus::class,
    ];
}
```

---

## 2. Penanganan di Controller (Backward Compatibility)

Salah satu tantangan terbesar saat beralih ke Enum adalah perbandingan nilai.

### Kasus 1: Mengubah Status (Write)
Saat menyimpan atau update, Anda bisa langsung assign Enum case. Laravel akan otomatis mengambil nilai string-nya (`->value`).

```php
// Cara LAMA (String)
$pemesanan->update(['status_pemesanan' => 'batal']);

// Cara BARU (Enum) - Lebih Aman & Auto-complete IDE
use App\Enums\PemesananStatus;

$pemesanan->update(['status_pemesanan' => PemesananStatus::Batal]);
```

### Kasus 2: Membandingkan Status (Read/Logic)
Ini bagian paling krusial. Jika Anda sudah pasang `$casts`, maka `$pemesanan->status_pemesanan` sekarang adalah **Object**, bukan String.

**❌ Salah (Akan Error/False):**
```php
if ($pemesanan->status_pemesanan == 'batal') { ... } 
// Karena Object(Enum) != String 'batal' (kecuali di PHP versi tertentu dengan loose comparison, tapi berisiko)
```

**✅ Benar (Cara 1 - Pakai Enum saat compare):**
```php
if ($pemesanan->status_pemesanan === PemesananStatus::Batal) { ... }
```

**✅ Benar (Cara 2 - Ambil value stringnya):**
```php
if ($pemesanan->status_pemesanan->value === 'batal') { ... }
```

**✅ Benar (Cara 3 - Pakai method `is()` jika pakai Laravel 9+):**
Meskipun Enum bawaan PHP tidak punya method `is`, framework seringkali memberikan helper. Tapi cara paling standar PHP Native adalah Cara 1.

### Kasus 3: JSON Response ke Frontend (Vue.js)
Jangan khawatir! Saat Laravel melakukan `json_encode($pemesanan)`, ia otomatis hanya mengambil **value** dari Backing Enum.

Jadi, Frontend Vue.js akan tetap menerima:
```json
{
  "id": 123,
  "status_pemesanan": "batal"  // Tetap string lower-case, bukan object aneh
}
```
**Frontend tidak perlu diubah sama sekali.**

---

## 3. Tips Migrasi Kode Secara Bertahap

Anda tidak perlu mengubah semua Controller sekaligus. 

1. **Pasang `$casts` di Model dulu.**
2. Test satu endpoint (misal detail booking).
3. Jika ada error `Object of class App\Enums\PemesananStatus could not be converted to string`, itu artinya ada kode yang mencoba melakukan `echo` atau string manipulation pada status.
   - Perbaikan: Ubah `$model->status` menjadi `$model->status->value`.

---

## 4. Contoh Validasi di Request
Anda bisa menggunakan aturan validasi `Enum` bawaan Laravel agar lebih clean.

```php
use Illuminate\Validation\Rules\Enum;

$request->validate([
    'status' => ['required', new Enum(PemesananStatus::class)],
]);
```
Ini otomatis memastikan input dari user hanyalah salah satu dari: `menunggu_pembayaran`, `dikonfirmasi`, dll.

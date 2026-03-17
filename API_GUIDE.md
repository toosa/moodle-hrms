# HRMS Integration API — Developer Guide

**Plugin**: `local_hrms`  
**Moodle**: 4.0+  
**Protocol**: REST (JSON)  
**Last Updated**: March 2026

**Develop by: PT Digitos Multimedia Synergy**

---

## Daftar Isi

1. [Persiapan & Konfigurasi](#1-persiapan--konfigurasi)
2. [Mekanisme Autentikasi](#2-mekanisme-autentikasi)
3. [Format Request & Response](#3-format-request--response)
4. [Referensi Fungsi API](#4-referensi-fungsi-api)
   - [GET — Daftar Kursus Aktif](#41-local_hrms_get_active_courses)
   - [GET — Peserta Kursus](#42-local_hrms_get_course_participants)
   - [GET — Hasil Pembelajaran](#43-local_hrms_get_course_results)
   - [GET — Daftar Pengguna](#44-local_hrms_get_users)
   - [WRITE — Suspend / Unsuspend Pengguna](#45-local_hrms_set_user_suspension)
   - [WRITE — Buat Kursus Baru](#46-local_hrms_create_course)
   - [WRITE — Update Setting Kursus](#47-local_hrms_update_course)
   - [WRITE — Buat Pengguna Baru](#48-local_hrms_create_user)
   - [WRITE — Update Data Pengguna](#49-local_hrms_update_user)
   - [WRITE — Enrol Pengguna ke Kursus](#410-local_hrms_enrol_user)
   - [WRITE — Unenrol Pengguna dari Kursus](#411-local_hrms_unenrol_user)
   - [GET — Progres Kursus](#412-local_hrms_get_course_progress)
5. [Format Error Response](#5-format-error-response)
6. [Contoh Implementasi](#6-contoh-implementasi)
   - [cURL / Shell](#61-curl--shell)
   - [PHP (native)](#62-php-native)
   - [Python](#63-python)
   - [JavaScript (fetch)](#64-javascript-fetch)
   - [CodeIgniter 3 Library](#65-codeigniter-3-library)
7. [Catatan Konfigurasi Moodle](#7-catatan-konfigurasi-moodle)
8. [Kode Kustom yang Digunakan Plugin](#8-kode-kustom-yang-digunakan-plugin)

---

## 1. Persiapan & Konfigurasi

### Persyaratan

| Item | Kebutuhan |
|------|-----------|
| Moodle | 4.0 atau lebih baru |
| PHP | 7.4+ |
| Web Services Moodle | Aktif |
| Protokol REST | Aktif |
| Plugin `local_hrms` | Terinstall & aktif |

### Langkah Aktivasi (Admin Moodle)

1. **Aktifkan Web Services**  
   _Site Administration → Advanced Features → Enable web services_

2. **Aktifkan Protokol REST**  
   _Site Administration → Plugins → Web services → Manage protocols → REST: Enable_

3. **Konfigurasi Plugin HRMS**  
   _Site Administration → Plugins → Local plugins → HRMS Integration_
   - Centang **Enable HRMS API**
   - Isi **API Key** dengan string acak yang aman (minimal 32 karakter)

4. **Buat Token Web Service**  
   _Site Administration → Plugins → Web services → Manage tokens → Add_
   - Pilih user yang akan mengakses API
   - Pilih service: **HRMS Integration Service**
   - Catat token yang dihasilkan

---

## 2. Mekanisme Autentikasi

Setiap request API memerlukan **dua lapis autentikasi**:

| Layer | Parameter | Sumber |
|-------|-----------|--------|
| Moodle Token | `wstoken` | Admin → Manage tokens |
| Plugin API Key | `apikey` | Admin → HRMS Integration settings |

> Kedua parameter ini **wajib ada** di setiap request. Request yang tidak memiliki salah satunya akan ditolak.

**Security Flow:**

```
Request masuk
    │
    ▼
[Layer 1] Validasi wstoken oleh Moodle Web Service
    │ Gagal → HTTP 403 / exception webservice_access_exception
    ▼
[Layer 2] Validasi apikey oleh plugin local_hrms
    │ Gagal → exception moodle_exception (invalidapikey)
    ▼
Proses fungsi & kembalikan data
```

---

## 3. Format Request & Response

### Base URL

```
https://{moodle_domain}/webservice/rest/server.php
```

### HTTP Method & Content-Type

```
Method  : POST
Header  : Content-Type: application/x-www-form-urlencoded
```

### Parameter Umum (Wajib di Semua Request)

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `wstoken` | string | Token web service Moodle |
| `wsfunction` | string | Nama fungsi yang dipanggil |
| `moodlewsrestformat` | string | Format response: `json` atau `xml` |
| `apikey` | string | API key plugin HRMS |

### Format Response Sukses

Selalu berupa JSON array atau JSON object sesuai fungsi yang dipanggil.

### Format Response Error

```json
{
  "exception": "moodle_exception",
  "errorcode": "invalidapikey",
  "message": "Invalid API key"
}
```

---

## 4. Referensi Fungsi API

---

### 4.1 `local_hrms_get_active_courses`

**Tipe**: Read  
**Deskripsi**: Mengembalikan daftar semua kursus yang sedang aktif (visible = 1), beserta informasi kategori dan custom field JP.

> **Alias**: `local_hrms_get_all_active_courses` memanggil fungsi yang sama.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID internal kursus di Moodle |
| `idnumber` | string | Nomor ID kursus (bisa kosong) |
| `shortname` | string | Nama pendek kursus |
| `fullname` | string | Nama lengkap kursus |
| `summary` | string | Deskripsi kursus (tanpa tag HTML) |
| `category_id` | int | ID kategori kursus |
| `category_name` | string | Nama kategori |
| `startdate` | int | Tanggal mulai (Unix timestamp) |
| `enddate` | int | Tanggal berakhir (Unix timestamp), `0` = tidak ada batas |
| `visible` | int | Visibilitas: `1` = tampil |
| `jp` | string | Nilai custom field `jp` (kosong jika tidak diset) |

#### Contoh Request

```bash
# Semua kursus aktif
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_active_courses" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA"

# Kursus tertentu berdasarkan ID internal
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_active_courses" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12"

# Kursus tertentu berdasarkan idnumber
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_active_courses" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001"
```

#### Contoh Response

```json
[
  {
    "id": 12,
    "idnumber": "TRAIN-2026-001",
    "shortname": "k3-dasar",
    "fullname": "Pelatihan K3 Dasar",
    "summary": "Pelatihan keselamatan kerja untuk karyawan baru",
    "category_id": 3,
    "category_name": "Pelatihan Internal",
    "startdate": 1740787200,
    "enddate": 1743465600,
    "visible": 1,
    "jp": "8"
  }
]
```

---

### 4.2 `local_hrms_get_course_participants`

**Tipe**: Read  
**Deskripsi**: Mengembalikan daftar peserta yang terdaftar (enrolled) dalam kursus. Dapat disaring per kursus atau mengambil semua kursus sekaligus.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `courseid` | int | Tidak | `0` | ID kursus. `0` = semua kursus |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `user_id` | int | ID pengguna Moodle |
| `email` | string | Alamat email pengguna |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `company_name` | string | Nama institusi/perusahaan (dari field standar `institution`, bisa kosong) |
| `course_id` | int | ID kursus |
| `course_idnumber` | string | Nomor ID kursus |
| `course_shortname` | string | Nama pendek kursus |
| `course_name` | string | Nama lengkap kursus |
| `enrollment_date` | int | Tanggal pendaftaran (Unix timestamp) |
| `role` | string | Peran pengguna dalam kursus (contoh: `student`, `editingteacher`, `teacher`). Kosong jika tidak ada role assignment. |

#### Contoh Request

```bash
# Semua peserta di semua kursus
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_participants" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=0"

# Peserta kursus tertentu berdasarkan ID internal (courseid = 12)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_participants" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12"

# Peserta kursus tertentu berdasarkan idnumber
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_participants" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001"
```

#### Contoh Response

```json
[
  {
    "user_id": 78,
    "email": "budi.santoso@perusahaan.co.id",
    "firstname": "Budi",
    "lastname": "Santoso",
    "company_name": "PT Contoh Indonesia",
    "course_id": 12,
    "course_idnumber": "TRAIN-2026-001",
    "course_shortname": "k3-dasar",
    "course_name": "Pelatihan K3 Dasar",
    "enrollment_date": 1740873600,
    "role": "student"
  }
]
```

---

### 4.3 `local_hrms_get_course_results`

**Tipe**: Read  
**Deskripsi**: Mengembalikan hasil pembelajaran per peserta, termasuk nilai akhir dan status penyelesaian kursus. Dapat disaring per kursus dan/atau per pengguna.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `courseid` | int | Tidak | `0` | ID internal kursus. `0` = semua kursus |
| `userid` | int | Tidak | `0` | ID pengguna. `0` = semua pengguna |
| `idnumber` | string | Tidak | `""` | ID number kursus (alternatif dari `courseid`). Diabaikan jika `courseid` > 0 |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `user_id` | int | ID pengguna |
| `email` | string | Email pengguna |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `company_name` | string | Nama institusi/perusahaan (dari field standar `institution`) |
| `course_id` | int | ID kursus |
| `course_shortname` | string | Nama pendek kursus |
| `course_name` | string | Nama lengkap kursus |
| `final_grade` | float | Nilai akhir kursus |
| `completion_date` | int | Timestamp penyelesaian kursus. `0` = belum selesai |
| `is_completed` | int | Status penyelesaian: `1` = selesai, `0` = belum |

#### Contoh Request

```bash
# Hasil semua pengguna di kursus tertentu berdasarkan courseid
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12" \
  -d "userid=0"

# Hasil semua pengguna di kursus tertentu berdasarkan idnumber
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001"

# Hasil pengguna tertentu di kursus tertentu
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12" \
  -d "userid=78"
```

#### Contoh Response

```json
[
  {
    "user_id": 78,
    "email": "budi.santoso@perusahaan.co.id",
    "firstname": "Budi",
    "lastname": "Santoso",
    "company_name": "PT Contoh Indonesia",
    "course_id": 12,
    "course_shortname": "k3-dasar",
    "course_name": "Pelatihan K3 Dasar",
    "final_grade": 87.50,
    "completion_date": 1743120000,
    "is_completed": 1
  }
]
```

---

### 4.4 `local_hrms_get_users`

**Tipe**: Read  
**Deskripsi**: Mengembalikan daftar pengguna Moodle. Dapat disaring berdasarkan status akun (aktif / suspended / semua) dan/atau berdasarkan alamat email.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `status` | string | Tidak | `all` | Filter status: `all`, `active`, `suspended` |
| `email` | string | Tidak | `""` | Filter berdasarkan alamat email (exact match). Kosong = semua pengguna |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID pengguna |
| `username` | string | Username login |
| `email` | string | Alamat email |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `institution` | string | Nama institusi/perusahaan |
| `suspended` | int | Status: `0` = aktif, `1` = suspended |
| `timecreated` | int | Waktu akun dibuat (Unix timestamp) |
| `lastlogin` | int | Waktu login terakhir (Unix timestamp). `0` = belum pernah login |

#### Contoh Request

```bash
# Semua pengguna
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_users" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "status=all"

# Hanya pengguna yang aktif
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_users" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "status=active"

# Filter berdasarkan email
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_users" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "email=budi.santoso@perusahaan.co.id"
```

#### Contoh Response

```json
[
  {
    "id": 78,
    "username": "budi.santoso",
    "email": "budi.santoso@perusahaan.co.id",
    "firstname": "Budi",
    "lastname": "Santoso",
    "institution": "PT Maju Bersama",
    "suspended": 0,
    "timecreated": 1735689600,
    "lastlogin": 1743033600
  }
]
```

---

### 4.5 `local_hrms_set_user_suspension`

**Tipe**: Write  
**Kapabilitas**: `moodle/user:update`  
**Deskripsi**: Meng-suspend atau mengaktifkan kembali akun pengguna. Pengguna dapat diidentifikasi melalui `userid` atau `email` (salah satu wajib diisi).

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `userid` | int | Tidak* | `0` | ID pengguna. `0` = tidak digunakan |
| `email` | string | Tidak* | `""` | Alamat email pengguna |
| `suspended` | int | Ya | — | `1` = suspend, `0` = aktifkan kembali |

\* Minimal salah satu dari `userid` atau `email` harus diisi.

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `success` | int | `1` = berhasil |
| `userid` | int | ID pengguna yang diubah |
| `suspended` | int | Status baru: `1` = suspended, `0` = aktif |
| `message` | string | Pesan konfirmasi |

#### Contoh Request

```bash
# Suspend pengguna berdasarkan email
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_set_user_suspension" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "email=budi.santoso@perusahaan.co.id" \
  -d "suspended=1"

# Aktifkan kembali berdasarkan userid
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_set_user_suspension" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "userid=78" \
  -d "suspended=0"
```

#### Contoh Response

```json
{
  "success": 1,
  "userid": 78,
  "suspended": 1,
  "message": "User suspended successfully"
}
```

---

### 4.6 `local_hrms_create_course`

**Tipe**: Write  
**Kapabilitas**: `moodle/course:create`  
**Deskripsi**: Membuat kursus baru di Moodle. `fullname` dan `shortname` bersifat wajib; parameter lain opsional.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `fullname` | string | Ya | — | Nama lengkap kursus |
| `shortname` | string | Ya | — | Nama pendek kursus (harus unik) |
| `idnumber` | string | Tidak | `""` | Nomor ID kursus (untuk referensi eksternal) |
| `summary` | string | Tidak | `""` | Deskripsi kursus (mendukung HTML) |
| `categoryid` | int | Tidak | `1` | ID kategori. Default = kategori pertama |
| `startdate` | int | Tidak | waktu sekarang | Tanggal mulai (Unix timestamp) |
| `enddate` | int | Tidak | `0` | Tanggal berakhir (Unix timestamp). `0` = tidak terbatas |
| `visible` | int | Tidak | `0` | `1` = tampil, `0` = tersembunyi |
| `jp` | int | Tidak | `1` | Nilai custom field `jp` |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID kursus yang baru dibuat |
| `shortname` | string | Nama pendek kursus |
| `fullname` | string | Nama lengkap kursus |
| `idnumber` | string | Nomor ID kursus |
| `categoryid` | int | ID kategori |
| `startdate` | int | Tanggal mulai (Unix timestamp) |
| `enddate` | int | Tanggal berakhir (Unix timestamp) |
| `visible` | int | Status visibilitas |
| `jp` | int | Nilai custom field `jp` |

#### Contoh Request

```bash
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_create_course" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "fullname=Pelatihan K3 Lanjutan" \
  -d "shortname=k3-lanjut-2026" \
  -d "idnumber=TRAIN-2026-002" \
  -d "categoryid=3" \
  -d "startdate=1748736000" \
  -d "enddate=1751414400" \
  -d "visible=1" \
  -d "jp=16"
```

#### Contoh Response

```json
{
  "id": 25,
  "shortname": "k3-lanjut-2026",
  "fullname": "Pelatihan K3 Lanjutan",
  "idnumber": "TRAIN-2026-002",
  "categoryid": 3,
  "startdate": 1748736000,
  "enddate": 1751414400,
  "visible": 1,
  "jp": 16
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `shortnametaken` | `shortname` sudah digunakan oleh kursus lain |
| `invalidapikey` | API key salah |

---

### 4.7 `local_hrms_update_course`

**Tipe**: Write  
**Kapabilitas**: `moodle/course:update`  
**Deskripsi**: Mengubah setting kursus yang sudah ada. Kursus diidentifikasi menggunakan **`idnumber`** (bukan ID internal). Hanya parameter yang dikirimkan dengan nilai berbeda dari default yang akan diubah — parameter yang tidak dikirim tidak akan mempengaruhi data yang ada.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `idnumber` | string | Ya | — | Nomor ID kursus yang akan diubah |
| `fullname` | string | Tidak | `""` | Nama lengkap baru. Kosong = tidak diubah |
| `shortname` | string | Tidak | `""` | Nama pendek baru. Kosong = tidak diubah |
| `new_idnumber` | string | Tidak | `""` | Ganti `idnumber` dengan nilai baru. Kosong = tidak diubah |
| `summary` | string | Tidak | `""` | Deskripsi baru (HTML). Kosong = tidak diubah |
| `categoryid` | int | Tidak | `0` | ID kategori baru. `0` = tidak diubah |
| `startdate` | int | Tidak | `0` | Tanggal mulai baru (Unix timestamp). `0` = tidak diubah |
| `enddate` | int | Tidak | `-1` | Tanggal berakhir baru (Unix timestamp). `-1` = tidak diubah; `0` = hapus batas tanggal |
| `visible` | int | Tidak | `-1` | Visibilitas: `1` = tampil, `0` = sembunyikan, `-1` = tidak diubah |
| `jp` | int | Tidak | `0` | Nilai custom field `jp`. `0` = tidak diubah |

#### Tabel Nilai "Tidak Diubah"

| Parameter | Nilai default (tidak diubah) | Bagaimana mengubah ke `0`/kosong |
|-----------|------------------------------|----------------------------------|
| `fullname` | `""` (string kosong) | Kirim string baru yang diinginkan |
| `shortname` | `""` | Kirim string baru yang diinginkan |
| `new_idnumber` | `""` | Kirim nilai baru; ini **mengganti** idnumber |
| `summary` | `""` | Kirim string baru |
| `categoryid` | `0` | Kirim ID kategori yang valid (> 0) |
| `startdate` | `0` | Kirim Unix timestamp > 0 |
| `enddate` | `-1` | Kirim `0` untuk menghapus batas, atau timestamp > 0 |
| `visible` | `-1` | Kirim `1` (tampil) atau `0` (sembunyi) |
| `jp` | `0` | Kirim nilai JP > 0 |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID internal Moodle kursus |
| `shortname` | string | Nama pendek kursus (setelah update) |
| `fullname` | string | Nama lengkap kursus (setelah update) |
| `idnumber` | string | Nomor ID kursus (setelah update) |
| `categoryid` | int | ID kategori (setelah update) |
| `startdate` | int | Tanggal mulai (setelah update) |
| `enddate` | int | Tanggal berakhir (setelah update) |
| `visible` | int | Status visibilitas (setelah update) |
| `jp` | int | Nilai custom field `jp` (setelah update) |

#### Contoh Request

```bash
# Hanya ubah nama lengkap dan visibilitas kursus
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_course" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001" \
  -d "fullname=Pelatihan K3 Dasar (Revisi 2026)" \
  -d "visible=1"

# Ganti idnumber dan pindah kategori
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_course" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001" \
  -d "new_idnumber=TRAIN-2026-001-REV" \
  -d "categoryid=5"

# Update tanggal dan nilai JP
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_course" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001" \
  -d "startdate=1751414400" \
  -d "enddate=1754006400" \
  -d "jp=24"
```

#### Contoh Response

```json
{
  "id": 12,
  "shortname": "k3-dasar",
  "fullname": "Pelatihan K3 Dasar (Revisi 2026)",
  "idnumber": "TRAIN-2026-001",
  "categoryid": 3,
  "startdate": 1740787200,
  "enddate": 1743465600,
  "visible": 1,
  "jp": 8
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `invalidrecord` | Tidak ada kursus dengan `idnumber` tersebut |
| `shortnametaken` | `shortname` baru sudah digunakan kursus lain |
| `courseidnumbertaken` | `new_idnumber` sudah digunakan kursus lain |
| `invalidapikey` | API key salah |

---

### 4.8 `local_hrms_create_user`

**Tipe**: Write  
**Kapabilitas**: `moodle/user:create`  
**Deskripsi**: Membuat akun pengguna baru di Moodle. Jika email yang dikirim sudah digunakan oleh pengguna lain, pembuatan akun akan ditolak dengan pesan error.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `username` | string | Ya | — | Username login (huruf kecil, tanpa spasi) |
| `email` | string | Ya | — | Alamat email (harus unik) |
| `firstname` | string | Ya | — | Nama depan |
| `lastname` | string | Ya | — | Nama belakang |
| `password` | string | Ya | — | Password plain-text |
| `institution` | string | Tidak | `""` | Nama institusi/perusahaan |
| `department` | string | Tidak | `""` | Departemen |
| `phone1` | string | Tidak | `""` | Nomor telepon |
| `city` | string | Tidak | `""` | Kota |
| `country` | string | Tidak | `""` | Kode negara dua huruf (contoh: `ID`) |
| `auth` | string | Tidak | `manual` | Plugin autentikasi (contoh: `manual`, `ldap`) |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID pengguna yang baru dibuat |
| `username` | string | Username |
| `email` | string | Alamat email |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `institution` | string | Nama institusi/perusahaan |
| `department` | string | Departemen |
| `auth` | string | Plugin autentikasi yang digunakan |
| `timecreated` | int | Waktu akun dibuat (Unix timestamp) |

#### Contoh Request

```bash
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_create_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "username=siti.rahayu" \
  -d "email=siti.rahayu@perusahaan.co.id" \
  -d "firstname=Siti" \
  -d "lastname=Rahayu" \
  -d "password=P@ssw0rd!" \
  -d "institution=PT Contoh Indonesia" \
  -d "department=SDM" \
  -d "city=Jakarta" \
  -d "country=ID"
```

#### Contoh Response

```json
{
  "id": 95,
  "username": "siti.rahayu",
  "email": "siti.rahayu@perusahaan.co.id",
  "firstname": "Siti",
  "lastname": "Rahayu",
  "institution": "PT Contoh Indonesia",
  "department": "SDM",
  "auth": "manual",
  "timecreated": 1741824000
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `emailalreadyused` | Email sudah digunakan oleh pengguna lain. Penambahan user baru tidak dapat dilakukan. |
| `usernameexists` | Username sudah digunakan oleh pengguna lain |
| `invalidapikey` | API key salah |

---

### 4.9 `local_hrms_update_user`

**Tipe**: Write  
**Kapabilitas**: `moodle/user:update`  
**Deskripsi**: Mengubah data pengguna yang sudah ada. Pengguna dapat diidentifikasi melalui `userid` atau `email` (salah satu wajib diisi). Hanya field yang dikirim dengan nilai tidak kosong yang akan diubah.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `userid` | int | Tidak* | `0` | ID pengguna. `0` = tidak digunakan |
| `email` | string | Tidak* | `""` | Email saat ini untuk mengidentifikasi pengguna |
| `new_email` | string | Tidak | `""` | Email baru. Kosong = tidak diubah |
| `firstname` | string | Tidak | `""` | Nama depan baru. Kosong = tidak diubah |
| `lastname` | string | Tidak | `""` | Nama belakang baru. Kosong = tidak diubah |
| `institution` | string | Tidak | `""` | Nama institusi/perusahaan baru. Kosong = tidak diubah |

\* Minimal salah satu dari `userid` atau `email` harus diisi untuk mengidentifikasi pengguna.

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID pengguna |
| `username` | string | Username |
| `email` | string | Alamat email (setelah update) |
| `firstname` | string | Nama depan (setelah update) |
| `lastname` | string | Nama belakang (setelah update) |
| `institution` | string | Nama institusi/perusahaan (setelah update) |

#### Contoh Request

```bash
# Update berdasarkan userid
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "userid=95" \
  -d "firstname=Siti" \
  -d "lastname=Rahayu Baru" \
  -d "institution=PT Baru Indonesia"

# Ganti email berdasarkan email lama
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "email=siti.rahayu@perusahaan.co.id" \
  -d "new_email=s.rahayu@perusahaan.co.id"
```

#### Contoh Response

```json
{
  "id": 95,
  "username": "siti.rahayu",
  "email": "s.rahayu@perusahaan.co.id",
  "firstname": "Siti",
  "lastname": "Rahayu Baru",
  "institution": "PT Baru Indonesia"
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `invaliduser` | Pengguna tidak ditemukan (userid/email tidak valid) |
| `emailalreadyused` | `new_email` sudah digunakan pengguna lain |
| `invalidapikey` | API key salah |

---

### 4.10 `local_hrms_enrol_user`

**Tipe**: Write  
**Kapabilitas**: `enrol/manual:enrol`  
**Deskripsi**: Mendaftarkan (enrol) pengguna ke dalam sebuah kursus menggunakan **manual enrolment plugin**. Pengguna dapat diidentifikasi dengan `userid` atau `email`; kursus dapat diidentifikasi dengan `courseid` atau `idnumber`. Operasi ini idempoten — jika pengguna sudah terdaftar, enrolment-nya akan diperbarui.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `userid` | int | Tidak* | `0` | ID pengguna. `0` = gunakan `email` |
| `email` | string | Tidak* | `""` | Alamat email pengguna. Digunakan jika `userid` = 0 |
| `courseid` | int | Tidak** | `0` | ID internal kursus. `0` = gunakan `idnumber` |
| `idnumber` | string | Tidak** | `""` | Nomor ID kursus. Digunakan jika `courseid` = 0 |
| `roleid` | int | Tidak | `0` | ID role yang diberikan. `0` = gunakan role default dari enrol instance (biasanya `student`) |

\* Minimal salah satu dari `userid` atau `email` harus diisi.  
\*\* Minimal salah satu dari `courseid` atau `idnumber` harus diisi.

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `success` | int | `1` = berhasil |
| `userid` | int | ID pengguna yang dienrol |
| `courseid` | int | ID kursus tujuan |
| `roleid` | int | ID role yang digunakan |
| `message` | string | Pesan konfirmasi |

#### Contoh Request

```bash
# Enrol berdasarkan userid dan courseid
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_enrol_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "userid=78" \
  -d "courseid=12"

# Enrol berdasarkan email dan idnumber kursus
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_enrol_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "email=budi.santoso@perusahaan.co.id" \
  -d "idnumber=TRAIN-2026-001"

# Enrol dengan role tertentu (misal role teacher, roleid=3)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_enrol_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "userid=78" \
  -d "courseid=12" \
  -d "roleid=3"
```

#### Contoh Response

```json
{
  "success": 1,
  "userid": 78,
  "courseid": 12,
  "roleid": 5,
  "message": "User enrolled successfully"
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `invaliduser` | Pengguna tidak ditemukan |
| `invalidrecord` | Kursus tidak ditemukan (berdasarkan `idnumber`) |
| `invalidcourseid` | `courseid` merujuk ke site course |
| `missingparam` | Tidak ada `courseid` maupun `idnumber` yang dikirim |
| `enrolnotinstalled` | Plugin enrolment manual tidak tersedia |
| `invalidapikey` | API key salah |

---

### 4.11 `local_hrms_unenrol_user`

**Tipe**: Write  
**Kapabilitas**: `enrol/manual:unenrol`  
**Deskripsi**: Mengeluarkan (unenrol) pengguna dari sebuah kursus. Pengguna dan kursus dapat diidentifikasi dengan `userid`/`email` dan `courseid`/`idnumber`. Operasi ini menghapus pengguna dari **semua** enrol instance di kursus tersebut.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `userid` | int | Tidak* | `0` | ID pengguna. `0` = gunakan `email` |
| `email` | string | Tidak* | `""` | Alamat email pengguna. Digunakan jika `userid` = 0 |
| `courseid` | int | Tidak** | `0` | ID internal kursus. `0` = gunakan `idnumber` |
| `idnumber` | string | Tidak** | `""` | Nomor ID kursus. Digunakan jika `courseid` = 0 |

\* Minimal salah satu dari `userid` atau `email` harus diisi.  
\*\* Minimal salah satu dari `courseid` atau `idnumber` harus diisi.

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `success` | int | `1` = berhasil |
| `userid` | int | ID pengguna yang di-unenrol |
| `courseid` | int | ID kursus |
| `message` | string | Pesan konfirmasi |

#### Contoh Request

```bash
# Unenrol berdasarkan userid dan courseid
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_unenrol_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "userid=78" \
  -d "courseid=12"

# Unenrol berdasarkan email dan idnumber kursus
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_unenrol_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "email=budi.santoso@perusahaan.co.id" \
  -d "idnumber=TRAIN-2026-001"
```

#### Contoh Response

```json
{
  "success": 1,
  "userid": 78,
  "courseid": 12,
  "message": "User unenrolled successfully"
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `invaliduser` | Pengguna tidak ditemukan |
| `invalidrecord` | Kursus tidak ditemukan (berdasarkan `idnumber`) |
| `invalidcourseid` | `courseid` merujuk ke site course |
| `notenrolled` | Pengguna tidak terdaftar di kursus tersebut |
| `missingparam` | Tidak ada `courseid` maupun `idnumber` yang dikirim |
| `invalidapikey` | API key salah |

---

### 4.12 `local_hrms_get_course_progress`

**Tipe**: Read  
**Deskripsi**: Mengembalikan ringkasan progres belajar per peserta dalam kursus. Progres dihitung berdasarkan jumlah activity yang memiliki completion tracking aktif yang telah diselesaikan dibandingkan total activity. Dapat disaring per kursus (courseid atau idnumber) dan per pengguna.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `courseid` | int | Tidak** | `0` | ID internal kursus. `0` = gunakan `idnumber` |
| `idnumber` | string | Tidak** | `""` | Nomor ID kursus. Digunakan jika `courseid` = 0 |
| `userid` | int | Tidak | `0` | Filter per pengguna berdasarkan ID. `0` = semua peserta |
| `email` | string | Tidak | `""` | Filter per pengguna berdasarkan email (exact match). Kosong = semua peserta |

\*\* Minimal salah satu dari `courseid` atau `idnumber` diisi untuk menyaring per kursus. Jika keduanya kosong, mengembalikan semua kursus aktif.
> `userid` dan `email` bersifat alternatif — jika `userid` > 0 maka `email` diabaikan.

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `user_id` | int | ID pengguna |
| `email` | string | Alamat email |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `company_name` | string | Nama institusi/perusahaan |
| `course_id` | int | ID kursus |
| `course_shortname` | string | Nama pendek kursus |
| `course_name` | string | Nama lengkap kursus |
| `modules_total` | int | Total activity dengan completion tracking aktif |
| `modules_completed` | int | Jumlah activity yang telah diselesaikan |
| `completion_percentage` | float | Persentase penyelesaian (0–100) |
| `is_completed` | int | Status penyelesaian kursus: `1` = selesai, `0` = belum |
| `completion_date` | int | Timestamp penyelesaian kursus. `0` = belum selesai |

#### Contoh Request

```bash
# Progres semua peserta di kursus tertentu (by courseid)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12"

# Progres semua peserta di kursus tertentu (by idnumber)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001"

# Progres pengguna tertentu di kursus tertentu (by userid)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12" \
  -d "userid=78"

# Progres pengguna tertentu di kursus tertentu (by email)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12" \
  -d "email=budi.santoso@perusahaan.co.id"
```

#### Contoh Response

```json
[
  {
    "user_id": 78,
    "email": "budi.santoso@perusahaan.co.id",
    "firstname": "Budi",
    "lastname": "Santoso",
    "company_name": "PT Contoh Indonesia",
    "course_id": 12,
    "course_shortname": "k3-dasar",
    "course_name": "Pelatihan K3 Dasar",
    "modules_total": 10,
    "modules_completed": 7,
    "completion_percentage": 70.00,
    "is_completed": 0,
    "completion_date": 0
  }
]
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `invalidrecord` | Kursus tidak ditemukan (berdasarkan `idnumber`) |
| `invalidapikey` | API key salah |

---

## 5. Format Error Response

Seluruh error dikembalikan dalam format JSON berikut:

```json
{
  "exception": "moodle_exception",
  "errorcode": "<kode_error>",
  "message": "<pesan_error_dalam_bahasa_sistem>"
}
```

### Kode Error Umum

| `errorcode` | Penyebab | Solusi |
|-------------|----------|--------|
| `invalidapikey` | API key salah atau tidak diset | Periksa konfigurasi API key di plugin settings |
| `accessexception` | `wstoken` tidak valid atau kadaluarsa | Hasilkan token baru di Manage tokens |
| `invalidrecord` | Data tidak ditemukan (misal: `idnumber` kursus) | Periksa nilai parameter yang dikirim |
| `shortnametaken` | Shortname kursus sudah digunakan | Gunakan shortname yang berbeda |
| `courseidnumbertaken` | idnumber sudah digunakan kursus lain | Gunakan idnumber yang berbeda |
| `invalidstatus` | Nilai parameter `status` tidak valid | Gunakan: `all`, `active`, atau `suspended` |
| `emailalreadyused` | Email sudah digunakan pengguna lain | Gunakan alamat email yang berbeda |
| `usernameexists` | Username sudah digunakan pengguna lain | Gunakan username yang berbeda |
| `invaliduser` | Pengguna tidak ditemukan | Periksa `userid` atau `email` yang dikirim |
| `notenrolled` | Pengguna tidak terdaftar di kursus tersebut | Pastikan user sudah enrolled sebelum di-unenrol |
| `missingparam` | Parameter `courseid` dan `idnumber` keduanya kosong | Kirim salah satu parameter kursus |

---

## 6. Contoh Implementasi

### 6.1 cURL / Shell

```bash
#!/bin/bash

BASE_URL="https://moodle.example.com/webservice/rest/server.php"
TOKEN="wstoken_anda_disini"
APIKEY="apikey_anda_disini"

# Helper function
call_api() {
  local wsfunction="$1"
  shift
  curl -s -X POST "$BASE_URL" \
    -d "wstoken=$TOKEN" \
    -d "wsfunction=$wsfunction" \
    -d "moodlewsrestformat=json" \
    -d "apikey=$APIKEY" \
    "$@"
}

# Ambil daftar kursus aktif
call_api "local_hrms_get_active_courses"

# Ambil peserta kursus ID 12
call_api "local_hrms_get_course_participants" -d "courseid=12"

# Buat kursus baru
call_api "local_hrms_create_course" \
  -d "fullname=Kursus Baru" \
  -d "shortname=kursus-baru-2026" \
  -d "idnumber=KRS-2026-099" \
  -d "categoryid=3" \
  -d "visible=1" \
  -d "jp=8"

# Update kursus (hanya visible)
call_api "local_hrms_update_course" \
  -d "idnumber=KRS-2026-099" \
  -d "visible=0"

# Buat pengguna baru
call_api "local_hrms_create_user" \
  -d "username=siti.rahayu" \
  -d "email=siti.rahayu@perusahaan.co.id" \
  -d "firstname=Siti" \
  -d "lastname=Rahayu" \
  -d "password=P@ssw0rd!" \
  -d "institution=PT Contoh Indonesia" \
  -d "department=SDM" \
  -d "city=Jakarta" \
  -d "country=ID"

# Update data pengguna
call_api "local_hrms_update_user" \
  -d "userid=95" \
  -d "firstname=Siti" \
  -d "lastname=Rahayu Baru" \
  -d "institution=PT Baru Indonesia"

# Enrol pengguna ke kursus
call_api "local_hrms_enrol_user" \
  -d "userid=78" \
  -d "idnumber=TRAIN-2026-001"

# Unenrol pengguna dari kursus
call_api "local_hrms_unenrol_user" \
  -d "email=budi.santoso@perusahaan.co.id" \
  -d "idnumber=TRAIN-2026-001"

# Progres belajar peserta kursus
call_api "local_hrms_get_course_progress" \
  -d "idnumber=TRAIN-2026-001"
```

---

### 6.2 PHP (native)

```php
<?php

class HrmsClient
{
    private string $baseUrl;
    private string $token;
    private string $apiKey;

    public function __construct(string $baseUrl, string $token, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/webservice/rest/server.php';
        $this->token   = $token;
        $this->apiKey  = $apiKey;
    }

    private function call(string $function, array $params = []): array
    {
        $payload = array_merge([
            'wstoken'            => $this->token,
            'wsfunction'         => $function,
            'moodlewsrestformat' => 'json',
            'apikey'             => $this->apiKey,
        ], $params);

        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new RuntimeException("HTTP error: $httpCode");
        }

        $data = json_decode($response, true);
        if (isset($data['exception'])) {
            throw new RuntimeException("[{$data['errorcode']}] {$data['message']}");
        }

        return $data;
    }

    public function getActiveCourses(int $courseId = 0, string $idnumber = ''): array
    {
        return $this->call('local_hrms_get_active_courses', ['courseid' => $courseId, 'idnumber' => $idnumber]);
    }

    public function getCourseParticipants(int $courseId = 0, string $idnumber = ''): array
    {
        return $this->call('local_hrms_get_course_participants', ['courseid' => $courseId, 'idnumber' => $idnumber]);
    }

    public function getCourseResults(int $courseId = 0, int $userId = 0, string $idnumber = ''): array
    {
        return $this->call('local_hrms_get_course_results', [
            'courseid' => $courseId,
            'userid'   => $userId,
            'idnumber' => $idnumber,
        ]);
    }

    public function getUsers(string $status = 'all', string $email = ''): array
    {
        return $this->call('local_hrms_get_users', ['status' => $status, 'email' => $email]);
    }

    public function setUserSuspension(int $userId = 0, string $email = '', int $suspended = 1): array
    {
        return $this->call('local_hrms_set_user_suspension', [
            'userid'    => $userId,
            'email'     => $email,
            'suspended' => $suspended,
        ]);
    }

    public function createCourse(array $data): array
    {
        return $this->call('local_hrms_create_course', $data);
    }

    public function updateCourse(string $idnumber, array $changes): array
    {
        return $this->call('local_hrms_update_course', array_merge(
            ['idnumber' => $idnumber],
            $changes
        ));
    }

    public function createUser(array $data): array
    {
        return $this->call('local_hrms_create_user', $data);
    }

    public function updateUser(array $data): array
    {
        return $this->call('local_hrms_update_user', $data);
    }

    public function enrolUser(int $userId = 0, string $email = '', int $courseId = 0, string $idnumber = '', int $roleId = 0): array
    {
        return $this->call('local_hrms_enrol_user', [
            'userid'   => $userId,
            'email'    => $email,
            'courseid' => $courseId,
            'idnumber' => $idnumber,
            'roleid'   => $roleId,
        ]);
    }

    public function unenrolUser(int $userId = 0, string $email = '', int $courseId = 0, string $idnumber = ''): array
    {
        return $this->call('local_hrms_unenrol_user', [
            'userid'   => $userId,
            'email'    => $email,
            'courseid' => $courseId,
            'idnumber' => $idnumber,
        ]);
    }

    public function getCourseProgress(int $courseId = 0, string $idnumber = '', int $userId = 0, string $email = ''): array
    {
        return $this->call('local_hrms_get_course_progress', [
            'courseid' => $courseId,
            'idnumber' => $idnumber,
            'userid'   => $userId,
            'email'    => $email,
        ]);
    }
}

// --- Penggunaan ---
$client = new HrmsClient(
    'https://moodle.example.com',
    'wstoken_anda',
    'apikey_anda'
);

// Ambil kursus aktif
$courses = $client->getActiveCourses();
foreach ($courses as $c) {
    echo "{$c['idnumber']} — {$c['fullname']}\n";
}

// Buat kursus
$newCourse = $client->createCourse([
    'fullname'   => 'Pelatihan Baru',
    'shortname'  => 'pel-baru-2026',
    'idnumber'   => 'TRAIN-2026-100',
    'categoryid' => 3,
    'visible'    => 1,
    'jp'         => 8,
]);
echo "Kursus dibuat: ID {$newCourse['id']}\n";

// Update kursus
$updated = $client->updateCourse('TRAIN-2026-100', [
    'fullname' => 'Pelatihan Baru (Update)',
    'visible'  => 0,
]);
echo "Kursus diupdate: {$updated['fullname']}\n";

// Buat pengguna baru
$newUser = $client->createUser([
    'username'    => 'siti.rahayu',
    'email'       => 'siti.rahayu@perusahaan.co.id',
    'firstname'   => 'Siti',
    'lastname'    => 'Rahayu',
    'password'    => 'P@ssw0rd!',
    'institution' => 'PT Contoh Indonesia',
    'department'  => 'SDM',
    'city'        => 'Jakarta',
    'country'     => 'ID',
]);
echo "Pengguna dibuat: ID {$newUser['id']}\n";

// Update data pengguna
$updatedUser = $client->updateUser([
    'userid'      => 95,
    'lastname'    => 'Rahayu Baru',
    'institution' => 'PT Baru Indonesia',
]);
echo "Pengguna diupdate: {$updatedUser['institution']}\n";

// Enrol pengguna ke kursus
$enrol = $client->enrolUser(78, '', 0, 'TRAIN-2026-001');
echo "Enrol: {$enrol['message']}\n";

// Unenrol pengguna dari kursus
$unenrol = $client->unenrolUser(0, 'budi.santoso@perusahaan.co.id', 0, 'TRAIN-2026-001');
echo "Unenrol: {$unenrol['message']}\n";

// Progres belajar peserta kursus
$progress = $client->getCourseProgress(0, 'TRAIN-2026-001');
foreach ($progress as $p) {
    echo "{$p['firstname']} {$p['lastname']}: {$p['completion_percentage']}%\n";
}
```

---

### 6.3 Python

```python
import requests

class HrmsClient:
    def __init__(self, base_url: str, token: str, api_key: str):
        self.url     = base_url.rstrip('/') + '/webservice/rest/server.php'
        self.token   = token
        self.api_key = api_key

    def _call(self, function: str, **kwargs) -> dict | list:
        payload = {
            'wstoken': self.token,
            'wsfunction': function,
            'moodlewsrestformat': 'json',
            'apikey': self.api_key,
            **kwargs,
        }
        resp = requests.post(self.url, data=payload, timeout=30)
        resp.raise_for_status()
        data = resp.json()
        if isinstance(data, dict) and 'exception' in data:
            raise ValueError(f"[{data['errorcode']}] {data['message']}")
        return data

    def get_active_courses(self, course_id: int = 0, idnumber: str = ''):
        return self._call('local_hrms_get_active_courses', courseid=course_id, idnumber=idnumber)

    def get_course_participants(self, course_id: int = 0, idnumber: str = ''):
        return self._call('local_hrms_get_course_participants', courseid=course_id, idnumber=idnumber)

    def get_course_results(self, course_id: int = 0, user_id: int = 0, idnumber: str = ''):
        return self._call('local_hrms_get_course_results', courseid=course_id, userid=user_id, idnumber=idnumber)

    def get_users(self, status: str = 'all', email: str = ''):
        return self._call('local_hrms_get_users', status=status, email=email)

    def set_user_suspension(self, suspended: int, user_id: int = 0, email: str = ''):
        return self._call('local_hrms_set_user_suspension',
                          userid=user_id, email=email, suspended=suspended)

    def create_course(self, **kwargs):
        return self._call('local_hrms_create_course', **kwargs)

    def update_course(self, idnumber: str, **kwargs):
        return self._call('local_hrms_update_course', idnumber=idnumber, **kwargs)

    def create_user(self, **kwargs):
        return self._call('local_hrms_create_user', **kwargs)

    def update_user(self, **kwargs):
        return self._call('local_hrms_update_user', **kwargs)

    def enrol_user(self, user_id: int = 0, email: str = '', course_id: int = 0, idnumber: str = '', role_id: int = 0):
        return self._call('local_hrms_enrol_user',
                          userid=user_id, email=email, courseid=course_id,
                          idnumber=idnumber, roleid=role_id)

    def unenrol_user(self, user_id: int = 0, email: str = '', course_id: int = 0, idnumber: str = ''):
        return self._call('local_hrms_unenrol_user',
                          userid=user_id, email=email, courseid=course_id, idnumber=idnumber)

    def get_course_progress(self, course_id: int = 0, idnumber: str = '', user_id: int = 0, email: str = ''):
        return self._call('local_hrms_get_course_progress',
                          courseid=course_id, idnumber=idnumber, userid=user_id, email=email)


# --- Penggunaan ---
client = HrmsClient(
    base_url='https://moodle.example.com',
    token='wstoken_anda',
    api_key='apikey_anda',
)

# Daftar kursus aktif
for c in client.get_active_courses():
    print(f"{c['idnumber']} — {c['fullname']}")

# Buat kursus baru
new = client.create_course(
    fullname='Pelatihan Python',
    shortname='py-dasar-2026',
    idnumber='TRAIN-2026-200',
    categoryid=3,
    visible=1,
    jp=8,
)
print(f"Kursus dibuat: id={new['id']}")

# Sembunyikan kursus
updated = client.update_course('TRAIN-2026-200', visible=0)
print(f"Kursus disembunyikan: visible={updated['visible']}")

# Buat pengguna baru
new_user = client.create_user(
    username='siti.rahayu',
    email='siti.rahayu@perusahaan.co.id',
    firstname='Siti',
    lastname='Rahayu',
    password='P@ssw0rd!',
    institution='PT Contoh Indonesia',
    department='SDM',
    city='Jakarta',
    country='ID',
)
print(f"Pengguna dibuat: id={new_user['id']}")

# Update data pengguna
updated_user = client.update_user(
    userid=95,
    lastname='Rahayu Baru',
    institution='PT Baru Indonesia',
)
print(f"Pengguna diupdate: {updated_user['institution']}")

# Enrol pengguna ke kursus
enrol = client.enrol_user(user_id=78, idnumber='TRAIN-2026-001')
print(f"Enrol: {enrol['message']}")

# Unenrol pengguna dari kursus
unenrol = client.unenrol_user(email='budi.santoso@perusahaan.co.id', idnumber='TRAIN-2026-001')
print(f"Unenrol: {unenrol['message']}")

# Progres belajar peserta kursus
progress = client.get_course_progress(idnumber='TRAIN-2026-001')
for p in progress:
    print(f"{p['firstname']} {p['lastname']}: {p['completion_percentage']}%")
```

---

### 6.4 JavaScript (fetch)

```javascript
class HrmsClient {
  constructor(baseUrl, token, apiKey) {
    this.url    = `${baseUrl.replace(/\/$/, '')}/webservice/rest/server.php`;
    this.token  = token;
    this.apiKey = apiKey;
  }

  async call(wsfunction, params = {}) {
    const body = new URLSearchParams({
      wstoken: this.token,
      wsfunction,
      moodlewsrestformat: 'json',
      apikey: this.apiKey,
      ...params,
    });

    const resp = await fetch(this.url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    });

    const data = await resp.json();
    if (data?.exception) {
      throw new Error(`[${data.errorcode}] ${data.message}`);
    }
    return data;
  }

  getActiveCourses(courseId = 0, idnumber = '')         { return this.call('local_hrms_get_active_courses', { courseid: courseId, idnumber }); }
  getCourseParticipants(courseId = 0, idnumber = '') { return this.call('local_hrms_get_course_participants', { courseid: courseId, idnumber }); }
  getCourseResults(courseId = 0, userId = 0, idnumber = '') {
    return this.call('local_hrms_get_course_results', { courseid: courseId, userid: userId, idnumber });
  }
  getUsers(status = 'all', email = '')     { return this.call('local_hrms_get_users', { status, email }); }
  createCourse(data)                       { return this.call('local_hrms_create_course', data); }
  updateCourse(idnumber, changes)          {
    return this.call('local_hrms_update_course', { idnumber, ...changes });
  }
  createUser(data)                         { return this.call('local_hrms_create_user', data); }
  updateUser(data)                         { return this.call('local_hrms_update_user', data); }
  enrolUser({ userId = 0, email = '', courseId = 0, idnumber = '', roleId = 0 } = {}) {
    return this.call('local_hrms_enrol_user', { userid: userId, email, courseid: courseId, idnumber, roleid: roleId });
  }
  unenrolUser({ userId = 0, email = '', courseId = 0, idnumber = '' } = {}) {
    return this.call('local_hrms_unenrol_user', { userid: userId, email, courseid: courseId, idnumber });
  }
  getCourseProgress(courseId = 0, idnumber = '', userId = 0, email = '') {
    return this.call('local_hrms_get_course_progress', { courseid: courseId, idnumber, userid: userId, email });
  }
}

// --- Penggunaan ---
const client = new HrmsClient(
  'https://moodle.example.com',
  'wstoken_anda',
  'apikey_anda',
);

// Ambil kursus aktif
const courses = await client.getActiveCourses();
courses.forEach(c => console.log(c.idnumber, c.fullname));

// Update kursus
const updated = await client.updateCourse('TRAIN-2026-001', { visible: 0, jp: 16 });
console.log('Updated:', updated);

// Buat pengguna baru
const newUser = await client.createUser({
  username: 'siti.rahayu',
  email: 'siti.rahayu@perusahaan.co.id',
  firstname: 'Siti',
  lastname: 'Rahayu',
  password: 'P@ssw0rd!',
  institution: 'PT Contoh Indonesia',
  department: 'SDM',
  city: 'Jakarta',
  country: 'ID',
});
console.log('User dibuat:', newUser.id);

// Update data pengguna
const updatedUser = await client.updateUser({
  userid: 95,
  lastname: 'Rahayu Baru',
  institution: 'PT Baru Indonesia',
});
console.log('User diupdate:', updatedUser.institution);

// Enrol pengguna ke kursus
const enrol = await client.enrolUser({ userId: 78, idnumber: 'TRAIN-2026-001' });
console.log('Enrol:', enrol.message);

// Unenrol pengguna dari kursus
const unenrol = await client.unenrolUser({ email: 'budi.santoso@perusahaan.co.id', idnumber: 'TRAIN-2026-001' });
console.log('Unenrol:', unenrol.message);

// Progres belajar peserta kursus
const progress = await client.getCourseProgress(0, 'TRAIN-2026-001');
progress.forEach(p => console.log(`${p.firstname} ${p.lastname}: ${p.completion_percentage}%`));
```

---

### 6.5 CodeIgniter 3 Library

**`application/config/hrms.php`**

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['hrms_base_url'] = 'https://moodle.example.com/webservice/rest/server.php';
$config['hrms_ws_token'] = 'wstoken_anda';
$config['hrms_api_key']  = 'apikey_anda';
```

**`application/libraries/Hrms_client.php`**

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hrms_client
{
    protected $CI;
    protected $base_url;
    protected $token;
    protected $api_key;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('hrms');

        $this->base_url = $this->CI->config->item('hrms_base_url');
        $this->token    = $this->CI->config->item('hrms_ws_token');
        $this->api_key  = $this->CI->config->item('hrms_api_key');
    }

    protected function call($function, $params = [])
    {
        $payload = array_merge([
            'wstoken'            => $this->token,
            'wsfunction'         => $function,
            'moodlewsrestformat' => 'json',
            'apikey'             => $this->api_key,
        ], $params);

        $ch = curl_init($this->base_url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['exception'])) {
            log_message('error', "HRMS API [{$data['errorcode']}]: {$data['message']}");
            return null;
        }
        return $data;
    }

    public function get_active_courses($course_id = 0, $idnumber = '')         { return $this->call('local_hrms_get_active_courses', ['courseid' => (int)$course_id, 'idnumber' => $idnumber]); }
    public function get_course_participants($course_id = 0, $idnumber = '') {
        return $this->call('local_hrms_get_course_participants', ['courseid' => (int)$course_id, 'idnumber' => $idnumber]);
    }
    public function get_course_results($course_id = 0, $user_id = 0, $idnumber = '') {
        return $this->call('local_hrms_get_course_results', ['courseid' => (int)$course_id, 'userid' => (int)$user_id, 'idnumber' => $idnumber]);
    }
    public function get_users($status = 'all', $email = '')       { return $this->call('local_hrms_get_users', ['status' => $status, 'email' => $email]); }
    public function set_user_suspension($suspended, $user_id = 0, $email = '') {
        return $this->call('local_hrms_set_user_suspension', ['userid' => (int)$user_id, 'email' => $email, 'suspended' => (int)$suspended]);
    }
    public function create_course($data)                         { return $this->call('local_hrms_create_course', $data); }
    public function update_course($idnumber, $changes)           { return $this->call('local_hrms_update_course', array_merge(['idnumber' => $idnumber], $changes)); }
    public function create_user($data)                           { return $this->call('local_hrms_create_user', $data); }
    public function update_user($data)                           { return $this->call('local_hrms_update_user', $data); }
    public function enrol_user($user_id = 0, $email = '', $course_id = 0, $idnumber = '', $role_id = 0) {
        return $this->call('local_hrms_enrol_user', [
            'userid'   => (int)$user_id,
            'email'    => $email,
            'courseid' => (int)$course_id,
            'idnumber' => $idnumber,
            'roleid'   => (int)$role_id,
        ]);
    }
    public function unenrol_user($user_id = 0, $email = '', $course_id = 0, $idnumber = '') {
        return $this->call('local_hrms_unenrol_user', [
            'userid'   => (int)$user_id,
            'email'    => $email,
            'courseid' => (int)$course_id,
            'idnumber' => $idnumber,
        ]);
    }
    public function get_course_progress($course_id = 0, $idnumber = '', $user_id = 0, $email = '') {
        return $this->call('local_hrms_get_course_progress', [
            'courseid' => (int)$course_id,
            'idnumber' => $idnumber,
            'userid'   => (int)$user_id,
            'email'    => $email,
        ]);
    }
}
```

---

## 7. Catatan Konfigurasi Moodle

### Custom Field yang Diperlukan

#### Custom Field pada Course Module (Quiz)

| Shortname | Tipe | Nilai | Keterangan |
|-----------|------|-------|------------|
| `jenis_quiz` | Text / Select | `1` Normal, `2` PreTest, `3` PostTest | Menandai jenis quiz |

**Cara setup**:
1. _Site Administration → Plugins → Activity modules → Manage activities → Course module custom fields_
2. Tambah field baru dengan shortname `jenis_quiz`
3. Di setiap quiz yang ingin ditandai, set field ini ke `2` (pre-test) atau `3` (post-test)

#### Custom Field pada Course

| Shortname | Tipe | Keterangan |
|-----------|------|------------|
| `jp` | Numeric / Text | Jumlah Jam Pelatihan (JP) — opsional, digunakan pada `create_course` dan `update_course` |

#### Field Standar User yang Digunakan

| Field | Tipe | Keterangan |
|-------|------|------------|
| `institution` | Text | Nama institusi/perusahaan pengguna — ditampilkan sebagai `company_name`. Diisi melalui profil pengguna Moodle (field bawaan, tidak perlu custom field). |

---

## 8. Kode Kustom yang Digunakan Plugin

### Ringkasan Fungsi yang Tersedia

| Nama Fungsi Moodle WS | Metode PHP | Tipe | Kapabilitas |
|-----------------------|-----------|------|-------------|
| `local_hrms_get_active_courses` | `get_active_courses()` | read | — |
| `local_hrms_get_all_active_courses` | `get_active_courses()` | read | — *(alias)* |
| `local_hrms_get_course_participants` | `get_course_participants()` | read | — |
| `local_hrms_get_course_results` | `get_course_results()` | read | — |
| `local_hrms_get_users` | `get_users()` | read | — |
| `local_hrms_set_user_suspension` | `set_user_suspension()` | write | `moodle/user:update` |
| `local_hrms_create_course` | `create_course()` | write | `moodle/course:create` |
| `local_hrms_update_course` | `update_course()` | write | `moodle/course:update` |
| `local_hrms_create_user` | `create_user()` | write | `moodle/user:create` |
| `local_hrms_update_user` | `update_user()` | write | `moodle/user:update` |
| `local_hrms_enrol_user` | `enrol_user()` | write | `enrol/manual:enrol` |
| `local_hrms_unenrol_user` | `unenrol_user()` | write | `enrol/manual:unenrol` |
| `local_hrms_get_course_progress` | `get_course_progress()` | read | — |

### Service yang Tersedia

| Service Name | Shortname | Deskripsi |
|---|---|---|
| HRMS Integration Service | `hrms_service` | Kumpulan semua fungsi di atas dalam satu service |

---

*Dokumentasi ini dihasilkan untuk plugin `local_hrms` — HRMS Integration untuk Moodle 4.x.*  
*Maintainer: Prihantoosa \<pht854@gmail.com\>*

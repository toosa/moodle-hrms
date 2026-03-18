# HRMS Integration API — Referensi Fungsi

**Plugin**: `local_hrms` | **Moodle**: 4.0+ | **Protocol**: REST (JSON)

**Developed by Digitos Multimedia Synergy**

---

## Daftar Fungsi

| Fungsi | Tipe | Keterangan |
|--------|------|------------|
| [`local_hrms_get_active_courses`](#1-local_hrms_get_active_courses) | Read | Daftar kursus aktif |
| [`local_hrms_get_course_participants`](#2-local_hrms_get_course_participants) | Read | Peserta kursus |
| [`local_hrms_get_course_results`](#3-local_hrms_get_course_results) | Read | Hasil pembelajaran |
| [`local_hrms_get_course_progress`](#4-local_hrms_get_course_progress) | Read | Progres belajar peserta |
| [`local_hrms_get_users`](#5-local_hrms_get_users) | Read | Daftar pengguna |
| [`local_hrms_set_user_suspension`](#6-local_hrms_set_user_suspension) | Write | Suspend/unsuspend pengguna |
| [`local_hrms_create_course`](#7-local_hrms_create_course) | Write | Buat kursus baru |
| [`local_hrms_update_course`](#8-local_hrms_update_course) | Write | Update setting kursus |
| [`local_hrms_create_user`](#9-local_hrms_create_user) | Write | Buat pengguna baru |
| [`local_hrms_update_user`](#10-local_hrms_update_user) | Write | Update data pengguna |
| [`local_hrms_enrol_user`](#11-local_hrms_enrol_user) | Write | Enrol pengguna ke kursus |
| [`local_hrms_unenrol_user`](#12-local_hrms_unenrol_user) | Write | Unenrol pengguna dari kursus |

---

## 1. `local_hrms_get_active_courses`

**Tipe**: Read  
**Deskripsi**: Mengembalikan daftar semua kursus yang sedang aktif (visible = 1), beserta informasi kategori dan custom field JP.

> **Alias**: `local_hrms_get_all_active_courses` memanggil fungsi yang sama.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `courseid` | int | Tidak | `0` | ID internal kursus. `0` = semua kursus |
| `idnumber` | string | Tidak | `""` | Nomor ID kursus. Digunakan jika `courseid` = 0 |

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
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_active_courses" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA"
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

## 2. `local_hrms_get_course_participants`

**Tipe**: Read  
**Deskripsi**: Mengembalikan daftar peserta yang terdaftar (enrolled) dalam kursus. Dapat disaring per kursus atau mengambil semua kursus sekaligus.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `courseid` | int | Tidak | `0` | ID kursus. `0` = semua kursus |
| `idnumber` | string | Tidak | `""` | Nomor ID kursus. Digunakan jika `courseid` = 0 |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `user_id` | int | ID pengguna Moodle |
| `email` | string | Alamat email pengguna |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `company_name` | string | Nama institusi/perusahaan |
| `course_id` | int | ID kursus |
| `course_idnumber` | string | Nomor ID kursus |
| `course_shortname` | string | Nama pendek kursus |
| `course_name` | string | Nama lengkap kursus |
| `enrollment_date` | int | Tanggal pendaftaran (Unix timestamp) |
| `role` | string | Peran pengguna dalam kursus (contoh: `student`, `editingteacher`, `teacher`) |

#### Contoh Request

```bash
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

## 3. `local_hrms_get_course_results`

**Tipe**: Read  
**Deskripsi**: Mengembalikan hasil pembelajaran per peserta, termasuk nilai akhir dan status penyelesaian kursus. Dapat disaring per kursus dan/atau per pengguna.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `courseid` | int | Tidak | `0` | ID internal kursus. `0` = semua kursus |
| `idnumber` | string | Tidak | `""` | ID number kursus. Diabaikan jika `courseid` > 0 |
| `userid` | int | Tidak | `0` | ID pengguna. `0` = semua pengguna |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `user_id` | int | ID pengguna |
| `email` | string | Email pengguna |
| `firstname` | string | Nama depan |
| `lastname` | string | Nama belakang |
| `company_name` | string | Nama institusi/perusahaan |
| `course_id` | int | ID kursus |
| `course_shortname` | string | Nama pendek kursus |
| `course_name` | string | Nama lengkap kursus |
| `final_grade` | float | Nilai akhir kursus |
| `completion_date` | int | Timestamp penyelesaian kursus. `0` = belum selesai |
| `is_completed` | int | Status penyelesaian: `1` = selesai, `0` = belum |

#### Contoh Request

```bash
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_results" \
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
    "course_shortname": "k3-dasar",
    "course_name": "Pelatihan K3 Dasar",
    "final_grade": 87.50,
    "completion_date": 1743120000,
    "is_completed": 1
  }
]
```

---

## 4. `local_hrms_get_course_progress`

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
# Progres semua peserta (by idnumber)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001"

# Progres pengguna tertentu (by userid)
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "courseid=12" \
  -d "userid=78"

# Progres pengguna tertentu (by email)
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

## 5. `local_hrms_get_users`

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
# Filter by status
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_get_users" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "status=active"

# Filter by email
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

## 6. `local_hrms_set_user_suspension`

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

## 7. `local_hrms_create_course`

**Tipe**: Write  
**Kapabilitas**: `moodle/course:create`  
**Deskripsi**: Membuat kursus baru di Moodle. `fullname`, `shortname`, dan `idnumber` bersifat wajib; parameter lain opsional.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `fullname` | string | Ya | — | Nama lengkap kursus |
| `shortname` | string | Ya | — | Nama pendek kursus (harus unik) |
| `idnumber` | string | Ya | — | Nomor ID kursus (untuk referensi eksternal, harus unik) |
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
| `summary` | string | Deskripsi kursus |
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
  "summary": "",
  "categoryid": 3,
  "startdate": 1748736000,
  "enddate": 0,
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

## 8. `local_hrms_update_course`

**Tipe**: Write  
**Kapabilitas**: `moodle/course:update`  
**Deskripsi**: Mengubah setting kursus yang sudah ada. Kursus diidentifikasi menggunakan **`idnumber`**. Hanya parameter yang dikirimkan yang akan diubah — parameter yang tidak dikirim tidak mempengaruhi data yang ada.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `idnumber` | string | Ya | — | Nomor ID kursus yang akan diubah |
| `fullname` | string | Tidak | `""` | Nama lengkap baru. Kosong = tidak diubah |
| `shortname` | string | Tidak | `""` | Nama pendek baru. Kosong = tidak diubah |
| `new_idnumber` | string | Tidak | `""` | Ganti `idnumber` dengan nilai baru |
| `summary` | string | Tidak | `""` | Deskripsi baru (HTML). Kosong = tidak diubah |
| `categoryid` | int | Tidak | `0` | ID kategori baru. `0` = tidak diubah |
| `startdate` | int | Tidak | `0` | Tanggal mulai baru (Unix timestamp). `0` = tidak diubah |
| `enddate` | int | Tidak | `-1` | Tanggal berakhir. `-1` = tidak diubah; `0` = hapus batas |
| `visible` | int | Tidak | `-1` | `1` = tampil, `0` = sembunyi, `-1` = tidak diubah |
| `jp` | int | Tidak | `0` | Nilai custom field `jp`. `0` = tidak diubah |

#### Response Fields

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | int | ID internal Moodle kursus |
| `shortname` | string | Nama pendek (setelah update) |
| `fullname` | string | Nama lengkap (setelah update) |
| `idnumber` | string | Nomor ID (setelah update) |
| `summary` | string | Deskripsi kursus (setelah update) |
| `categoryid` | int | ID kategori (setelah update) |
| `startdate` | int | Tanggal mulai (setelah update) |
| `enddate` | int | Tanggal berakhir (setelah update) |
| `visible` | int | Status visibilitas (setelah update) |
| `jp` | int | Nilai custom field `jp` (setelah update) |

#### Contoh Request

```bash
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_course" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "idnumber=TRAIN-2026-001" \
  -d "fullname=Pelatihan K3 Dasar (Revisi 2026)" \
  -d "visible=1"
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `invalidrecord` | Tidak ada kursus dengan `idnumber` tersebut |
| `shortnametaken` | `shortname` baru sudah digunakan kursus lain |
| `courseidnumbertaken` | `new_idnumber` sudah digunakan kursus lain |
| `invalidapikey` | API key salah |

---

## 9. `local_hrms_create_user`

**Tipe**: Write  
**Kapabilitas**: `moodle/user:create`  
**Deskripsi**: Membuat akun pengguna baru di Moodle. Jika email sudah digunakan, pembuatan akun akan ditolak.

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
| `auth` | string | Tidak | `manual` | Plugin autentikasi |

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
| `phone1` | string | Nomor telepon |
| `city` | string | Kota |
| `country` | string | Kode negara (contoh: `ID`) |
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
  "phone1": "",
  "city": "Jakarta",
  "country": "ID",
  "auth": "manual",
  "timecreated": 1741824000
}
```

#### Error yang Mungkin Muncul

| Errorcode | Penyebab |
|-----------|----------|
| `emailalreadyused` | Email sudah digunakan oleh pengguna lain |
| `usernameexists` | Username sudah digunakan oleh pengguna lain |
| `invalidapikey` | API key salah |

---

## 10. `local_hrms_update_user`

**Tipe**: Write  
**Kapabilitas**: `moodle/user:update`  
**Deskripsi**: Mengubah data pengguna yang sudah ada. Pengguna dapat diidentifikasi melalui `userid` atau `email` (salah satu wajib). Hanya field yang dikirim dengan nilai tidak kosong yang akan diubah.

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

\* Minimal salah satu dari `userid` atau `email` harus diisi.

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
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_update_user" \
  -d "moodlewsrestformat=json" \
  -d "apikey=APIKEY_ANDA" \
  -d "userid=95" \
  -d "firstname=Siti" \
  -d "lastname=Rahayu Baru" \
  -d "institution=PT Baru Indonesia"
```

#### Contoh Response

```json
{
  "id": 95,
  "username": "siti.rahayu",
  "email": "siti.rahayu@perusahaan.co.id",
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

## 11. `local_hrms_enrol_user`

**Tipe**: Write  
**Kapabilitas**: `enrol/manual:enrol`  
**Deskripsi**: Mendaftarkan (enrol) pengguna ke dalam kursus menggunakan manual enrolment plugin. Operasi ini idempoten — jika pengguna sudah terdaftar, enrolment-nya akan diperbarui.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `userid` | int | Tidak* | `0` | ID pengguna. `0` = gunakan `email` |
| `email` | string | Tidak* | `""` | Alamat email pengguna |
| `courseid` | int | Tidak** | `0` | ID internal kursus. `0` = gunakan `idnumber` |
| `idnumber` | string | Tidak** | `""` | Nomor ID kursus |
| `roleid` | int | Tidak | `0` | ID role. `0` = gunakan role default (biasanya `student`) |

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
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN_ANDA" \
  -d "wsfunction=local_hrms_enrol_user" \
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

## 12. `local_hrms_unenrol_user`

**Tipe**: Write  
**Kapabilitas**: `enrol/manual:unenrol`  
**Deskripsi**: Mengeluarkan (unenrol) pengguna dari kursus. Menghapus pengguna dari semua enrol instance di kursus tersebut.

#### Parameter Request

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `apikey` | string | Ya | — | API key HRMS |
| `userid` | int | Tidak* | `0` | ID pengguna. `0` = gunakan `email` |
| `email` | string | Tidak* | `""` | Alamat email pengguna |
| `courseid` | int | Tidak** | `0` | ID internal kursus. `0` = gunakan `idnumber` |
| `idnumber` | string | Tidak** | `""` | Nomor ID kursus |

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

## Format Error Response

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

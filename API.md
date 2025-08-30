# Greeting Ads API Documentation

API untuk mengakses data greeting ads dengan sistem autentikasi Bearer token.

## Base URL
```
{your-wordpress-site}/wp-json/greeting/v1/
```

## Authentication
Semua endpoint memerlukan Bearer token authentication:

```
Authorization: Bearer c2e1a7f62f8147e48a1c3f960bdcb176
```

## Endpoints

### 1. Get All Data (Original)
**GET** `/get`

Mengembalikan semua data dari tabel greeting_ads_data tanpa filter.

**Response:**
```json
[
  {
    "id": "1",
    "kata_kunci": "sepatu running",
    "grup_iklan": "Sepatu Olahraga",
    "id_grup_iklan": "ADG001",
    "nomor_kata_kunci": "123",
    "greeting": "Halo! Tertarik dengan sepatu running berkualitas?"
  }
]
```

---

### 2. Get All Data with Filter & Pagination
**GET** `/all`

Mengembalikan data dengan fitur filtering dan pagination.

**Parameters:**
- `kata_kunci` (string) - Filter kata kunci (LIKE search)
- `grup_iklan` (string) - Filter grup iklan (LIKE search)
- `id_grup_iklan` (string) - Filter ID grup iklan (exact match)
- `nomor_kata_kunci` (string) - Filter nomor kata kunci (exact match)
- `greeting` (string) - Filter greeting (LIKE search)
- `search` (string) - Global search di semua kolom
- `page` (int) - Nomor halaman (default: 1)
- `per_page` (int) - Jumlah per halaman (default: 50, max: 100)

**Examples:**

1. **Basic pagination:**
```
GET /all?page=1&per_page=20
```

2. **Filter by specific ad group:**
```
GET /all?id_grup_iklan=ADG001
```

3. **Search across all fields:**
```
GET /all?search=sepatu&page=1&per_page=10
```

4. **Multiple filters:**
```
GET /all?grup_iklan=Sepatu&nomor_kata_kunci=123&page=1
```

**Response:**
```json
{
  "data": [
    {
      "id": "1",
      "kata_kunci": "sepatu running",
      "grup_iklan": "Sepatu Olahraga",
      "id_grup_iklan": "ADG001",
      "nomor_kata_kunci": "123",
      "greeting": "Halo! Tertarik dengan sepatu running berkualitas?"
    }
  ],
  "pagination": {
    "total_items": 150,
    "total_pages": 8,
    "current_page": 1,
    "per_page": 20
  }
}
```

---

### 3. Sync Data (For Integration)
**GET** `/sync`

Endpoint khusus untuk sinkronisasi data dengan aplikasi eksternal.

**Parameters:**
- `format` (string) - Format output: `json`, `csv`, `xml` (default: json)
- `limit` (int) - Batasi jumlah records (max: 10,000)
- `id_from` (int) - ID awal range
- `id_to` (int) - ID akhir range

**Use Cases:**

1. **Full sync (small dataset):**
```
GET /sync
```

2. **Chunked sync (large dataset):**
```
GET /sync?id_from=1&id_to=1000&limit=1000
GET /sync?id_from=1001&id_to=2000&limit=1000
```

3. **Export to CSV:**
```
GET /sync?format=csv
```

4. **Export to XML:**
```
GET /sync?format=xml
```

**JSON Response:**
```json
{
  "success": true,
  "total_records": 1500,
  "returned_records": 1000,
  "data": [
    {
      "id": "1",
      "kata_kunci": "sepatu running",
      "grup_iklan": "Sepatu Olahraga",
      "id_grup_iklan": "ADG001",
      "nomor_kata_kunci": "123",
      "greeting": "Halo! Tertarik dengan sepatu running berkualitas?"
    }
  ],
  "sync_info": {
    "timestamp": "2024-01-15 10:30:45",
    "format": "json",
    "id_range": {
      "from": "1",
      "to": "1000"
    }
  }
}
```

**CSV Response:**
Headers: `Content-Type: text/csv`, `Content-Disposition: attachment; filename="greeting_ads_data.csv"`

```csv
id,kata_kunci,grup_iklan,id_grup_iklan,nomor_kata_kunci,greeting
"1","sepatu running","Sepatu Olahraga","ADG001","123","Halo! Tertarik dengan sepatu running berkualitas?"
```

**XML Response:**
```xml
<greeting_ads_sync>
    <success>true</success>
    <total_records>1500</total_records>
    <returned_records>1000</returned_records>
    <sync_info>
        <timestamp>2024-01-15 10:30:45</timestamp>
        <format>xml</format>
    </sync_info>
    <records>
        <record>
            <id>1</id>
            <kata_kunci>sepatu running</kata_kunci>
            <grup_iklan>Sepatu Olahraga</grup_iklan>
            <id_grup_iklan>ADG001</id_grup_iklan>
            <nomor_kata_kunci>123</nomor_kata_kunci>
            <greeting>Halo! Tertarik dengan sepatu running berkualitas?</greeting>
        </record>
    </records>
</greeting_ads_sync>
```

## Error Responses

**Authentication Error:**
```json
{
  "code": "invalid_token",
  "message": "Invalid API token",
  "data": {
    "status": 403
  }
}
```

**Missing Authorization Header:**
```json
{
  "code": "no_auth_header",
  "message": "Authorization header missing",
  "data": {
    "status": 403
  }
}
```

## Integration Examples

### JavaScript (Fetch API)
```javascript
const response = await fetch('https://yoursite.com/wp-json/greeting/v1/all?page=1&per_page=20', {
  headers: {
    'Authorization': 'Bearer c2e1a7f62f8147e48a1c3f960bdcb176',
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
console.log(data);
```

### PHP (cURL)
```php
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://yoursite.com/wp-json/greeting/v1/sync?format=json&limit=100',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer c2e1a7f62f8147e48a1c3f960bdcb176',
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);
```

### Python (requests)
```python
import requests

headers = {
    'Authorization': 'Bearer c2e1a7f62f8147e48a1c3f960bdcb176',
    'Content-Type': 'application/json'
}

response = requests.get(
    'https://yoursite.com/wp-json/greeting/v1/all',
    headers=headers,
    params={'search': 'sepatu', 'page': 1, 'per_page': 50}
)

data = response.json()
print(data)
```

## Best Practices

1. **Pagination**: Gunakan pagination untuk dataset besar
2. **Chunked Sync**: Untuk sinkronisasi data besar, gunakan parameter `id_from` dan `id_to`
3. **Rate Limiting**: Implementasikan delay antar request untuk mencegah overload server
4. **Error Handling**: Selalu handle error response dengan proper exception handling
5. **Caching**: Cache response data jika memungkinkan untuk mengurangi API calls

## Rate Limits

- Tidak ada rate limit khusus, namun disarankan max 10 requests per detik
- Untuk bulk sync, gunakan delay 1-2 detik antar request

## Security Notes

- Token API bersifat static, simpan dengan aman
- Gunakan HTTPS untuk semua API calls
- Jangan expose token di client-side code
- Monitor API usage untuk deteksi anomali
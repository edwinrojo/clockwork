# API Documentation

## Authentication

All API endpoints require authentication via Sanctum Bearer token. Include the token in the Authorization header:

```
Authorization: Bearer {token}
```

All responses return JSON format with `Content-Type: application/json`.

---

## Scanners API

### List Scanners

**GET** `/api/scanners`

Returns a paginated list of scanners.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Search by scanner name, uid, or host |
| `active` | boolean | true | Filter by scanner active status |
| `inactive` | boolean | false | Include inactive scanners |

#### Response

```json
{
    "data": [
        {
            "id": "01j730j5p980xg159b46akb30w",
            "name": "scanner-name",
            "uid": "123",
            "priority": true,
            "active": true,
            "synced": "2025-11-18T12:00:00.000000Z",
            "employees": 42
        }
    ],
    "links": {
        "first": "http://localhost/api/scanners?page=1",
        "last": "http://localhost/api/scanners?page=10",
        "prev": null,
        "next": "http://localhost/api/scanners?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 100,
        "to": 100,
        "total": 1000
    }
}
```

#### Examples

```bash
# Get all active scanners
GET /api/scanners?active=true&inactive=false

# Search for scanners
GET /api/scanners?search=coliseum

# Get inactive scanners only
GET /api/scanners?active=false&inactive=true

# Get all scanners (active and inactive)
GET /api/scanners?active=true&inactive=true

# Paginate results
GET /api/scanners?paginate=50
```

---

### Get Scanner

**GET** `/api/scanners/{id}`

Returns a single scanner by ID.

#### Response

```json
{
    "data": {
        "id": "01j730j5p980xg159b46akb30w",
        "name": "scanner-name",
        "uid": "123",
        "priority": true,
        "active": true,
        "synced": "2025-11-18T12:00:00.000000Z",
        "employees": 42
    }
}
```

---

### List Scanner Employees

**GET** `/api/scanners/{scanner_id}/employees`

Returns a paginated list of employees enrolled in a specific scanner.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Global search across all name fields |
| `first_name` | string | - | Filter by first name |
| `middle_name` | string | - | Filter by middle name |
| `last_name` | string | - | Filter by last name |
| `qualifier_name` | string | - | Filter by qualifier name |
| `status` | string | - | Filter by employee status |
| `substatus` | string | - | Filter by employee substatus |
| `active` | boolean | true | Filter by enrollment active status |
| `inactive` | boolean | false | Include inactive enrollments |

#### Response

```json
{
    "data": [
        {
            "id": "01ja7fmk8drvvrmx1xab3hjvgy",
            "first_name": "John",
            "middle_name": "Doe",
            "last_name": "Smith",
            "prefix_name": null,
            "suffix_name": null,
            "qualifier_name": "N/A",
            "status": "contractual",
            "substatus": "contract-of-service",
            "tag": "jds001",
            "email": "john.smith@example.com",
            "birthdate": "1990-01-15T00:00:00.000000Z",
            "sex": "male",
            "uid": "12345"
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

#### Examples

```bash
# Get all active enrollments
GET /api/scanners/01j730j5p980xg159b46akb30w/employees?active=true

# Search employees by name
GET /api/scanners/01j730j5p980xg159b46akb30w/employees?search=john

# Filter by last name
GET /api/scanners/01j730j5p980xg159b46akb30w/employees?last_name=smith

# Filter by status
GET /api/scanners/01j730j5p980xg159b46akb30w/employees?status=contractual

# Filter by substatus
GET /api/scanners/01j730j5p980xg159b46akb30w/employees?substatus=contract-of-service

# Get all enrollments (active and inactive)
GET /api/scanners/01j730j5p980xg159b46akb30w/employees?active=true&inactive=true
```

---

## Employees API

### List Employees

**GET** `/api/employees`

Returns a paginated list of employees.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Global search across all name fields |
| `first_name` | string | - | Filter by first name |
| `middle_name` | string | - | Filter by middle name |
| `last_name` | string | - | Filter by last name |
| `qualifier_name` | string | - | Filter by qualifier name |
| `status` | string | - | Filter by employee status |
| `substatus` | string | - | Filter by employee substatus |

#### Response

```json
{
    "data": [
        {
            "id": "01ja7fmk8drvvrmx1xab3hjvgy",
            "first_name": "John",
            "middle_name": "Doe",
            "last_name": "Smith",
            "prefix_name": null,
            "suffix_name": null,
            "qualifier_name": "N/A",
            "status": "contractual",
            "substatus": "contract-of-service",
            "tag": "jds001",
            "email": "john.smith@example.com",
            "birthdate": "1990-01-15T00:00:00.000000Z",
            "sex": "male"
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

#### Examples

```bash
# Get all employees
GET /api/employees

# Search employees
GET /api/employees?search=john smith

# Filter by last name
GET /api/employees?last_name=smith

# Paginate results
GET /api/employees?paginate=50
```

---

### Get Employee

**GET** `/api/employees/{id}`

Returns a single employee by ID with associated scanners, offices, and groups.

#### Response

```json
{
    "data": {
        "id": "01ja7fmk8drvvrmx1xab3hjvgy",
        "first_name": "John",
        "middle_name": "Doe",
        "last_name": "Smith",
        "prefix_name": null,
        "suffix_name": null,
        "qualifier_name": "N/A",
        "status": "permanent",
        "substatus": "",
        "tag": "jds001",
        "email": "john.smith@example.com",
        "birthdate": "1990-01-15T00:00:00.000000Z",
        "sex": "male",
        "scanners": [
            {
                "id": "01j730j5p980xg159b46akb30w",
                "name": "scanner-name",
                "uid": "12345",
                "priority": true,
                "active": true,
                "synced": "2025-11-18T12:00:00.000000Z",
                "active": true
            }
        ],
        "offices": [
            {
                "id": "01j0fx3eg1ea41c5ge5qnd4cnb",
                "name": "Office Name",
                "code": "OFFICE-001",
                "logo": "http://localhost/storage/offices/logo.webp",
                "current": true,
                "active": true
            }
        ],
        "groups": [
            {
                "id": "01j0fx3efx6ncwkk3xw2x4nvhv",
                "name": "Group Name",
                "active": true
            }
        ]
    }
}
```

---

### List Employee Scanners

**GET** `/api/employees/{employee_id}/scanners`

Returns a paginated list of scanners where the employee is enrolled.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Search by scanner name, uid, or host |
| `active` | boolean | true | Filter by enrollment active status |
| `inactive` | boolean | false | Include inactive enrollments |

#### Response

```json
{
    "data": [
        {
            "id": "01j730j5p980xg159b46akb30w",
            "name": "scanner-name",
            "uid": "12345",
            "priority": true,
            "active": true,
            "synced": "2025-11-18T12:00:00.000000Z"
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### List Employee Offices

**GET** `/api/employees/{employee_id}/offices`

Returns a paginated list of offices where the employee is deployed.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Search by office name or code |
| `active` | boolean | true | Filter by deployment active status |
| `inactive` | boolean | false | Include inactive deployments |

#### Response

```json
{
    "data": [
        {
            "id": "01j0fx3eg1ea41c5ge5qnd4cnb",
            "name": "Office Name",
            "code": "OFFICE-001",
            "logo": "http://localhost/storage/offices/logo.webp",
            "current": true,
            "active": true
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

## Offices API

### List Offices

**GET** `/api/offices`

Returns a paginated list of offices.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Search by office name or code |

#### Response

```json
{
    "data": [
        {
            "id": "01j0fx3eg1ea41c5ge5qnd4cnb",
            "name": "Provincial Information and Communications Technology Office",
            "code": "PGO-PICTO",
            "logo": "http://localhost/storage/offices/pgo-picto.webp",
            "employees": 150
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

#### Examples

```bash
# Get all offices
GET /api/offices

# Search offices
GET /api/offices?search=PICTO

# Paginate results
GET /api/offices?paginate=50
```

---

### Get Office

**GET** `/api/offices/{id}`

Returns a single office by ID.

#### Response

```json
{
    "data": {
        "id": "01j0fx3eg1ea41c5ge5qnd4cnb",
        "name": "Provincial Information and Communications Technology Office",
        "code": "PGO-PICTO",
        "logo": "http://localhost/storage/offices/pgo-picto.webp",
        "employees": 150
    }
}
```

---

### List Office Employees

**GET** `/api/offices/{office_id}/employees`

Returns a paginated list of employees deployed to a specific office.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `paginate` | integer | 100 | Number of items per page (1-1000) |
| `search` | string | - | Global search across all name fields |
| `first_name` | string | - | Filter by first name |
| `middle_name` | string | - | Filter by middle name |
| `last_name` | string | - | Filter by last name |
| `qualifier_name` | string | - | Filter by qualifier name |
| `status` | string | - | Filter by employee status |
| `substatus` | string | - | Filter by employee substatus |
| `active` | boolean | true | Filter by deployment active status |
| `inactive` | boolean | false | Include inactive deployments |

#### Response

```json
{
    "data": [
        {
            "id": "01ja7fmk8drvvrmx1xab3hjvgy",
            "first_name": "John",
            "middle_name": "Doe",
            "last_name": "Smith",
            "prefix_name": null,
            "suffix_name": null,
            "qualifier_name": "N/A",
            "status": "contractual",
            "substatus": "contract-of-service",
            "tag": "jds001",
            "email": "john.smith@example.com",
            "birthdate": "1990-01-15T00:00:00.000000Z",
            "sex": "male"
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

#### Examples

```bash
# Get all active deployments
GET /api/offices/01j0fx3eg1ea41c5ge5qnd4cnb/employees?active=true

# Search employees
GET /api/offices/01j0fx3eg1ea41c5ge5qnd4cnb/employees?search=john

# Filter by status
GET /api/offices/01j0fx3eg1ea41c5ge5qnd4cnb/employees?status=contractual

# Filter by substatus
GET /api/offices/01j0fx3eg1ea41c5ge5qnd4cnb/employees?substatus=contract-of-service

# Get all deployments (active and inactive)
GET /api/offices/01j0fx3eg1ea41c5ge5qnd4cnb/employees?active=true&inactive=true
```

---

## Filtering Logic

### Active/Inactive Filtering

The `active` and `inactive` parameters work together to filter results:

- `active=true, inactive=false` (default): Returns only active records
- `active=false, inactive=true`: Returns only inactive records
- `active=true, inactive=true`: Returns all records (active and inactive)
- `active=false, inactive=false`: Returns empty result set

This applies to:
- Scanner active status (`/api/scanners`)
- Enrollment active status (`/api/scanners/{id}/employees`, `/api/employees/{id}/scanners`)
- Deployment active status (`/api/offices/{id}/employees`, `/api/employees/{id}/offices`)

### Search Filtering

#### Employee Search

The `search` parameter performs a global search across multiple name fields:
- `first_name`
- `middle_name`
- `last_name`
- `qualifier_name`
- `prefix_name`
- `suffix_name`
- `name` (stored column)
- `full_name` (stored column)

Individual name filters (`first_name`, `middle_name`, `last_name`, `qualifier_name`) can be used for more specific searches.

#### Status and Substatus Filtering

The `status` and `substatus` parameters filter employees by their employment status:

**Available Status Values:**
- `permanent` - Employee has a stable, long-term employment relationship with the organization
- `casual` - Employee is hired on an as-needed basis, typically for short-term or temporary work
- `contractual` - Employee is hired based on a specific contract or agreement, which may be for a fixed term or duration
- `internship` - Employee is engaged in an internship or training program to gain practical experience in a specific field or industry
- `` (empty string) - None

**Available Substatus Values:**
- `job-order` - Employee is hired on a per-job basis, typically for short-term or temporary work
- `contract-of-service` - Employee is engaged in a contract of service, typically for short-term or temporary work
- `` (empty string) - None

**Note:** Substatus is typically used in conjunction with `contractual` status.

```bash
# Filter by status
GET /api/employees?status=contractual

# Filter by substatus
GET /api/employees?substatus=contract-of-service

# Combine status and substatus
GET /api/employees?status=contractual&substatus=contract-of-service
```

Both filters use case-insensitive partial matching (ILIKE).

#### Scanner Search

The `search` parameter searches across:
- `name`
- `uid`
- `host`

#### Office Search

The `search` parameter searches across:
- `name`
- `code`

---

## Sorting

### Scanners

Scanners are sorted by:
1. `priority` (ascending)
2. `name` (ascending)

### Employees

Employees are sorted by:
1. `last_name` (ascending)
2. `first_name` (ascending)
3. `middle_name` (ascending)
4. `suffix_name` (ascending)

### Offices

Offices are sorted by:
1. `code` (ascending)

---

## Pagination

All list endpoints support pagination via the `paginate` query parameter:

- Default: 100 items per page
- Minimum: 1 item per page
- Maximum: 1000 items per page

Pagination links are included in the response under `links` and metadata under `meta`. Query parameters are preserved in pagination links.

---

## Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 404 Not Found

```json
{
    "message": "No query results for model [App\\Models\\Scanner] {id}"
}
```

### 422 Validation Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field": ["Error message"]
    }
}
```


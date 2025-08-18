# Authentication API

## Overview

Endpoints para gestión de autenticación, incluyendo login tradicional y Google OAuth.

## Endpoints

### Login

**POST** `/api/auth/login`

Autentica un usuario con email y contraseña.

#### Request Body

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

#### Response

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe",
      "created_at": "2025-01-15T10:30:00Z"
    },
    "session_id": "sess_abc123def456"
  },
  "message": "Login exitoso"
}
```

#### Error Responses

```json
{
  "success": false,
  "error": "Credenciales inválidas",
  "code": "INVALID_CREDENTIALS"
}
```

### Google OAuth Login

**POST** `/api/auth/google`

Autentica usando Google OAuth token.

#### Request Body

```json
{
  "google_token": "google_oauth_token_here"
}
```

#### Response

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 2,
      "email": "user@gmail.com",
      "name": "Jane Smith",
      "google_id": "123456789",
      "avatar": "https://lh3.googleusercontent.com/...",
      "created_at": "2025-01-15T10:30:00Z"
    },
    "session_id": "sess_xyz789abc123"
  },
  "message": "Login con Google exitoso"
}
```

### Logout

**POST** `/api/auth/logout`

Cierra la sesión del usuario actual.

#### Request

No requiere body. Utiliza la sesión activa.

#### Response

```json
{
  "success": true,
  "message": "Logout exitoso"
}
```

### Check Session

**GET** `/api/auth/check`

Verifica si el usuario está autenticado.

#### Response (Authenticated)

```json
{
  "success": true,
  "data": {
    "authenticated": true,
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe"
    }
  }
}
```

#### Response (Not Authenticated)

```json
{
  "success": true,
  "data": {
    "authenticated": false,
    "user": null
  }
}
```

## Authentication Flow

### Traditional Login Flow

1. User submits email/password
2. Server validates credentials
3. Server creates session
4. Server returns user data and session info
5. Client stores session for subsequent requests

### Google OAuth Flow

1. Client initiates Google OAuth on frontend
2. Google returns OAuth token
3. Client sends token to `/api/auth/google`
4. Server validates token with Google
5. Server creates/updates user record
6. Server creates session
7. Server returns user data and session info

## Session Management

- Sessions are stored server-side
- Session cookies are HTTP-only and secure
- Session timeout: 24 hours of inactivity
- Sessions are automatically cleaned up

## Security Features

- Password hashing with bcrypt
- CSRF protection
- Rate limiting on login attempts
- Session token validation
- Google OAuth token verification

## Error Codes

| Code | Description |
|------|-------------|
| `INVALID_CREDENTIALS` | Email/password combination invalid |
| `GOOGLE_TOKEN_INVALID` | Google OAuth token is invalid |
| `USER_NOT_FOUND` | User account doesn't exist |
| `ACCOUNT_LOCKED` | Too many failed login attempts |
| `SESSION_EXPIRED` | User session has expired |
| `ALREADY_AUTHENTICATED` | User is already logged in |

---

*Última actualización: 18 de Agosto de 2025*

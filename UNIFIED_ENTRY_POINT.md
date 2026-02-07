# TOPINV - Single Entry Point Structure

## New Application Architecture

The application now uses a **single unified entry point** at the root, eliminating the need to access pages through folder paths like `/public/` or `/admin/`.

### File Structure

```
/topinv/
├── index.php                 ← MAIN ENTRY POINT (all requests start here)
├── api/
│   ├── index.php             ← REST API router
│   └── .htaccess             ← API URL rewriting
├── backend/
│   ├── core/                 ← Core classes (Database, Auth, Router, Response)
│   ├── services/             ← Business logic services
│   └── database.sql          ← Database schema
└── public/
    ├── index.html            ← Login page (served by root index.php)
    ├── cashier.html          ← Cashier dashboard (served by root index.php)
    ├── admin.html            ← Admin dashboard (served by root index.php)
    ├── css/
    └── js/
```

## How It Works

### 1. **Initial Request**
```
User accesses: http://localhost/topinv/
              ↓
        index.php (root)
```

### 2. **Authentication Check**
```
No auth token → Show login page (public/index.html)
      ↓
User logs in → API stores JWT token in sessionStorage
      ↓
Redirects to http://localhost/topinv/
      ↓
index.php detects auth → Shows appropriate dashboard
```

### 3. **Routing Logic in index.php**
```javascript
Check sessionStorage for 'authToken' and 'currentUser'
  ├─ Not found → Load login page
  ├─ Role === 'cashier' → Load cashier dashboard
  └─ Role === 'admin' → Load admin dashboard

Each page loads its own JavaScript:
  - Login → auth.js
  - Cashier → cashier.js
  - Admin → admin.js
  
All pages load → common.js (shared utilities)
```

## Key Changes

### URLs
- ❌ Old: `http://localhost/topinv/public/index.html`
- ✅ New: `http://localhost/topinv/`

- ❌ Old: `http://localhost/topinv/public/cashier.html`
- ✅ New: `http://localhost/topinv/` (automatic routing)

- ❌ Old: `http://localhost/topinv/public/admin.html`
- ✅ New: `http://localhost/topinv/` (automatic routing)

### API
- ✅ Same: `http://localhost/topinv/api/*` (unchanged)

### SessionStorage Keys
Before:
```javascript
sessionStorage.setItem('user', JSON.stringify(user));
sessionStorage.setItem('token', jwtToken);
```

After:
```javascript
sessionStorage.setItem('currentUser', JSON.stringify(user));
sessionStorage.setItem('authToken', jwtToken);
```

## Testing Flow

### 1. Start Application
```bash
# Using PHP dev server (no web server needed)
cd /topinv
php -S localhost:8000

# OR using XAMPP Apache
C:\xampp\apache_start.bat
```

### 2. Access Application
```
http://localhost:8000/topinv/        # PHP dev server
http://localhost/topinv/              # Apache
```

### 3. Login Flow
- Opens login page automatically
- Enter credentials (cashier1/password or admin1/password)
- Redirects to root `/topinv/`
- Root index.php detects role
- Loads appropriate dashboard with right JavaScript

### 4. Logout
- Click logout button
- Clears sessionStorage
- Redirects to root `/topinv/`
- Shows login page again

## Benefits

✅ **Single Entry Point** - No more confusing folder paths
✅ **Clean URLs** - Looks professional and standard
✅ **Works Locally** - No web server path rewriting needed
✅ **Easy Routing** - JavaScript handles UI switching seamlessly
✅ **SEO Friendly** - Single root URL pattern
✅ **Scalable** - Easy to add more pages/roles

## Technical Details

### index.php Responsibilities
1. Check `sessionStorage` for JWT token (client-side via JavaScript)
2. Load appropriate HTML page dynamically via `fetch()`
3. Inject HTML into `#app-container` div
4. Load corresponding JavaScript file

### Data Flow
```
Client Request → Root index.php
                    ↓
            Check sessionStorage (JS)
                    ↓
        Load appropriate HTML + JS
                    ↓
            User interacts with page
                    ↓
         Page makes API calls to /api/*
                    ↓
        API validates JWT token
                    ↓
            Returns data/response
                    ↓
        JavaScript updates UI
```

## Compatibility

✅ Works with PHP Development Server
✅ Works with Apache (XAMPP)
✅ Works with Nginx
✅ No additional configuration needed
✅ No URL rewriting required (unlike old setup)

## Migration from Old URLs

If you had bookmarks or links to old URLs:
- `/topinv/public/index.html` → now just `/topinv/`
- `/topinv/public/cashier.html` → now just `/topinv/`
- `/topinv/public/admin.html` → now just `/topinv/`

All routes through the same root URL!

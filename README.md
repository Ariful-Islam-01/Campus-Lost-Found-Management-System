# Campus Lost & Found Management System

## Default Credentials

### Admin
- Email: `admin@campus.local`
- Password: `Admin@1234`
- Redirects to `admin-dashboard.php`
- Use this account for report moderation, claims review, audit log access, and admin-only actions.

### Student User
- Email: `student@campus.local`
- Password: `Student@1234`
- Redirects to `dashboard.php`
- Use this account for regular student actions like reporting lost/found items and viewing claims.

## Notes
- The same login page is used for both admin and student accounts.
- Admin users are redirected automatically to the admin dashboard.
- Regular users are redirected to the student dashboard.
- The database initializer seeds both accounts automatically if they do not already exist.

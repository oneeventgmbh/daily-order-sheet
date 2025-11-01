# Changelog

All notable changes to the Daily Order Sheet plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-01

### Added
- Initial release
- Display WooCommerce orders for Events Calendar events
- Date picker with AJAX loading (no page reload)
- Print-optimized layout
- Role-based access control with custom capability `view_daily_order_sheet`
- Summary statistics (total orders, tickets, events)
- Column visibility toggles (saved per user)
- Sortable table columns
- Clickable order numbers linking to WooCommerce edit page
- Transient caching with 1-hour expiration
- Automatic cache invalidation on order/event updates
- Manual refresh button to bypass cache
- Cache status indicators (Fresh/Cached badges)
- PII access logging for GDPR/CCPA compliance
- Comprehensive error handling
- Support for all WooCommerce order statuses
- Color-coded order status badges
- Loading indicators for AJAX requests
- Browser history support (back/forward buttons work)

### Security
- CSRF protection with nonce verification
- XSS prevention with proper output escaping
- SQL injection prevention with prepared statements
- Input validation (date format, range 2000-2050)
- Capability checks on all privileged operations
- Secure AJAX implementation

### Performance
- Transient caching to reduce database queries
- Efficient query patterns
- Smart cache invalidation

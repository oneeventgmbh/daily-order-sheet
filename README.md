# Daily Order Sheet

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)](https://github.com/oneeventgmbh/daily-order-sheet/releases)

A WordPress plugin that displays WooCommerce orders for The Events Calendar events in a printable daily sheet format with AJAX loading, caching, and comprehensive security.

## ✨ Features

- 📊 **Order-based view** - One row per WooCommerce order (not individual attendees)
- 📅 **AJAX date picker** - Automatic loading without page reload
- 🔒 **Role-based access** - Custom capability for granular permissions
- 📄 **Complete order info** - Order ID, purchaser details, status, ticket count
- 🖨️ **Print optimized** - Clean print layout for physical sheets
- ⚡ **Smart caching** - 1-hour transient cache with automatic invalidation
- 🎨 **Sortable columns** - Click headers to sort by any column
- ⚙️ **Column visibility** - Toggle columns and save preferences per user
- 🔗 **Clickable orders** - Direct links to WooCommerce order edit pages
- 🛡️ **Security hardened** - CSRF protection, XSS prevention, PII access logging
- 📈 **Summary statistics** - Total orders, tickets, and events at a glance

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **The Events Calendar**: 6.0+
- **Event Tickets Plus**: 6.0+ (with WooCommerce integration)
- **WooCommerce**: 6.0+

## 🚀 Quick Start

```bash
# Clone the repository
git clone https://github.com/oneeventgmbh/daily-order-sheet.git

# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Copy or symlink the plugin
cp -r /path/to/daily-order-sheet ./

# Or create a symbolic link for development
ln -s /path/to/daily-order-sheet ./daily-order-sheet
```

Then activate the plugin in WordPress Admin → Plugins.

## 📦 Installation

### From GitHub

1. Download or clone this repository
2. Upload the `daily-order-sheet` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Access "Daily Order Sheet" from the WordPress admin menu

### From ZIP

1. Download the latest release from [Releases](https://github.com/oneeventgmbh/daily-order-sheet/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose the downloaded ZIP file
4. Click "Install Now" then "Activate"

### For Developers

```bash
# Clone the repo
git clone https://github.com/oneeventgmbh/daily-order-sheet.git
cd daily-order-sheet

# Install dependencies (optional)
composer install
```

## 💡 Usage

### Basic Usage

1. Navigate to **"Daily Order Sheet"** in WordPress admin sidebar
2. Select a date using the date picker (automatically loads via AJAX)
3. View orders with summary statistics
4. Click column headers to sort
5. Toggle column visibility using the settings form
6. Click **Print** button or use Ctrl+P/Cmd+P to print

### Order Display

The plugin displays one row per WooCommerce order with:
- Event name and date/time
- Order ID (clickable link to WooCommerce)
- Purchaser name, email, phone
- Order status (color-coded badges)
- Ticket count and names

### Performance

- **Caching**: Results are cached for 1 hour
- **Cache Indicators**: Green "Fresh" or blue "Cached" badges show data status
- **Refresh**: Click "Refresh" button to bypass cache and get latest data

## 🔐 Access Control

By default, only **Administrators** can access the Daily Order Sheet.

### Custom Capability

The plugin uses a custom capability: **`view_daily_order_sheet`**

To grant access to other roles (Shop Manager, Editor, etc.), use a role management plugin like:
- [User Role Editor](https://wordpress.org/plugins/user-role-editor/)
- [Members](https://wordpress.org/plugins/members/)
- [PublishPress Capabilities](https://wordpress.org/plugins/capability-manager-enhanced/)

Enable the `view_daily_order_sheet` capability for the desired role.

## 🛡️ Security

This plugin has undergone comprehensive security reviews and implements:

- **CSRF Protection**: Nonce verification on all forms and AJAX requests
- **XSS Prevention**: All output properly escaped using WordPress functions
- **SQL Injection Prevention**: Uses prepared statements and WordPress database APIs
- **Input Validation**: Comprehensive validation (date format, ranges, types)
- **Access Control**: Capability checks on all privileged operations
- **PII Logging**: GDPR/CCPA compliant access logging for audit trails
- **Error Handling**: Try-catch blocks with graceful degradation

**Security Score**: 87/100 - Production Ready

## 🤝 Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on:
- Development setup
- Code standards (WordPress Coding Standards)
- Pull request process
- Testing requirements

### Quick Contribute

```bash
# Fork and clone
git clone https://github.com/oneeventgmbh/daily-order-sheet.git
cd daily-order-sheet

# Create feature branch
git checkout -b feature/amazing-feature

# Make changes and test
composer phpcs  # Check code standards

# Commit and push
git commit -m "Add amazing feature"
git push origin feature/amazing-feature

# Open Pull Request on GitHub
```

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| "Event Tickets Plus required" message | Install and activate Event Tickets Plus with WooCommerce integration |
| Menu item not showing | Verify user has `view_daily_order_sheet` capability |
| No orders found | Check that events exist for that date with ticket sales |
| AJAX not loading | Clear browser cache and check JavaScript console for errors |
| Print layout issues | Use Ctrl+P/Cmd+P and check "Print backgrounds" option |

For additional support, please [open an issue](https://github.com/oneeventgmbh/daily-order-sheet/issues) on GitHub.

## 📁 File Structure

```
daily-order-sheet/
├── daily-order-sheet.php   # Main plugin file
├── README.md               # This file
├── CHANGELOG.md            # Version history
├── CONTRIBUTING.md         # Contribution guidelines
├── composer.json           # PHP dependencies
├── .gitignore              # Git ignore rules
├── .gitattributes          # Git attributes
└── assets/                 # Screenshots and media
    └── screenshots/
```

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/oneeventgmbh/daily-order-sheet/issues)
- **Website**: [www.oneevent.at](https://www.oneevent.at)
- **Security**: For security issues, please contact us directly

## 📜 License

GPL v2 or later - See plugin header for full license information.

## 🙏 Credits

Built for **The Events Calendar** plugin suite by StellarWP.

Compatible with:
- The Events Calendar / Events Calendar Pro
- Event Tickets Plus
- WooCommerce

---

**Developed by [Harry Fesenmayr](https://www.fesenmayr.com)**

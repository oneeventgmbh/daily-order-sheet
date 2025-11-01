# Contributing to Daily Order Sheet

Thank you for your interest in contributing to the Daily Order Sheet plugin by OneEvent GmbH!

## Development Setup

1. Clone the repository
2. Install dependencies (optional): `composer install`
3. Set up a local WordPress installation with:
   - WordPress 5.8+
   - PHP 7.4+
   - The Events Calendar 6.0+
   - Event Tickets Plus 6.0+
   - WooCommerce 6.0+

## Code Standards

This plugin follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).

### Running Code Sniffer

If you have Composer installed:

```bash
composer phpcs
```

To automatically fix some issues:

```bash
composer phpcbf
```

## Security

Security is a top priority. This plugin:
- Uses nonce verification for all forms
- Implements proper capability checks
- Escapes all output
- Sanitizes all input
- Uses prepared statements for database queries
- Logs PII access for compliance

### Reporting Security Issues

If you discover a security vulnerability, please contact us directly instead of using the public issue tracker.

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Test thoroughly:
   - Test with different date ranges
   - Test with multiple events
   - Test print functionality
   - Test with different user roles
   - Verify AJAX functionality
5. Ensure code follows WordPress standards
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to your branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## Testing Checklist

Before submitting a PR, ensure:

- [ ] Code follows WordPress coding standards
- [ ] All functionality works as expected
- [ ] No PHP errors or warnings
- [ ] No JavaScript console errors
- [ ] Works with PHP 7.4+
- [ ] Compatible with WordPress 5.8+
- [ ] AJAX loading works properly
- [ ] Print layout displays correctly
- [ ] Security measures are maintained (nonces, escaping, sanitization)
- [ ] Cache functionality works
- [ ] PII access logging is functional

## Questions?

Feel free to open an issue for questions or discussions about contributing.

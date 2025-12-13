[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# suspend

A flexible suspension and banning system for Laravel with pluggable strategies and resolvers

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/suspend
```

## Documentation

- **[Getting Started](https://docs.cline.sh/suspend/getting-started/)** - Installation and quick start
- **[Entity Suspensions](https://docs.cline.sh/suspend/entity-suspensions/)** - Suspend users and models
- **[Context Matching](https://docs.cline.sh/suspend/context-matching/)** - Pattern-based blocking
- **[Strategies](https://docs.cline.sh/suspend/strategies/)** - Conditional suspension logic
- **[Middleware](https://docs.cline.sh/suspend/middleware/)** - Route protection
- **[Events](https://docs.cline.sh/suspend/events/)** - Lifecycle event handling
- **[Querying](https://docs.cline.sh/suspend/querying/)** - Finding suspensions
- **[Configuration](https://docs.cline.sh/suspend/configuration/)** - Full config reference

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/suspend/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/suspend.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/suspend.svg

[link-tests]: https://github.com/faustbrian/suspend/actions
[link-packagist]: https://packagist.org/packages/cline/suspend
[link-downloads]: https://packagist.org/packages/cline/suspend
[link-security]: https://github.com/faustbrian/suspend/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors

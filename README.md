[![GitHub issues](https://img.shields.io/github/issues/VladReshet/Artisanui)](https://github.com/VladReshet/Artisanui/issues)
[![GitHub forks](https://img.shields.io/github/forks/VladReshet/Artisanui)](https://github.com/VladReshet/Artisanui/network)
[![GitHub stars](https://img.shields.io/github/stars/VladReshet/Artisanui)](https://github.com/VladReshet/Artisanui/stargazers)
[![GitHub license](https://img.shields.io/github/license/VladReshet/Artisanui)](https://github.com/VladReshet/ArtisanUI/blob/master/LICENSE)


🖥️ CLI User Interface for Laravel Artisan. Supports customizing (config/artisanui.php)!

![Preview](./preview.jpg)

## Installation

You can install the package via composer:

```bash
composer require vladreshet/artisanui --dev
```
after updating composer, add the ServiceProvider to the providers array in config/app.php


_VladReshet\ArtisanUI\ArtisanUIServiceProvider::class_


this will allow you to do the next:
```bash
php artisan vendor:publish --provider=VladReshet\\ArtisanUI\\ArtisanUIServiceProvider
```

**Attention! Now this package uses not stable version of one of its dependencies. It will be fixed as soon as that dependency will release new update, but for now your project minimum-stability settings should be not higher than "dev" to install this package**

## Usage

You can use it like a usual laravel artisan.

``` bash
php artisanui
```
or
``` bash
./artisanui
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email vlreshet@gmail.com instead of using the issue tracker.

## Credits

- [Vlad Reshetilo](https://github.com/vladreshet)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Laravel Data Explorer

> [!WARNING]  
> This is still beta software - use with caution.

## Installation

```bash
composer require ccharz/laravel-data-explorer
```

## Usage

```bash
php artisan data-explorer
```

You can also specify the database connection which should be used:

```bash
php artisan data-explorer --connection=sqlite
```

## ToDo

- Jump To Foreign Key Target
- Sort by column
- Show full table values in sidebar
- Filter data
- Show indices
- Show sql errors
- Paginate manual sql

## License

Laravel Data Explorer is licensed under [The MIT License (MIT)](LICENSE).

## Acknowledgments

A big **Thank You** goes out to [Aaron Francis](https://github.com/aarondfrancis), [Joe Tannenbaum](https://github.com/joetannenbaum) and the [Contributors of Laravel Prompts](https://github.com/laravel/prompts) for all their work on Laravel Terminal-User-Interfaces.

# Laravel Data Explorer

> [!WARNING]  
> This is still beta software - use with caution.

## About

This package provides a terminal user interface to explore the tables of your database.

![data_explorer](https://github.com/user-attachments/assets/d93ccfaa-8704-4929-9f71-627f302819ef)

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
- Show full table values in sidebar
- Filter data
- Show indices
- Paginate manual sql
- Re-Render on resize

## License

Laravel Data Explorer is licensed under [The MIT License (MIT)](LICENSE).

## Acknowledgments

A big **Thank You** goes out to [Aaron Francis](https://github.com/aarondfrancis), [Joe Tannenbaum](https://github.com/joetannenbaum) and the [Contributors of Laravel Prompts](https://github.com/laravel/prompts) for all their work on Laravel Terminal-User-Interfaces.

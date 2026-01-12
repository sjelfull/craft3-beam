# Beam for Craft CMS

[![Latest Version](https://img.shields.io/github/release/sjelfull/craft3-beam.svg?style=flat-square)](https://github.com/sjelfull/craft3-beam/releases)
[![License](https://img.shields.io/github/license/sjelfull/craft3-beam.svg?style=flat-square)](LICENSE.md)

> Generate CSV and Excel (XLSX) files directly from your Craft CMS templates

![Screenshot](resources/img/plugin-logo.png)

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Usage guide](#usage-guide)
  - [Basic usage](#basic-usage)
  - [Output formats](#output-formats)
  - [Dynamic content](#dynamic-content)
  - [Configuration methods](#configuration-methods)
- [Advanced features](#advanced-features)
  - [Custom cell formatting](#custom-cell-formatting)
  - [Supported format types](#supported-format-types)
  - [Multiple sheets](#multiple-sheets-xlsx-only)
  - [Soft newlines](#soft-newlines-in-xlsx-cells)
- [Common use cases](#common-use-cases)
- [Load-balanced environments](#load-balanced-environments)
- [About](#about)

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.0.2 or later

<details>
<summary>Legacy version support</summary>

For older Craft CMS versions:
- **Craft 4**: Use Beam 3.x
- **Craft 3**: Use Beam 2.x
</details>

## Installation

Install via Composer from your Craft project directory:

```bash
composer require superbig/craft3-beam
```

> **Note:** The package name is `superbig/craft3-beam` for all Craft CMS versions (3, 4, and 5). The version automatically installed matches your Craft version.

Then install the plugin in the Craft Control Panel:
1. Go to **Settings → Plugins**
2. Find **Beam** and click **Install**

## Quick start

Generate a CSV file with just a few lines:

```twig
{% set beam = craft.beam.create({
    header: ['Name', 'Email'],
    content: [
        ['John Doe', 'john@example.com'],
        ['Jane Doe', 'jane@example.com']
    ]
}) %}
{% do beam.csv() %}
```

That's it! The file will automatically download in the user's browser.

## Usage guide

### Basic usage

Every Beam export starts by creating a Beam instance with `craft.beam.create()`:

```twig
{% set beam = craft.beam.create({
    header: ['Email', 'Name'],
    content: [
        ['test@example.com', 'John Doe'],
        ['another@example.com', 'Jane Doe'],
        ['third@example.com', 'Trond Johansen']
    ]
}) %}
```

### Output formats

#### Generate CSV
```twig
{% do beam.csv() %}
```

#### Generate Excel (XLSX)
```twig
{% do beam.xlsx() %}
```

### Dynamic content

Build your export dynamically using loops and the `append()` method:

```twig
{# Create beam with headers #}
{% set beam = craft.beam.create({
    header: ['Username', 'Name', 'Email']
}) %}

{# Append data from entries or users #}
{% set users = craft.users().group('authors').all() %}
{% for user in users %}
    {% do beam.append([user.username, user.name, user.email]) %}
{% endfor %}

{# Generate the file #}
{% do beam.csv() %}
```

### Configuration methods

Beam provides several methods to customize your export:

<details>
<summary><strong>Set Custom Filename</strong></summary>

```twig
{% set currentDate = now|date('Y-m-d') %}
{% do beam.setFilename("user-report-#{currentDate}") %}
```
</details>

<details>
<summary><strong>Update Headers</strong></summary>

```twig
{% do beam.setHeader(['Username', 'Full Name', 'Email Address']) %}
```
</details>

<details>
<summary><strong>Replace Content</strong></summary>

```twig
{% do beam.setContent([
    ['test@example.com', 'John Doe'],
    ['another@example.com', 'Jane Doe']
]) %}
```
</details>

## Advanced features

### Custom cell formatting

Excel (XLSX) files support custom cell formatting. Define column types in the header:

```twig
{% set beam = craft.beam.create({
    header: [
        'Email',
        'Name',
        { text: 'Amount', type: 'price' },
        { text: 'Date', type: 'date' }
    ],
    content: [
        ['john@example.com', 'John Doe', 1500.50, '2024-01-15'],
        ['jane@example.com', 'Jane Doe', 2300.75, '2024-01-16']
    ]
}) %}
{% do beam.xlsx() %}
```

### Supported format types

| Type     | Excel Format                              | Example Output      |
|----------|-------------------------------------------|---------------------|
| string   | @                                         | Text                |
| integer  | 0                                         | 12345               |
| date     | YYYY-MM-DD                                | 2024-01-15          |
| datetime | YYYY-MM-DD HH:MM:SS                       | 2024-01-15 14:30:00 |
| time     | HH:MM:SS                                  | 14:30:00            |
| price    | #,##0.00                                  | 1,234.56            |
| dollar   | [$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00 | $1,234.56           |
| euro     | #,##0.00 [$€-407];[RED]-#,##0.00 [$€-407] | €1.234,56           |

### Multiple sheets (XLSX only)

You can create an Excel file with multiple sheets using the fluent `sheet()` method:

```twig
{% set beam = craft.beam.create() %}
{% do beam.setFilename('users-by-group') %}

{# Create and populate sheets using fluent methods #}
{% for group in craft.users.groups() %}
    {% set users = craft.users().group(group.handle).all() %}
    
    {# Select/create a sheet and set its header #}
    {% do beam.sheet(group.name).setHeader(['Email', 'Full Name']) %}
    
    {# Append users to the active sheet #}
    {% for user in users %}
        {% do beam.append([user.email, user.fullName]) %}
    {% endfor %}
{% endfor %}

{% do beam.xlsx() %}
```

You can switch between sheets as needed:

```twig
{% set beam = craft.beam.create() %}

{# Set 'Summary' as the active sheet #}
{% do beam.setSheet('Summary') %}
{% do beam.setHeader(['Total Users', 'Active', 'Inactive']) %}
{% do beam.append([100, 75, 25]) %}

{# Switch to 'Details' sheet #}
{% do beam.sheet('Details').setHeader(['Email', 'Name', 'Status']) %}
{% do beam.append(['john@example.com', 'John', 'Active']) %}

{% do beam.xlsx() %}
```

<details>
<summary><strong>More sheet configuration options</strong></summary>

The `sheet()` method also accepts an options array as the second parameter:

```twig
{% do beam.sheet('Products', {
    header: ['ID', 'Name', 'Price']
}) %}
{% do beam.append(['1', 'Product A', '10.00']) %}
```

#### Alternative: Using array-based configuration

If you need to configure all sheets upfront, you can provide a `sheets` array in the options:

```twig
{% set options = {
    filename: 'users-report',
    sheets: [
        {
            name: 'Active Users',
            header: ['Email', 'Name', 'Status'],
            content: [
                [ 'john@example.com', 'John Doe', 'Active' ],
                [ 'jane@example.com', 'Jane Doe', 'Active' ],
            ]
        },
        {
            name: 'Inactive Users',
            header: ['Email', 'Name', 'Status'],
            content: [
                [ 'inactive@example.com', 'Bob Smith', 'Inactive' ],
            ]
        }
    ]
} %}
{% set beam = craft.beam.create(options) %}
{% do beam.xlsx() %}
```

Or build the sheets array dynamically with `setSheets()`:

```twig
{% set beam = craft.beam.create() %}
{% do beam.setFilename('users-by-group') %}

{% set sheets = [] %}
{% for group in craft.users.groups() %}
    {% set users = craft.users().group(group.handle).all() %}
    {% set sheetContent = [] %}
    {% for user in users %}
        {% set sheetContent = sheetContent|merge([[ user.email, user.fullName ]]) %}
    {% endfor %}
    
    {% set sheets = sheets|merge([{
        name: group.name,
        header: ['Email', 'Full Name'],
        content: sheetContent
    }]) %}
{% endfor %}

{% do beam.setSheets(sheets) %}
{% do beam.xlsx() %}
```

**Note:** The `sheets` configuration only works with XLSX exports. If you use it with `csv()`, it will be ignored and a standard single-sheet CSV will be generated.
</details>

### Soft newlines in XLSX cells

Soft newlines (line breaks within cells) are supported in XLSX files. Simply use `\n` in your cell content:

```twig
{% set options = {
    header: ['Name', 'Address'],
    content: [
        [ 'John Doe', "123 Main St\nApt 4B\nNew York, NY" ],
        [ 'Jane Smith', "456 Oak Ave\nSuite 200\nLos Angeles, CA" ],
    ]
} %}
{% set beam = craft.beam.create(options) %}
{% do beam.xlsx() %}
```

You can also join arrays with newlines to create multi-line cells:

```twig
{% set myArray = ['Item 1', 'Item 2', 'Item 3'] %}
{% set options = {
    header: ['Name', 'Items'],
    content: [
        [ 'Order 1', myArray|join("\n") ],
    ]
} %}
{% set beam = craft.beam.create(options) %}
{% do beam.xlsx() %}
```

Text wrapping is enabled by default to properly display multi-line content. If you need to disable it:

```twig
{% do beam.setWrapText(false) %}
```

## Common use cases

<details>
<summary><strong>Export Entry Data</strong></summary>

```twig
{% set beam = craft.beam.create({
    header: ['Title', 'Author', 'Date Published', 'URL']
}) %}

{% set entries = craft.entries()
    .section('blog')
    .orderBy('postDate DESC')
    .all() %}

{% for entry in entries %}
    {% do beam.append([
        entry.title,
        entry.author.fullName,
        entry.postDate|date('Y-m-d'),
        entry.url
    ]) %}
{% endfor %}

{% do beam.csv() %}
```
</details>

<details>
<summary><strong>Export Commerce Orders</strong></summary>

> **Note:** This example requires [Craft Commerce](https://craftcms.com/commerce) to be installed.

```twig
{% set beam = craft.beam.create({
    header: ['Order Number', 'Customer', 'Total', 'Date', 'Status']
}) %}

{% set orders = craft.orders()
    .isCompleted(true)
    .orderBy('dateOrdered DESC')
    .all() %}

{% for order in orders %}
    {% do beam.append([
        order.number,
        order.email,
        order.totalPrice,
        order.dateOrdered|date('Y-m-d'),
        order.orderStatus
    ]) %}
{% endfor %}

{% do beam.xlsx() %}
```
</details>

<details>
<summary><strong>Export with Formatted Numbers</strong></summary>

```twig
{% set beam = craft.beam.create({
    header: [
        'Product',
        { text: 'Price', type: 'dollar' },
        { text: 'Quantity', type: 'integer' },
        { text: 'Total', type: 'dollar' }
    ]
}) %}

{% set products = craft.entries().section('products').all() %}
{% for product in products %}
    {% do beam.append([
        product.title,
        product.price,
        product.stock,
        product.price * product.stock
    ]) %}
{% endfor %}

{% do beam.xlsx() %}
```
</details>

## Load-balanced environments

If you're running on a load-balanced environment (like Fortrabbit, Servd, or Craft Cloud), you may experience intermittent download failures when temporary files are stored on the local filesystem.

Configure Craft to use a shared filesystem for temporary files by setting `tempAssetUploadFs` in your `config/general.php`:

```php
return [
    '*' => [
        'tempAssetUploadFs' => 's3', // use your filesystem handle
    ],
];
```

Or use the `CRAFT_TEMP_ASSET_UPLOAD_FS` environment variable.

See the [Craft documentation](https://craftcms.com/docs/5.x/reference/config/general.html#tempassetuploadfs) for more details.

## About

Brought to you by [Superbig](https://superbig.co)

**Useful Resources:**
- [Report Issues](https://github.com/sjelfull/craft3-beam/issues)
- [View Changelog](https://github.com/sjelfull/craft3-beam/blob/main/CHANGELOG.md)
- [Superbig Website](https://superbig.co)

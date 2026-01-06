# Beam for Craft CMS

[![Latest Version](https://img.shields.io/github/release/sjelfull/craft3-beam.svg?style=flat-square)](https://github.com/sjelfull/craft3-beam/releases)
[![License](https://img.shields.io/github/license/sjelfull/craft3-beam.svg?style=flat-square)](LICENSE.md)

> Generate CSV and Excel (XLSX) files directly from your Craft CMS templates

![Screenshot](resources/img/plugin-logo.png)

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage Guide](#usage-guide)
  - [Basic Usage](#basic-usage)
  - [Output Formats](#output-formats)
  - [Dynamic Content](#dynamic-content)
  - [Configuration Methods](#configuration-methods)
- [Advanced Features](#advanced-features)
  - [Custom Cell Formatting](#custom-cell-formatting)
  - [Supported Format Types](#supported-format-types)
- [Common Use Cases](#common-use-cases)
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

Then install the plugin in the Craft Control Panel:
1. Go to **Settings → Plugins**
2. Find **Beam** and click **Install**

## Quick Start

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

## Usage Guide

### Basic Usage

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

### Output Formats

#### Generate CSV
```twig
{% do beam.csv() %}
```

#### Generate Excel (XLSX)
```twig
{% do beam.xlsx() %}
```

### Dynamic Content

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

### Configuration Methods

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

## Advanced Features

### Custom Cell Formatting

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

### Supported Format Types

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

## Common Use Cases

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

## About

Brought to you by [Superbig](https://superbig.co)

**Useful Resources:**
- [Documentation](https://github.com/sjelfull/craft3-beam/blob/master/README.md)
- [Report Issues](https://github.com/sjelfull/craft3-beam/issues)
- [Changelog](https://github.com/sjelfull/craft3-beam/blob/master/CHANGELOG.md)

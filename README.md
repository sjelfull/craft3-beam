# Beam plugin for Craft CMS 3.x

Generate CSVs and XLS files in your templates

![Screenshot](resources/img/plugin-logo.png)

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require superbig/craft3-beam

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Beam.

## Using Beam

The starting point when working with Beam is to create a instance:

```twig
{% set options = {
    header: ['Email', 'Name'],
    content: [
        [ 'test@example.com', 'John Doe' ],
        [ 'another+test@example.com', 'Jane Doe' ],
        [ 'third+test@example.com', 'Trond Johansen' ],
    ]
} %}
{% set beam = craft.beam.create(options) %}
```

This will return a `BeamModel` behind the scenes.

If you want to append content dynamically, say from a loop, you can use the `append` method:

```twig
{% set myUserQuery = craft.users()
    .group('authors') %}

{# Fetch the users #}
{% set users = myUserQuery.all() %}

{# Display the list #}
{% for user in users %}
    {% do beam.append([user.username, user.name, user.email]) %}
{% endfor %}
```

### To generate an CSV:
```twig
{% do beam.csv() %}
```

### To generate an XLSX:
```twig
{% do beam.xlsx() %}
```

### Changing config on the fly

To set the header of the file (the first row):
```twig
{% do beam.setHeader([ 'Username', 'Name', 'Email' ]) %}
``` 

To set the filename:
```twig
{% set currentDate = now|date('Y-m-d') %}
{% do beam.setFilename("report-#{currentDate}") %}
```

To overwrite the content:
```twig
{% do beam.setContent([
    [ 'test@example.com', 'John Doe' ],
    [ 'another+test@example.com', 'Jane Doe' ],
    [ 'third+test@example.com', 'Trond Johansen' ],
]) %}
```

### Custom cell formatting is supported for XLSX:

```twig
{% set options = {
    header: ['Email', 'Name', { text: 'Number', type: 'number' }, { text: 'Date', type: 'date' }],
    content: [
        [ 'test@example.com', 'John Doe', 100000, '2022-06-10'],
        [ 'another+test@example.com', 'Jane Doe', 252323, '2022-06-22'],
        [ 'third+test@example.com', 'Trond Johansen', 30, '2022-06-22'],
        [ 'third+test@example.com', 'Trond Johansen', 6233, '2023-06-22'],
    ]
} %}
{% set beam = craft.beam.create(options) %}
{%  do beam.xlsx() %}
```

These types are supported:

| Format Type | Maps to the following cell format         |
|-------------|-------------------------------------------|
| string      | @                                         |
| integer     | 0                                         |
| date        | YYYY-MM-DD                                |
| datetime    | YYYY-MM-DD HH:MM:SS                       |
| time        | HH:MM:SS                                  |
| price       | #,##0.00                                  |
| dollar      | [$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00 |
| euro        | #,##0.00 [$€-407];[RED]-#,##0.00 [$€-407] |

### Multiple sheets in Excel (XLSX only):

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

Brought to you by [Superbig](https://superbig.co)

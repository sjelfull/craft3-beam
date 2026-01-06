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

## Load-Balanced Environments

If you're running your site on a load-balanced environment (like Fortrabbit, Servd, or Craft Cloud), you may experience intermittent download failures. This happens because temporary export files are stored on the local filesystem, and subsequent requests may be routed to a different server that doesn't have access to the file.

### Solution: Configure a Shared Temp Directory

To resolve this issue, configure Craft to use a shared temp directory that's accessible by all servers in your load-balanced environment. This is done via Craft's general configuration.

In your `config/general.php` file, set the `tempAssetUploadFs` setting to a filesystem that all servers can access:

```php
return [
    '*' => [
        // other settings...
        
        // Use a shared filesystem for temporary files
        'tempAssetUploadFs' => 's3', // or any filesystem handle you've configured
    ],
];
```

### Setting Up a Shared Filesystem

1. **Create a Filesystem**: In the Craft Control Panel, go to Settings → Filesystems and create a new filesystem that uses cloud storage (AWS S3, Google Cloud Storage, DigitalOcean Spaces, etc.).

2. **Note the Handle**: Make note of the filesystem's handle (e.g., `s3`, `cloudStorage`, etc.).

3. **Update Configuration**: Add the `tempAssetUploadFs` setting to your `config/general.php` as shown above, using your filesystem's handle.

4. **Test**: Try exporting a file multiple times and refreshing the browser. Downloads should now work consistently.

### Alternative: Use a Shared Mount

If you prefer not to use cloud storage for temporary files, you can configure a shared network mount (like NFS or similar) and point Craft's `@storage` alias to this shared location:

```php
return [
    '*' => [
        'aliases' => [
            '@storage' => '/mnt/shared-storage/storage',
        ],
    ],
];
```

This ensures the `storage/runtime/temp/beam/` directory is accessible by all servers.

### More Information

For more details on configuring Craft CMS for multi-server environments, refer to:
- [Craft CMS Documentation - tempAssetUploadFs](https://craftcms.com/docs/5.x/reference/config/general.html#tempassetuploadfs)
- Your hosting provider's documentation on shared storage solutions

Brought to you by [Superbig](https://superbig.co)

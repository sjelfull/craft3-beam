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

To generate an CSV:
```twig
{% do beam.csv() %}
```

To generate an XLSX:
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
{% do beam.setFilename('report-#{currentDate}') %}
```

To overwrite the content:
```twig
{% do beam.setContent([
    [ 'test@example.com', 'John Doe' ],
    [ 'another+test@example.com', 'Jane Doe' ],
    [ 'third+test@example.com', 'Trond Johansen' ],
]) %}
``` 

Brought to you by [Superbig](https://superbig.co)

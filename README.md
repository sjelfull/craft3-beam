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

To generate an CSV:

```twig
{% spaceless %}
{% set options = {
    header: ['Email', 'Name'],
    rows: [
        [ 'test@example.com', 'John Doe' ],
        [ 'another+test@example.com', 'Jane Doe' ],
        [ 'third+test@example.com', 'Trond Johansen' ],
    ]
} %}
{{ craft.beam.csv(options) }}
{% endspaceless %}
```

To generate an XLSX:

```twig
{% spaceless %}
{% set options = {
    header: ['Email', 'Name'],
    rows: [
        [ 'test@example.com', 'John Doe' ],
        [ 'another+test@example.com', 'Jane Doe' ],
        [ 'third+test@example.com', 'Trond Johansen' ],
    ]
} %}
{{ craft.beam.xlsx(options) }}
{% endspaceless %}
```

Brought to you by [Superbig](https://superbig.co)

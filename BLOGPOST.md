# Introducing Beam: Effortless CSV and XLSX Exports for Craft CMS

We're excited to announce the release of Beam, a powerful new plugin for Craft CMS that simplifies the process of generating CSV and XLSX files directly from your templates. Whether you need to create reports, export data, or provide downloadable content to your users, Beam makes it quick and easy.

## Why Beam?

As developers, we often face the challenge of exporting data from our Craft CMS sites. While Craft provides robust content management capabilities, creating exportable files has always required extra steps or custom code. Beam bridges this gap, allowing you to generate CSV and XLSX files with just a few lines of Twig code.

## Key Features

- **Simple Twig Syntax**: Create exports with intuitive template code.
- **Dynamic Content**: Easily append data on the fly, perfect for loops and complex data structures.
- **CSV and XLSX Support**: Generate files in both CSV and Excel formats.
- **Custom Headers and Filenames**: Tailor your exports with custom column headers and filenames.
- **Cell Formatting for XLSX**: Apply number, date, and currency formatting to Excel cells.

## Getting Started

Using Beam is straightforward. Here's a basic example:

\```twig
{% set options = {
    header: ['Email', 'Name'],
    content: [
        ['john@example.com', 'John Doe'],
        ['jane@example.com', 'Jane Doe']
    ]
} %}
{% set beam = craft.beam.create(options) %}
{% do beam.csv() %}
\```

This code snippet will generate a CSV file with email and name data for two users.

## Advanced Usage

Beam really shines when working with dynamic content. For instance, you can export all users from a specific group:

\```twig
{% set beam = craft.beam.create() %}
{% do beam.setHeader(['Username', 'Full Name', 'Email']) %}

{% for user in craft.users.group('members').all() %}
    {% do beam.append([user.username, user.fullName, user.email]) %}
{% endfor %}

{% do beam.xlsx('members-export') %}
\```

This will create an XLSX file containing data for all users in the 'members' group.

## Why We Built Beam

As Craft CMS developers, we frequently encountered projects requiring data exports. We found ourselves writing similar code snippets across different projects to handle these exports. Beam was born out of the desire to standardize and simplify this process, saving time and reducing the potential for errors.

## Looking Ahead

We're committed to continually improving Beam based on community feedback. Some features we're considering for future releases include:

- More advanced styling options for XLSX files
- Integration with Craft's element exporter
- Additional file format support (e.g., PDF)

## Get Started with Beam Today

Beam is available now via Composer. To install, run:

\```
composer require superbig/craft3-beam
\```

Then, install the plugin through the Craft Control Panel.

We can't wait to see how you'll use Beam in your projects. If you have any questions, feature requests, or run into any issues, please don't hesitate to reach out on GitHub or through our support channels.

Happy exporting!

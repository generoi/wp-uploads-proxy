# wp-uploads-proxy

> A wordpress plugin for downloading uploaded files from a production host when
requested locally.

## Features

- Downloads images when hit by 404
- Downloads Timber images when initialized.

## Installation

Only install this on development or staging environments together with the following defines:

```
define('WPUP_IS_LOCAL', env('WPUP_IS_LOCAL') ?: false);
define('WPUP_SITEURL', 'http://example.production');
```

## Development

Install dependencies

    composer install

Run the tests

    npm run test
